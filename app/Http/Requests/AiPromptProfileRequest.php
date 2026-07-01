<?php

namespace App\Http\Requests;

use App\Models\AiPromptProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiPromptProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'generate_image' => $this->boolean('generate_image'),
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    public function rules(): array
    {
        $profile = $this->route('aiPromptProfile');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('ai_prompt_profiles')->where('user_id', $this->user()?->id)->ignore($profile)],
            'system_prompt' => ['required', 'string', 'min:50', 'max:20000'],
            'model' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'writing_style' => ['required', 'string', 'max:255'],
            'tone' => ['required', 'string', 'max:255'],
            'content_length' => ['required', Rule::in(array_keys(AiPromptProfile::lengthOptions()))],
            'language' => ['required', 'string', 'max:10'],
            'audience' => ['required', 'string', 'max:255'],
            'max_output_tokens' => ['required', 'integer', 'min:512', 'max:32000'],
            'generate_image' => ['boolean'],
            'image_model' => ['required_if:generate_image,true', 'nullable', Rule::in(array_keys(AiPromptProfile::imageModelOptions()))],
            'image_size' => ['required_if:generate_image,true', 'nullable', 'regex:/^\d{3,4}x\d{3,4}$/'],
            'image_quality' => ['required_if:generate_image,true', 'nullable', Rule::in(['low', 'medium', 'high'])],
            'image_style' => ['required_if:generate_image,true', 'nullable', 'string', 'max:500'],
            'is_default' => ['boolean'],
        ];
    }
}
