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
            'use_source_image' => $this->boolean('use_source_image'),
            'generate_image' => $this->boolean('generate_image'),
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    public function rules(): array
    {
        $profile = $this->route('aiPromptProfile');

        return [
            'name' => ['required', Rule::in([
                AiPromptProfile::SYSTEM_EDITORIAL_NAME,
                AiPromptProfile::SYSTEM_SOCIAL_NAME,
            ]), Rule::unique('ai_prompt_profiles', 'name')->ignore($profile)],
            'system_prompt' => ['required', 'string', 'min:50', 'max:20000'],
            'model' => ['required', Rule::in(array_keys(AiPromptProfile::textModelOptions()))],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'writing_style' => ['required', 'string', 'max:255'],
            'tone' => ['required', 'string', 'max:255'],
            'content_length' => ['required', Rule::in(array_keys(AiPromptProfile::lengthOptions()))],
            'language' => ['required', 'string', 'max:10'],
            'audience' => ['required', 'string', 'max:255'],
            'max_output_tokens' => ['required', 'integer', 'min:512', 'max:32000'],
            'use_source_image' => ['boolean'],
            'generate_image' => ['boolean'],
            'image_model' => ['required_if:generate_image,true', 'nullable', Rule::in(array_keys(AiPromptProfile::imageModelOptions()))],
            'image_size' => [Rule::requiredIf(fn (): bool => $this->boolean('generate_image') || $this->boolean('use_source_image')), 'nullable', Rule::in(array_keys(AiPromptProfile::imageSizeOptions()))],
            'image_quality' => ['required_if:generate_image,true', 'nullable', Rule::in(array_keys(AiPromptProfile::imageQualityOptions()))],
            'image_format' => [Rule::requiredIf(fn (): bool => $this->boolean('generate_image') || $this->boolean('use_source_image')), 'nullable', Rule::in(array_keys(AiPromptProfile::imageFormatOptions()))],
            'image_compression' => [Rule::requiredIf(fn (): bool => $this->boolean('generate_image') || $this->boolean('use_source_image')), 'nullable', 'integer', 'min:40', 'max:100'],
            'image_style' => ['required_if:generate_image,true', 'nullable', 'string', 'max:500'],
            'is_default' => ['boolean'],
        ];
    }
}
