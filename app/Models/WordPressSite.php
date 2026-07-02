<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Modelos de sitios de Wordpress para publicar el contenido generado por la Ia.
class WordPressSite extends Model
{
    protected $table = 'wordpress_sites';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'user_id',
        'name',
        'rest_api_url',
        'username',
        'application_password',
        'categories',
        'tags',
        'status',
        'active',
        'last_tested_at',
        'connection_error',
    ];

    protected $hidden = [
        'application_password',
    ];

    protected function casts(): array
    {
        return [
            'application_password' => 'encrypted',
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
