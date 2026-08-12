<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// Modelos de stios fuentes para obtener publicaciones generadas por Ia.
class SourceSite extends Model
{
    use SoftDeletes;

    public const TYPE_AUTO = 'auto';

    public const TYPE_WORDPRESS_REST = 'wordpress_rest';

    public const TYPE_RSS = 'rss';

    public const TYPE_JSON_FEED = 'json_feed';

    public const TYPE_SITEMAP = 'sitemap';

    public const TYPE_HTML = 'html';

    public const TYPE_AI_WEB = 'ai_web';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ERROR = 'error';

    public const AUTH_NONE = 'none';

    public const AUTH_API_KEY = 'api_key';

    public const AUTH_BASIC = 'basic';

    public const AUTH_BEARER = 'bearer';

    public const AUTH_CUSTOM = 'custom';

    protected $fillable = [
        'name',
        'automation_user_id',
        'company_id',
        'ai_prompt_profile_id',
        'wordpress_site_id',
        'publication_profile_ids',
        'publication_schedules',
        'auto_generate',
        'auto_publish',
        'url',
        'type',
        'status',
        'frequency_minutes',
        'category',
        'filter_topics',
        'excluded_topics',
        'filter_instructions',
        'language',
        'country',
        'priority',
        'api_key',
        'username',
        'password',
        'custom_headers',
        'cookies',
        'auth_method',
        'daily_limit',
        'max_posts_per_scan',
        'max_generations_per_scan',
        'last_synced_at',
        'next_scan_at',
        'last_queued_at',
        'active',
    ];

    protected $hidden = [
        'api_key',
        'password',
    ];

    protected function casts(): array
    {
        return [
            'custom_headers' => 'array',
            'cookies' => 'array',
            'filter_topics' => 'array',
            'excluded_topics' => 'array',
            'publication_profile_ids' => 'array',
            'publication_schedules' => 'array',
            'api_key' => 'encrypted',
            'password' => 'encrypted',
            'last_synced_at' => 'datetime',
            'next_scan_at' => 'datetime',
            'last_queued_at' => 'datetime',
            'active' => 'boolean',
            'auto_generate' => 'boolean',
            'auto_publish' => 'boolean',
            'frequency_minutes' => 'integer',
            'priority' => 'integer',
            'daily_limit' => 'integer',
            'max_posts_per_scan' => 'integer',
            'max_generations_per_scan' => 'integer',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_AUTO => 'Detección automática (recomendado)',
            self::TYPE_WORDPRESS_REST => 'WordPress — API REST nativa',
            self::TYPE_RSS => 'Feed RSS o Atom',
            self::TYPE_JSON_FEED => 'JSON Feed',
            self::TYPE_SITEMAP => 'Sitemap XML de publicaciones',
            self::TYPE_HTML => 'Página HTML — datos estructurados o scraping',
            self::TYPE_AI_WEB => 'Navegación y extracción con IA — último recurso',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_ACTIVE => 'Operativo',
            self::STATUS_PAUSED => 'Pausado',
            self::STATUS_ERROR => 'Con error',
        ];
    }

    public static function authMethodOptions(): array
    {
        return [
            self::AUTH_NONE => 'Sin autenticación',
            self::AUTH_API_KEY => 'API Key',
            self::AUTH_BASIC => 'Usuario y password',
            self::AUTH_BEARER => 'Bearer token',
            self::AUTH_CUSTOM => 'Personalizada',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    public function authMethodLabel(): string
    {
        return self::authMethodOptions()[$this->auth_method] ?? $this->auth_method;
    }

    public function sourcePosts(): HasMany
    {
        return $this->hasMany(SourcePost::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(SourceScanLog::class);
    }

    public function automationUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'automation_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function promptProfile(): BelongsTo
    {
        return $this->belongsTo(AiPromptProfile::class, 'ai_prompt_profile_id');
    }

    public function wordpressSite(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class);
    }

    /**
     * @return array<int, int>
     */
    public function selectedPublicationProfileIds(): array
    {
        $scheduledProfileIds = array_keys($this->normalizedPublicationSchedules());

        if ($scheduledProfileIds !== []) {
            return array_map('intval', $scheduledProfileIds);
        }

        $profileIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $this->publication_profile_ids ?: [],
        ))));

        if ($profileIds === [] && $this->wordpress_site_id) {
            return [(int) $this->wordpress_site_id];
        }

        return $profileIds;
    }

    /**
     * @return array<int, array{daily_target: int, priority_time: string}>
     */
    public function normalizedPublicationSchedules(): array
    {
        $schedules = collect($this->publication_schedules ?: [])
            ->mapWithKeys(function (mixed $schedule, mixed $profileId): array {
                $profileId = (int) $profileId;

                if ($profileId < 1 || ! is_array($schedule)) {
                    return [];
                }

                $time = (string) ($schedule['priority_time'] ?? '08:00');

                return [$profileId => [
                    'daily_target' => min(100, max(1, (int) ($schedule['daily_target'] ?? 1))),
                    'priority_time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) ? $time : '08:00',
                ]];
            })
            ->all();

        if ($schedules !== []) {
            return $schedules;
        }

        $legacyProfileIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $this->publication_profile_ids ?: array_filter([$this->wordpress_site_id]),
        ))));

        return collect($legacyProfileIds)->mapWithKeys(fn (int $profileId) => [$profileId => [
            'daily_target' => max(1, (int) ($this->max_generations_per_scan ?: 5)),
            'priority_time' => '00:00',
        ]])->all();
    }

    public function publicationScheduleSummary(): string
    {
        $schedules = $this->normalizedPublicationSchedules();

        if ($schedules === []) {
            return 'Sin destinos';
        }

        $targets = collect($schedules)->pluck('daily_target');
        $targetLabel = $targets->min() === $targets->max()
            ? $targets->first().' por destino'
            : $targets->min().'–'.$targets->max().' por destino';
        $earliest = collect($schedules)->min('priority_time');

        return count($schedules).' destino(s) · '.$targetLabel.'/día desde '.$earliest;
    }

    public function scheduledTasks(): HasMany
    {
        return $this->hasMany(Scheduler::class);
    }
}
