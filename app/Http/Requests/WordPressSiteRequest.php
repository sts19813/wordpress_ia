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
            'active' => $this->boolean('active'),
        ]);
    }

    public function rules(): array
    {
        $isCreating = ! $this->route('wordpressSite');
        $storedProfile = $this->route('wordpressSite');
        $isWordPress = $this->input('type') === WordPressSite::TYPE_WORDPRESS;
        $isFacebook = $this->input('type') === WordPressSite::TYPE_FACEBOOK_PAGE;
        $requiresWordPressPassword = $isWordPress
            && ($isCreating || ! ($storedProfile instanceof WordPressSite) || blank($storedProfile->application_password));
        $requiresFacebookToken = $isFacebook
            && ($isCreating || ! ($storedProfile instanceof WordPressSite) || blank($storedProfile->facebook_access_token));

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(WordPressSite::typeOptions()))],
            'rest_api_url' => [$isWordPress ? 'required' : 'nullable', 'url:http,https', 'max:2048'],
            'username' => [$isWordPress ? 'required' : 'nullable', 'string', 'max:255'],
            'application_password' => [$requiresWordPressPassword ? 'required' : 'nullable', 'string', 'max:2048'],
            'facebook_page_id' => [$isFacebook ? 'required' : 'nullable', 'regex:/^\d+$/', 'max:255'],
            'facebook_access_token' => [$requiresFacebookToken ? 'required' : 'nullable', 'string', 'max:4096'],
            'facebook_api_version' => [$isFacebook ? 'required' : 'nullable', 'regex:/^v\d+\.\d+$/', 'max:20'],
            'active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'facebook_page_id.regex' => 'El identificador de la página solo debe contener números.',
            'facebook_api_version.regex' => 'La versión debe tener un formato como v24.0.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'type' => 'tipo de perfil',
            'rest_api_url' => 'dominio',
            'username' => 'usuario de WordPress',
            'application_password' => 'contraseña de aplicación',
            'facebook_page_id' => 'ID de la página de Facebook',
            'facebook_access_token' => 'Page Access Token',
            'facebook_api_version' => 'versión de Graph API',
            'active' => 'perfil activo',
        ];
    }
}
