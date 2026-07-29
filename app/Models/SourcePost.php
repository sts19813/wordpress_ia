<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Modelos de publicaciones generadas por IA.
class SourcePost extends Model
{
    public const ORIGIN_SOURCE_SITE = 'source_site';

    public const ORIGIN_QUICK_POST = 'quick_post';

    public const STATUS_FETCHED = 'fetched';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_DISCARDED = 'discarded';

    protected $fillable = [
        'source_site_id',
        'origin_type',
        'social_platform',
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
        'canonical_url',
        'hash',
        'status',
        'original_json',
        'language',
        'filter_applies',
        'filter_reason',
        'matched_topics',
        'filter_method',
        'scanned_at',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'categories' => 'array',
            'tags' => 'array',
            'original_json' => 'array',
            'filter_applies' => 'boolean',
            'matched_topics' => 'array',
            'scanned_at' => 'datetime',
            'captured_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (SourcePost $sourcePost): void {
            $sourcePost->media()->get()->each->delete();
        });
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

    public function media(): HasMany
    {
        return $this->hasMany(SourcePostMedia::class)->orderBy('position');
    }

    public function isQuickPost(): bool
    {
        return $this->origin_type === self::ORIGIN_QUICK_POST;
    }

    public function originLabel(): string
    {
        if (! $this->isQuickPost()) {
            return $this->sourceSite?->name ?: 'Sitio fuente';
        }

        return match ($this->social_platform) {
            'facebook' => 'Facebook',
            'x' => 'X',
            'instagram' => 'Instagram',
            default => 'Post rápido',
        };
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }
}
