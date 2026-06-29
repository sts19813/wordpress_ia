<?php

namespace App\Http\Requests;

use App\Models\SourceSite;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SourceSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'type' => ['required', Rule::in(array_keys(SourceSite::typeOptions()))],
            'status' => ['required', Rule::in(array_keys(SourceSite::statusOptions()))],
            'frequency_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
            'category' => ['nullable', 'string', 'max:120'],
            'language' => ['required', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', 'integer', 'min:1', 'max:10'],
            'api_key' => ['nullable', 'string', 'max:2048'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:2048'],
            'custom_headers' => ['nullable', 'json'],
            'cookies' => ['nullable', 'json'],
            'auth_method' => ['required', Rule::in(array_keys(SourceSite::authMethodOptions()))],
            'daily_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'last_synced_at' => ['nullable', 'date'],
            'active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'url' => 'URL',
            'type' => 'tipo',
            'status' => 'estado',
            'frequency_minutes' => 'frecuencia de consulta',
            'category' => 'categoría',
            'language' => 'idioma',
            'country' => 'país',
            'priority' => 'prioridad',
            'api_key' => 'API Key',
            'username' => 'usuario',
            'password' => 'password',
            'custom_headers' => 'headers personalizados',
            'cookies' => 'cookies',
            'auth_method' => 'método de autenticación',
            'daily_limit' => 'límite diario',
            'last_synced_at' => 'última sincronización',
            'active' => 'activo',
        ];
    }
}
