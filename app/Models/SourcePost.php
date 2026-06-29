<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


//Modelos de publicaciones generadas por IA.
class SourcePost extends Model
{
    public const STATUS_FETCHED = 'fetched';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_DISCARDED = 'discarded';

    protected $fillable = [
        'source_site_id',
        'title',
        'content',
        'content_html',
        'summary',
        'author',
        'published_at',
        'image_url',
        'categories',
        'tags',
        'url',
        'hash',
        'status',
        'original_json',
        'language',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'categories' => 'array',
            'tags' => 'array',
            'original_json' => 'array',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_FETCHED => 'Obtenida',
            self::STATUS_DUPLICATE => 'Duplicada',
            self::STATUS_DISCARDED => 'Descartada',
        ];
    }

    public function sourceSite(): BelongsTo
    {
        return $this->belongsTo(SourceSite::class);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }
}
