<?php

namespace App\Http\Requests;

use App\Models\WordPressSite;
use Illuminate\Foundation\Http\FormRequest;

class WordPressSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('wordpressSite');

        return $site instanceof WordPressSite
            ? $this->user()->can('update', $site)
            : $this->user()->can('create', WordPressSite::class);
    }

    protected function prepareForValidation(): void
    {
        $url = trim((string) $this->input('rest_api_url'));
        $url = preg_replace('#/wp-json(?:/wp/v2)?/?$#i', '', $url) ?: $url;

        $this->merge([
            'rest_api_url' => rtrim($url, '/'),
            'active' => $this->boolean('active'),
        ]);
    }

    public function rules(): array
    {
        $isCreating = ! $this->route('wordpressSite');

        return [
            'name' => ['required', 'string', 'max:255'],
            'rest_api_url' => ['required', 'url:http,https', 'max:2048'],
            'username' => ['required', 'string', 'max:255'],
            'application_password' => [$isCreating ? 'required' : 'nullable', 'string', 'max:2048'],
            'active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'rest_api_url' => 'dominio',
            'username' => 'usuario de WordPress',
            'application_password' => 'contraseña de aplicación',
            'active' => 'sitio activo',
        ];
    }
}
