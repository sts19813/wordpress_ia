<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class AiArticle extends Model
{
    public const STATUS_PENDING = 'pending_generation';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'source_post_ids',
        'title',
        'content',
        'excerpt',
        'meta_description',
        'slug',
        'categories',
        'tags',
        'seo_keywords',
        'faqs',
        'conclusion',
        'prompt_used',
        'full_response',
        'tokens',
        'cost',
        'duration_ms',
        'model',
        'temperature',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'source_post_ids' => 'array',
            'categories' => 'array',
            'tags' => 'array',
            'seo_keywords' => 'array',
            'faqs' => 'array',
            'tokens' => 'array',
            'cost' => 'decimal:6',
            'temperature' => 'decimal:2',
            'duration_ms' => 'integer',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente de generación',
            self::STATUS_GENERATED => 'Generado',
            self::STATUS_FAILED => 'Fallido',
        ];
    }

    public function sourcePosts(): EloquentCollection
    {
        return SourcePost::query()
            ->whereIn('id', $this->source_post_ids ?: [])
            ->get();
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }
}
