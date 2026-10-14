<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewGeneratedPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_ids' => array_values(array_unique(array_map('intval', (array) $this->input('company_ids', [])))),
            'publication_profile_ids' => array_values(array_unique(array_map('intval', (array) $this->input('publication_profile_ids', [])))),
        ]);
    }

    public function rules(): array
    {
        return [
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => [
                'integer',
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
                    ->where('status', 'active')),
            ],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $profileIds = $this->validated('publication_profile_ids', []);
            $companyIds = $this->validated('company_ids', []);

            if ($profileIds === [] || $companyIds === []) {
                return;
            }

            $invalid = $this->user()->wordpressSites()
                ->whereIn('id', $profileIds)
                ->get(['company_id'])
                ->contains(fn ($profile): bool => ! in_array((int) $profile->company_id, $companyIds, true));

            if ($invalid) {
                $validator->errors()->add('publication_profile_ids', 'Los destinos seleccionados deben pertenecer a las empresas marcadas.');
            }
        }];
    }

    /** @return array<int, int> */
    public function publicationProfileIds(): array
    {
        $profileIds = $this->validated('publication_profile_ids', []);
        $companyIds = $this->validated('company_ids', []);

        if ($profileIds !== [] || $companyIds === []) {
            return $profileIds;
        }

        return $this->user()->wordpressSites()
            ->whereIn('company_id', $companyIds)
            ->where('active', true)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
