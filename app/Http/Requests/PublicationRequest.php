<?php

namespace App\Http\Requests;

use App\Models\AiArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('aiArticle');

        return $article instanceof AiArticle && $this->user()->can('update', $article);
    }

    public function rules(): array
    {
        return [
            'site_ids' => ['nullable', 'array', 'min:1'],
            'site_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('wordpress_sites', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('active', true)
                    ->where('status', 'active')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'site_ids.min' => 'Selecciona al menos un sitio WordPress.',
            'site_ids.*.exists' => 'Uno de los sitios seleccionados no está disponible.',
        ];
    }
}
