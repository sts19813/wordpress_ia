<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SourceSite extends Model
{
    use SoftDeletes;

    public const TYPE_WORDPRESS_REST = 'wordpress_rest';

    public const TYPE_RSS = 'rss';

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
            self::TYPE_WORDPRESS_REST => 'WordPress REST API',
            self::TYPE_RSS => 'RSS',
            self::TYPE_HTML => 'Sitio HTML para scraping',
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
}
