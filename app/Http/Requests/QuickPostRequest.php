<?php

namespace App\Http\Requests;

use App\Support\SocialPostUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
                Rule::exists('ai_prompt_profiles', 'id')
                    ->where('user_id', $this->user()->id),
            ],
            'image_mode' => ['required', Rule::in(['generate', 'original'])],
            'publication_profile_ids' => ['nullable', 'array'],
            'publication_profile_ids.*' => [
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
            'publication_profile_ids.*.exists' => 'Uno de los perfiles de publicación seleccionados ya no está disponible.',
        ];
    }
}
