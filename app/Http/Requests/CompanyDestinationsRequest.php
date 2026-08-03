<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyDestinationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company && $this->user()->can('update', $company);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'publication_profile_ids' => array_values(array_unique(array_filter(array_map(
                'intval',
                (array) $this->input('publication_profile_ids', []),
            )))),
        ]);
    }

    public function rules(): array
    {
        return [
            'publication_profile_ids' => ['present', 'array'],
            'publication_profile_ids.*' => [
                'integer',
                Rule::exists('wordpress_sites', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'publication_profile_ids' => 'destinos de publicación',
            'publication_profile_ids.*' => 'destino de publicación',
        ];
    }
}
