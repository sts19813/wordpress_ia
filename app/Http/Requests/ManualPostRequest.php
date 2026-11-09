<?php

namespace App\Http\Requests;

use App\Models\WordPressSite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ManualPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $encodedContent = $this->input('encoded_content');
        $decodedContent = is_string($encodedContent)
            ? base64_decode($encodedContent, true)
            : false;

        $this->merge([
            // The browser sends manual text encoded when uploading media. Some
            // shared-hosting WAF rules inspect multipart text fields and may
            // reject perfectly valid editorial content before Laravel receives
            // the request. Decode it here, before normal validation and storage.
            'content' => trim((string) ($decodedContent !== false ? $decodedContent : $this->input('content'))),
            'publish_as_is' => $this->boolean('publish_as_is'),
            'generate_image_if_missing' => $this->boolean('generate_image_if_missing'),
            'publish_without_review' => $this->boolean('publish_without_review'),
            'company_ids' => array_values(array_unique(array_map('intval', (array) $this->input('company_ids', [])))),
            'publication_profile_ids' => array_values(array_unique(array_map('intval', (array) $this->input('publication_profile_ids', [])))),
        ]);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', $this->boolean('publish_as_is') ? 'min:1' : 'min:80', 'max:20000'],
            'publish_as_is' => ['boolean'],
            'generate_image_if_missing' => ['boolean'],
            'ai_prompt_profile_id' => [
                $this->needsAiProfile() ? 'required' : 'nullable',
                'integer',
                Rule::exists('ai_prompt_profiles', 'id'),
            ],
            'publish_without_review' => ['boolean'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('companies', 'id')->where(fn ($query) => $query
                    ->where('active', true)),
            ],
            'publication_profile_ids' => ['nullable', 'array'],
            'publication_profile_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('wordpress_sites', 'id')->where(fn ($query) => $query
                    ->where('active', true)
                    ->where('status', WordPressSite::STATUS_ACTIVE)),
            ],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:245760'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.min' => $this->boolean('publish_as_is')
                ? 'Agrega el contenido que deseas publicar.'
                : 'Agrega al menos 80 caracteres para que la IA tenga contexto suficiente.',
            'images.max' => 'Puedes subir hasta 8 imágenes o 1 video.',
            'images.*.file' => 'Cada archivo debe ser válido.',
            'images.*.mimes' => 'Usa imágenes JPG, PNG o WebP, o un video MP4 o MOV.',
            'images.*.max' => 'Cada imagen puede pesar máximo 10 MB y el video hasta 240 MB.',
            'publication_profile_ids.*.exists' => 'Uno de los destinos seleccionados ya no está disponible.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $profileIds = array_values(array_unique(array_map('intval', (array) $this->input('publication_profile_ids', []))));
            $companyIds = array_values(array_unique(array_map('intval', (array) $this->input('company_ids', []))));
            $videos = collect($this->file('images', []))
                ->filter(fn ($file): bool => str_starts_with((string) $file?->getClientMimeType(), 'video/')
                    || str_starts_with((string) $file?->getMimeType(), 'video/'));
            $oversizedImage = collect($this->file('images', []))
                ->reject(fn ($file): bool => $videos->contains($file))
                ->contains(fn ($file): bool => (int) $file?->getSize() > 10 * 1024 * 1024);

            if ($oversizedImage) {
                $validator->errors()->add('images', 'Cada imagen puede pesar máximo 10 MB.');
            }

            if ($videos->isNotEmpty()) {
                if ($videos->count() > 1 || count($this->file('images', [])) > 1) {
                    $validator->errors()->add('images', 'Por ahora agrega un solo video o varias imágenes, no ambos en la misma publicación.');
                }

                if (! $this->boolean('publish_as_is')) {
                    $validator->errors()->add('images', 'Los videos se publican sin IA. Usa “Publicar sin IA” para conservar el video original.');
                }
            }

            if ($profileIds === [] && $this->boolean('publish_as_is') && $companyIds !== []) {
                $profileIds = WordPressSite::query()
                    ->whereIn('company_id', $companyIds)
                    ->where('active', true)
                    ->where('status', WordPressSite::STATUS_ACTIVE)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();
            }

            if ($profileIds === []) {
                if (($this->boolean('publish_without_review') || $this->boolean('publish_as_is')) && $companyIds === []) {
                    $validator->errors()->add('publication_profile_ids', 'Selecciona al menos un destino para publicar directamente.');
                } elseif ($this->boolean('publish_as_is')) {
                    $validator->errors()->add('publication_profile_ids', 'Las empresas seleccionadas no tienen destinos activos para publicar directamente.');
                }

                return;
            }

            $profiles = WordPressSite::query()
                ->whereIn('id', $profileIds)
                ->get(['id', 'company_id']);

            if ($profiles->count() !== count($profileIds)) {
                $validator->errors()->add('publication_profile_ids', 'Todos los destinos deben estar disponibles.');

                return;
            }

            if ($companyIds !== []) {
                $invalid = $profiles->contains(fn ($profile): bool => ! in_array((int) $profile->company_id, $companyIds, true));

                if ($invalid) {
                    $validator->errors()->add('publication_profile_ids', 'Los destinos seleccionados deben pertenecer a las empresas marcadas.');
                }
            }

            if ($this->boolean('publish_as_is')
                && WordPressSite::query()->whereIn('id', $profileIds)->where('type', WordPressSite::TYPE_INSTAGRAM)->exists()
                && count($this->file('images', [])) === 0
                && ! $this->generatesImageIfMissing()) {
                $validator->errors()->add('images', 'Agrega al menos una imagen para publicar directamente en Instagram.');
            }
        }];
    }

    /**
     * @return array<int, int>
     */
    public function publicationProfileIds(): array
    {
        $validated = $this->validated();
        $profileIds = array_values(array_unique(array_map('intval', $validated['publication_profile_ids'] ?? [])));
        $companyIds = array_values(array_unique(array_map('intval', $validated['company_ids'] ?? [])));

        if ($profileIds !== [] || $companyIds === []) {
            return $profileIds;
        }

        return WordPressSite::query()
            ->whereIn('company_id', $companyIds)
            ->where('active', true)
            ->where('status', WordPressSite::STATUS_ACTIVE)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function publishesWithoutReview(): bool
    {
        return $this->publishesAsIs() || (bool) $this->validated('publish_without_review', false);
    }

    public function publishesAsIs(): bool
    {
        return (bool) $this->validated('publish_as_is', false);
    }

    public function generatesImageIfMissing(): bool
    {
        return (bool) $this->validated('generate_image_if_missing', false)
            && count($this->file('images', [])) === 0;
    }

    private function needsAiProfile(): bool
    {
        return ! $this->boolean('publish_as_is')
            || ($this->boolean('generate_image_if_missing') && count($this->file('images', [])) === 0);
    }
}
