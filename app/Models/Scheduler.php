<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scheduler extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const TYPE_AI_ARTICLE = 'ai_article';

    public const TYPE_SOURCE_SCAN = 'source_scan';

    public const TYPE_SOURCE_ARTICLE = 'source_article';

    public const TYPE_QUICK_POST = 'quick_post';

    protected $fillable = [
        'parent_id',
        'user_id',
        'ai_article_id',
        'source_site_id',
        'source_post_id',
        'publication_id',
        'type',
        'name',
        'status',
        'step',
        'progress',
        'attempts',
        'max_attempts',
        'payload',
        'events',
        'last_error',
        'started_at',
        'finished_at',
        'scheduled_for',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'events' => 'array',
            'progress' => 'integer',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'scheduled_for' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_QUEUED => 'En cola',
            self::STATUS_RUNNING => 'Procesando',
            self::STATUS_COMPLETED => 'Completado',
            self::STATUS_FAILED => 'Con error',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(AiArticle::class, 'ai_article_id');
    }

    public function sourceSite(): BelongsTo
    {
        return $this->belongsTo(SourceSite::class);
    }

    public function sourcePost(): BelongsTo
    {
        return $this->belongsTo(SourcePost::class);
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_SOURCE_SCAN => 'Consulta de fuente',
            self::TYPE_SOURCE_ARTICLE => 'Generación y publicación',
            self::TYPE_QUICK_POST => 'Post rápido',
            default => 'Generación manual',
        };
    }
}
