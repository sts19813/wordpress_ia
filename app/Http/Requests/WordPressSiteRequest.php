<?php

namespace App\Http\Requests;

use App\Models\WordPressSite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $type = (string) $this->input('type', WordPressSite::TYPE_WORDPRESS);
        $url = trim((string) $this->input('rest_api_url'));
        $url = preg_replace('#/wp-json(?:/wp/v2)?/?$#i', '', $url) ?: $url;

        $this->merge([
            'type' => $type,
            'rest_api_url' => rtrim($url, '/'),
            'facebook_page_id' => trim((string) $this->input('facebook_page_id')),
            'facebook_api_version' => trim((string) $this->input('facebook_api_version', 'v24.0')),
            'instagram_account_id' => trim((string) $this->input('instagram_account_id')),
            'instagram_api_version' => trim((string) $this->input('instagram_api_version', 'v24.0')),
            'x_username' => ltrim(trim((string) $this->input('x_username')), '@'),
            'x_client_id' => trim((string) $this->input('x_client_id')),
            'active' => $this->boolean('active'),
        ]);
    }

    public function rules(): array
    {
        $isCreating = ! $this->route('wordpressSite');
        $storedProfile = $this->route('wordpressSite');
        $isWordPress = $this->input('type') === WordPressSite::TYPE_WORDPRESS;
        $isFacebook = $this->input('type') === WordPressSite::TYPE_FACEBOOK_PAGE;
        $isInstagram = $this->input('type') === WordPressSite::TYPE_INSTAGRAM;
        $isX = $this->input('type') === WordPressSite::TYPE_X;
        $requiresWordPressPassword = $isWordPress
            && ($isCreating || ! ($storedProfile instanceof WordPressSite) || blank($storedProfile->application_password));
        $requiresFacebookToken = $isFacebook
            && ($isCreating || ! ($storedProfile instanceof WordPressSite) || blank($storedProfile->facebook_access_token));
        $requiresInstagramToken = $isInstagram
            && ($isCreating || ! ($storedProfile instanceof WordPressSite) || blank($storedProfile->instagram_access_token));
        $hasStoredXClient = $storedProfile instanceof WordPressSite
            && filled($storedProfile->x_client_id)
            && filled($storedProfile->x_client_secret);
        $hasSubmittedXToken = filled($this->input('x_access_token'));
        $requiresXClient = $isX && ! $hasSubmittedXToken && ($isCreating || ! $hasStoredXClient);

        return [
            'company_id' => [
                'nullable',
                'integer',
                Rule::exists('companies', 'id')->where('user_id', $this->user()->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(WordPressSite::typeOptions()))],
            'rest_api_url' => [$isWordPress ? 'required' : 'nullable', 'url:http,https', 'max:2048'],
            'username' => [$isWordPress ? 'required' : 'nullable', 'string', 'max:255'],
            'application_password' => [$requiresWordPressPassword ? 'required' : 'nullable', 'string', 'max:2048'],
            'facebook_page_id' => [$isFacebook ? 'required' : 'nullable', 'regex:/^\d+$/', 'max:255'],
            'facebook_access_token' => [$requiresFacebookToken ? 'required' : 'nullable', 'string', 'max:4096'],
            'facebook_api_version' => [$isFacebook ? 'required' : 'nullable', 'regex:/^v\d+\.\d+$/', 'max:20'],
            'instagram_account_id' => [$isInstagram ? 'required' : 'nullable', 'regex:/^\d+$/', 'max:255'],
            'instagram_access_token' => [$requiresInstagramToken ? 'required' : 'nullable', 'string', 'max:4096'],
            'instagram_api_version' => [$isInstagram ? 'required' : 'nullable', 'regex:/^v\d+\.\d+$/', 'max:20'],
            'x_username' => ['nullable', 'regex:/^[A-Za-z0-9_]{1,15}$/'],
            'x_client_id' => [$requiresXClient ? 'required' : 'nullable', 'string', 'max:255'],
            'x_client_secret' => [$requiresXClient ? 'required' : 'nullable', 'string', 'max:4096'],
            'x_access_token' => ['nullable', 'string', 'max:4096'],
            'x_refresh_token' => ['nullable', 'string', 'max:4096'],
            'active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'facebook_page_id.regex' => 'El identificador de la página solo debe contener números.',
            'facebook_api_version.regex' => 'La versión debe tener un formato como v24.0.',
            'instagram_account_id.regex' => 'El identificador de la cuenta de Instagram solo debe contener números.',
            'instagram_api_version.regex' => 'La versión debe tener un formato como v24.0.',
            'x_username.regex' => 'El usuario de X solo puede contener letras, números y guion bajo.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_id' => 'empresa',
            'name' => 'nombre',
            'type' => 'tipo de perfil',
            'rest_api_url' => 'dominio',
            'username' => 'usuario de WordPress',
            'application_password' => 'contraseña de aplicación',
            'facebook_page_id' => 'ID de la página de Facebook',
            'facebook_access_token' => 'Page Access Token',
            'facebook_api_version' => 'versión de Graph API',
            'instagram_account_id' => 'ID de la cuenta profesional de Instagram',
            'instagram_access_token' => 'token de acceso de Instagram',
            'instagram_api_version' => 'versión de Graph API',
            'x_username' => 'usuario de X',
            'x_client_id' => 'Client ID de X',
            'x_client_secret' => 'Client Secret de X',
            'x_access_token' => 'User Access Token de X',
            'x_refresh_token' => 'Refresh Token de X',
            'active' => 'perfil activo',
        ];
    }
}
