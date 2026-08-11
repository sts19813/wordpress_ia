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
        $frequencyHours = $this->input('frequency_hours');
        $sourceSite = $this->route('sourceSite');

        $this->merge(array_filter([
            'frequency_minutes' => filled($frequencyHours) ? ((int) $frequencyHours * 60) : null,
            'filter_topics' => $this->topicList($this->input('filter_topics')),
            'excluded_topics' => $this->topicList($this->input('excluded_topics')),
            'status' => $this->input('status', $sourceSite?->status ?: SourceSite::STATUS_PENDING),
            'language' => $this->input('language', $sourceSite?->language ?: 'es'),
            'priority' => $this->input('priority', $sourceSite?->priority ?: 5),
            'auth_method' => $this->input('auth_method', $sourceSite?->auth_method ?: SourceSite::AUTH_NONE),
            'active' => $this->has('active') ? $this->boolean('active') : ($sourceSite?->active ?? true),
            'auto_generate' => $this->has('auto_generate') ? $this->boolean('auto_generate') : ($sourceSite?->auto_generate ?? true),
            'auto_publish' => $this->has('auto_publish') ? $this->boolean('auto_publish') : ($sourceSite?->auto_publish ?? false),
            'company_id' => filled($this->input('company_id')) ? (int) $this->input('company_id') : null,
            'publication_profile_ids' => array_values(array_unique(array_filter(array_map(
                'intval',
                (array) $this->input('publication_profile_ids', []),
            )))),
            'max_posts_per_scan' => $this->input('max_posts_per_scan', $sourceSite?->max_posts_per_scan ?: 20),
            'max_generations_per_scan' => $this->input('max_generations_per_scan', $sourceSite?->max_generations_per_scan ?: 5),
        ], fn (mixed $value) => $value !== null));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ownerId = $this->ownerId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'type' => ['required', Rule::in(array_keys(SourceSite::typeOptions()))],
            'status' => ['required', Rule::in(array_keys(SourceSite::statusOptions()))],
            'frequency_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'frequency_minutes' => ['required', 'integer', 'min:60', 'max:10080'],
            'category' => ['nullable', 'string', 'max:120'],
            'filter_topics' => ['nullable', 'array', 'max:30'],
            'filter_topics.*' => ['string', 'max:120'],
            'excluded_topics' => ['nullable', 'array', 'max:30'],
            'excluded_topics.*' => ['string', 'max:120'],
            'filter_instructions' => ['nullable', 'string', 'max:3000'],
            'language' => ['required', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', 'integer', 'min:1', 'max:10'],
            'api_key' => ['nullable', 'string', 'max:2048'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:2048'],
            'custom_headers' => ['nullable', 'json'],
            'cookies' => ['nullable', 'json'],
            'auth_method' => ['required', Rule::in(array_keys(SourceSite::authMethodOptions()))],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:10000'],
            'max_posts_per_scan' => ['required', 'integer', 'min:1', 'max:1000', 'lte:daily_limit'],
            'max_generations_per_scan' => ['required', 'integer', 'min:1', 'max:1000', 'lte:max_posts_per_scan'],
            'last_synced_at' => ['nullable', 'date'],
            'active' => ['boolean'],
            'auto_generate' => ['boolean'],
            'auto_publish' => ['boolean'],
            'ai_prompt_profile_id' => [
                'nullable',
                'required_if:auto_generate,1',
                'integer',
                Rule::exists('ai_prompt_profiles', 'id')->where('user_id', $ownerId),
            ],
            'company_id' => [
                'nullable',
                'integer',
                Rule::exists('companies', 'id'),
            ],
            'publication_profile_ids' => [
                'nullable',
                'required_if:auto_publish,1',
                'array',
            ],
            'publication_profile_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('wordpress_sites', 'id')->where(fn ($query) => $query
                    ->where('active', true)
                    ->where('status', 'active')),
            ],
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
            'frequency_hours' => 'frecuencia de consulta',
            'filter_topics' => 'temas aceptados',
            'excluded_topics' => 'temas excluidos',
            'filter_instructions' => 'instrucciones del filtro',
            'language' => 'idioma',
            'country' => 'país',
            'priority' => 'prioridad',
            'api_key' => 'API Key',
            'username' => 'usuario',
            'password' => 'password',
            'custom_headers' => 'headers personalizados',
            'cookies' => 'cookies',
            'auth_method' => 'método de autenticación',
            'daily_limit' => 'límite de posts escaneados al día',
            'max_posts_per_scan' => 'máximo de posts por consulta',
            'max_generations_per_scan' => 'máximo de artículos generados por consulta',
            'last_synced_at' => 'última sincronización',
            'active' => 'activo',
            'auto_generate' => 'generación automática',
            'auto_publish' => 'publicación automática',
            'ai_prompt_profile_id' => 'perfil editorial IA',
            'company_id' => 'empresa',
            'publication_profile_ids' => 'perfiles de publicación',
            'publication_profile_ids.*' => 'perfil de publicación',
        ];
    }

    private function ownerId(): int
    {
        $sourceSite = $this->route('sourceSite');

        if ($this->user()->isAdmin() && $sourceSite instanceof SourceSite && $sourceSite->automation_user_id) {
            return (int) $sourceSite->automation_user_id;
        }

        return (int) $this->user()->id;
    }

    /**
     * @return array<int, string>
     */
    private function topicList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }

        return collect(is_iterable($value) ? $value : [])
            ->map(fn (mixed $topic) => trim((string) $topic))
            ->filter()
            ->unique(fn (string $topic) => mb_strtolower($topic))
            ->values()
            ->all();
    }
}
