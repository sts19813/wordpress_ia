<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Modelos de artículos generados por IA
class AiArticle extends Model
{
    public const STATUS_PENDING = 'pending_generation';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'source_post_ids',
        'user_id',
        'ai_prompt_profile_id',
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
        'writing_style',
        'tone',
        'content_length',
        'language',
        'audience',
        'max_output_tokens',
        'status',
        'generation_error',
        'generated_at',
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
            'max_output_tokens' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente de generación',
            self::STATUS_GENERATED => 'Generado',
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_FAILED => 'Fallido',
        ];
    }

    public function sourcePosts(): EloquentCollection
    {
        return SourcePost::query()
            ->whereIn('id', $this->source_post_ids ?: [])
            ->get();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promptProfile(): BelongsTo
    {
        return $this->belongsTo(AiPromptProfile::class, 'ai_prompt_profile_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(AiImage::class);
    }

    public function mainImage(): ?AiImage
    {
        return $this->images->firstWhere('type', AiImage::TYPE_MAIN);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }
}
