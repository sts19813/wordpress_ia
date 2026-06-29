<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WordPressSite extends Model
{
    protected $table = 'wordpress_sites';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'name',
        'rest_api_url',
        'username',
        'application_password',
        'categories',
        'tags',
        'status',
        'active',
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
        return $this->hasMany(Publication::class);
    }

    public function endpoint(string $path): string
    {
        return rtrim($this->rest_api_url, '/').'/'.ltrim($path, '/');
    }
}
