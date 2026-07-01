<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiArticleGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_post_ids' => ['required', 'array', 'min:1', 'max:10'],
            'source_post_ids.*' => ['required', 'integer', 'distinct', 'exists:source_posts,id'],
            'ai_prompt_profile_id' => ['required', 'integer', 'exists:ai_prompt_profiles,id'],
        ];
    }
}
