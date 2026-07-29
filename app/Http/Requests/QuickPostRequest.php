<?php

namespace App\Http\Requests;

use App\Support\SocialPostUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class QuickPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['url' => trim((string) $this->input('url'))]);
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
        ];
    }
}
