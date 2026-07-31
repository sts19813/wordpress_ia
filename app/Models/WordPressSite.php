<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Modelos de sitios de Wordpress para publicar el contenido generado por la Ia.
class WordPressSite extends Model
{
    protected $table = 'wordpress_sites';

    public const TYPE_WORDPRESS = 'wordpress';

    public const TYPE_FACEBOOK_PAGE = 'facebook_page';

    public const TYPE_INSTAGRAM = 'instagram';

    public const TYPE_X = 'x';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'rest_api_url',
        'username',
        'application_password',
        'facebook_page_id',
        'facebook_access_token',
        'facebook_api_version',
        'instagram_account_id',
        'instagram_access_token',
        'instagram_api_version',
        'x_user_id',
        'x_username',
        'x_client_id',
        'x_client_secret',
        'x_access_token',
        'x_refresh_token',
        'x_token_expires_at',
        'categories',
        'tags',
        'status',
        'active',
        'last_tested_at',
        'connection_error',
    ];

    protected $hidden = [
        'application_password',
        'facebook_access_token',
        'instagram_access_token',
        'x_client_secret',
        'x_access_token',
        'x_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'application_password' => 'encrypted',
            'facebook_access_token' => 'encrypted',
            'instagram_access_token' => 'encrypted',
            'x_client_secret' => 'encrypted',
            'x_access_token' => 'encrypted',
            'x_refresh_token' => 'encrypted',
            'x_token_expires_at' => 'datetime',
            'categories' => 'array',
            'tags' => 'array',
            'active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Activo',
            self::STATUS_PAUSED => 'Pausado',
            self::STATUS_ERROR => 'Con error',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_WORDPRESS => 'Sitio WordPress',
            self::TYPE_FACEBOOK_PAGE => 'Página de Facebook',
            self::TYPE_INSTAGRAM => 'Cuenta de Instagram',
            self::TYPE_X => 'Cuenta de X',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->type] ?? $this->type;
    }

    public function isWordPress(): bool
    {
        return $this->type === self::TYPE_WORDPRESS;
    }

    public function isFacebookPage(): bool
    {
        return $this->type === self::TYPE_FACEBOOK_PAGE;
    }

    public function isInstagram(): bool
    {
        return $this->type === self::TYPE_INSTAGRAM;
    }

    public function isX(): bool
    {
        return $this->type === self::TYPE_X;
    }

    public function isSocial(): bool
    {
        return in_array($this->type, [
            self::TYPE_FACEBOOK_PAGE,
            self::TYPE_INSTAGRAM,
            self::TYPE_X,
        ], true);
    }

    public function destinationLabel(): ?string
    {
        return match ($this->type) {
            self::TYPE_FACEBOOK_PAGE => $this->facebook_page_id ? 'facebook.com/'.$this->facebook_page_id : null,
            self::TYPE_INSTAGRAM => $this->instagram_account_id ? 'instagram.com / '.$this->instagram_account_id : null,
            self::TYPE_X => $this->x_username ? 'x.com/'.ltrim($this->x_username, '@') : null,
            default => $this->rest_api_url,
        };
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class, 'wordpress_site_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function endpoint(string $path): string
    {
        return rtrim($this->rest_api_url, '/').'/'.ltrim($path, '/');
    }
}
