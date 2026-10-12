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
        $this->merge([
            'content' => trim((string) $this->input('content')),
            'company_ids' => array_values(array_unique(array_map('intval', (array) $this->input('company_ids', [])))),
            'publication_profile_ids' => array_values(array_unique(array_map('intval', (array) $this->input('publication_profile_ids', [])))),
        ]);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:80', 'max:20000'],
            'ai_prompt_profile_id' => ['required', 'integer', Rule::exists('ai_prompt_profiles', 'id')],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('companies', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('active', true)),
            ],
            'publication_profile_ids' => ['nullable', 'array'],
            'publication_profile_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('wordpress_sites', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('active', true)
                    ->where('status', WordPressSite::STATUS_ACTIVE)),
            ],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.min' => 'Agrega al menos 80 caracteres para que la IA tenga contexto suficiente.',
            'images.max' => 'Puedes subir hasta 8 imágenes.',
            'images.*.image' => 'Cada archivo debe ser una imagen válida.',
            'images.*.mimes' => 'Las imágenes deben ser JPG, PNG o WebP.',
            'images.*.max' => 'Cada imagen puede pesar máximo 10 MB.',
            'publication_profile_ids.*.exists' => 'Uno de los destinos seleccionados ya no está disponible.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $profileIds = array_values(array_unique(array_map('intval', (array) $this->input('publication_profile_ids', []))));
            $companyIds = array_values(array_unique(array_map('intval', (array) $this->input('company_ids', []))));

            if ($profileIds === []) {
                return;
            }

            $profiles = $this->user()->wordpressSites()
                ->whereIn('id', $profileIds)
                ->get(['id', 'company_id']);

            if ($profiles->count() !== count($profileIds)) {
                $validator->errors()->add('publication_profile_ids', 'Todos los destinos deben pertenecer a tu cuenta.');

                return;
            }

            if ($companyIds !== []) {
                $invalid = $profiles->contains(fn ($profile): bool => ! in_array((int) $profile->company_id, $companyIds, true));

                if ($invalid) {
                    $validator->errors()->add('publication_profile_ids', 'Los destinos seleccionados deben pertenecer a las empresas marcadas.');
                }
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

        return $this->user()->wordpressSites()
            ->whereIn('company_id', $companyIds)
            ->where('active', true)
            ->where('status', WordPressSite::STATUS_ACTIVE)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
