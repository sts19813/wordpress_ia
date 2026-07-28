<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'last_synced_at',
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
            'api_key' => 'encrypted',
            'password' => 'encrypted',
            'last_synced_at' => 'datetime',
            'active' => 'boolean',
            'frequency_minutes' => 'integer',
            'priority' => 'integer',
            'daily_limit' => 'integer',
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
}
