<?php

namespace App\Http\Requests;

use App\Models\WordPressSite;
use App\Support\SocialPostUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class QuickPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'url' => trim((string) $this->input('url')),
            'image_mode' => trim((string) $this->input('image_mode', 'generate')),
            'publish_without_review' => $this->boolean('publish_without_review'),
            'company_ids' => array_values(array_unique(array_filter(
                array_map('intval', (array) ($this->input('company_ids') ?: [$this->input('company_id')])),
                fn (int $id): bool => $id > 0,
            ))),
            'publication_profile_ids' => array_values(array_unique(array_map('intval', (array) $this->input('publication_profile_ids', [])))),
        ]);
    }

    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        SocialPostUrl::validate((string) $value);
                    } catch (InvalidArgumentException $exception) {
                        $fail($exception->getMessage());
                    }
                },
            ],
            'ai_prompt_profile_id' => [
                'required',
                'integer',
                Rule::exists('ai_prompt_profiles', 'id'),
            ],
            'image_mode' => ['required', Rule::in(['generate', 'original'])],
            'publish_without_review' => ['boolean'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => [
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $query
                    ->where('active', true)),
            ],
            'publication_profile_ids' => ['nullable', 'array'],
            'publication_profile_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('wordpress_sites', 'id')->where(fn ($query) => $query
                    ->where('active', true)
                    ->where('status', 'active')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'publication_profile_ids.*.exists' => 'Uno de los perfiles de publicación seleccionados ya no está disponible.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $profileIds = array_values(array_unique(array_map('intval', (array) $this->input('publication_profile_ids', []))));
            $companyIds = array_values(array_unique(array_map('intval', (array) $this->input('company_ids', []))));

            if ($profileIds === []) {
                if ($this->boolean('publish_without_review') && $companyIds === []) {
                    $validator->errors()->add('publication_profile_ids', 'Selecciona al menos un destino para publicar sin revisar.');
                }

                return;
            }

            if ($companyIds === []) {
                return;
            }

            $profiles = WordPressSite::query()->whereIn('id', $profileIds)->get(['id', 'company_id']);

            if ($profiles->contains(fn ($profile): bool => ! in_array((int) $profile->company_id, $companyIds, true))) {
                $validator->errors()->add('publication_profile_ids', 'Todos los destinos deben pertenecer a las empresas seleccionadas.');
            }
        }];
    }

    /** @return array<int, int> */
    public function companyIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->validated('company_ids', []))));
    }

    /** @return array<int, int> */
    public function publicationProfileIds(): array
    {
        $profileIds = array_values(array_unique(array_map('intval', $this->validated('publication_profile_ids', []))));

        if ($profileIds !== [] || $this->companyIds() === []) {
            return $profileIds;
        }

        return WordPressSite::query()
            ->whereIn('company_id', $this->companyIds())
            ->where('active', true)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function publishesWithoutReview(): bool
    {
        return (bool) $this->validated('publish_without_review', false);
    }
}
