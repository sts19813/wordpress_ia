<?php

namespace App\Http\Requests;

use App\Models\SourceSite;
use App\Models\WordPressSite;
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
        $sourceSite = $this->route('sourceSite');
        $companyId = filled($this->input('company_id')) ? (int) $this->input('company_id') : null;
        $publicationSchedulesInput = (array) $this->input('publication_schedules', []);
        $dailyTarget = min(100, max(1, (int) $this->input(
            'daily_publication_target',
            $sourceSite?->dailyPublicationTarget() ?: 5,
        )));
        $priorityTime = (string) $this->input(
            'publication_priority_time',
            $sourceSite?->publicationPriorityTime() ?: '08:00',
        );

        if ($companyId) {
            $publicationProfileIds = WordPressSite::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->where('status', WordPressSite::STATUS_ACTIVE)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn (mixed $id) => (int) $id)
                ->all();
            $publicationSchedules = collect($publicationProfileIds)
                ->mapWithKeys(fn (int $profileId) => [$profileId => [
                    'daily_target' => $dailyTarget,
                    'priority_time' => $priorityTime,
                ]])
                ->all();
        } else {
            if ($publicationSchedulesInput === [] && $this->filled('publication_profile_ids')) {
                $legacyDailyTarget = max(1, (int) $this->input('max_generations_per_scan', 5));
                $publicationSchedulesInput = collect((array) $this->input('publication_profile_ids'))
                    ->mapWithKeys(fn (mixed $profileId) => [(int) $profileId => [
                        'enabled' => true,
                        'daily_target' => $legacyDailyTarget,
                        'priority_time' => '00:00',
                    ]])
                    ->all();
            }

            $publicationSchedules = collect($publicationSchedulesInput)
                ->filter(fn (mixed $schedule) => is_array($schedule) && filter_var($schedule['enabled'] ?? false, FILTER_VALIDATE_BOOL))
                ->mapWithKeys(fn (array $schedule, mixed $profileId) => [(int) $profileId => [
                    'daily_target' => (int) ($schedule['daily_target'] ?? 1),
                    'priority_time' => (string) ($schedule['priority_time'] ?? '08:00'),
                ]])
                ->all();
            $publicationProfileIds = array_map('intval', array_keys($publicationSchedules));
            $dailyTarget = max([1, ...array_column($publicationSchedules, 'daily_target')]);
            $priorityTime = (string) (collect($publicationSchedules)->pluck('priority_time')->filter()->sort()->first() ?: $priorityTime);
        }

        $this->merge(array_filter([
            'frequency_minutes' => 60,
            'filter_topics' => $this->topicList($this->input('filter_topics')),
            'excluded_topics' => $this->topicList($this->input('excluded_topics')),
            'status' => $this->input('status', $sourceSite?->status ?: SourceSite::STATUS_PENDING),
            'language' => $this->input('language', $sourceSite?->language ?: 'es'),
            'priority' => $this->input('priority', $sourceSite?->priority ?: 5),
            'auth_method' => $this->input('auth_method', $sourceSite?->auth_method ?: SourceSite::AUTH_NONE),
            'active' => $this->has('active') ? $this->boolean('active') : ($sourceSite?->active ?? true),
            'auto_generate' => $publicationSchedules !== [],
            'auto_publish' => $publicationSchedules !== [],
            'company_id' => $companyId,
            'publication_profile_ids' => $publicationProfileIds,
            'publication_schedules' => $publicationSchedules,
            'daily_publication_target' => $dailyTarget,
            'publication_priority_time' => $priorityTime,
            'daily_limit' => max(50, $dailyTarget * 10),
            'max_posts_per_scan' => min(100, max(20, $dailyTarget * 5)),
            'max_generations_per_scan' => $dailyTarget,
        ], fn (mixed $value) => $value !== null));
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
            'frequency_minutes' => ['required', 'integer', 'min:60', 'max:60'],
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
            'max_posts_per_scan' => ['required', 'integer', 'min:1', 'max:1000'],
            'max_generations_per_scan' => ['required', 'integer', 'min:1', 'max:1000'],
            'last_synced_at' => ['nullable', 'date'],
            'active' => ['boolean'],
            'auto_generate' => ['boolean'],
            'auto_publish' => ['boolean'],
            'ai_prompt_profile_id' => [
                'nullable',
                'required_if:auto_generate,1',
                'integer',
                Rule::exists('ai_prompt_profiles', 'id'),
            ],
            'company_id' => [
                'nullable',
                'integer',
                Rule::exists('companies', 'id'),
            ],
            'daily_publication_target' => ['required', 'integer', 'min:1', 'max:100'],
            'publication_priority_time' => ['required', 'date_format:H:i'],
            'publication_profile_ids' => ['nullable', 'array'],
            'publication_profile_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('wordpress_sites', 'id')->where(fn ($query) => $query
                    ->where('active', true)
                    ->where('status', 'active')),
            ],
            'publication_schedules' => ['nullable', 'array'],
            'publication_schedules.*.daily_target' => ['required', 'integer', 'min:1', 'max:100'],
            'publication_schedules.*.priority_time' => ['required', 'date_format:H:i'],
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
            'daily_publication_target' => 'artículos a generar por día',
            'publication_priority_time' => 'hora de inicio de publicaciones',
            'publication_profile_ids' => 'perfiles de publicación',
            'publication_profile_ids.*' => 'perfil de publicación',
            'publication_schedules' => 'programación de publicación',
            'publication_schedules.*.daily_target' => 'posts deseados al día',
            'publication_schedules.*.priority_time' => 'hora de prioridad',
        ];
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
