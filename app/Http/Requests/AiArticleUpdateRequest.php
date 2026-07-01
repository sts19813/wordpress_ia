<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiArticleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'categories' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'string', 'max:2000'],
            'seo_keywords' => ['nullable', 'string', 'max:2000'],
            'conclusion' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
