<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SystemLog extends Model
{
    public const LEVEL_ERROR = 'error';

    public const LEVEL_SUCCESS = 'success';

    public const EVENT_SYSTEM_ERROR = 'system_error';

    public const EVENT_PUBLICATION_FAILED = 'publication_failed';

    public const EVENT_PUBLICATION_PUBLISHED = 'publication_published';

    public const EVENT_TASK_FAILED = 'task_failed';

    public const EVENT_ARTICLE_FAILED = 'article_failed';

    public const EVENT_IMAGE_FAILED = 'image_failed';

    public const EVENT_CONNECTION_FAILED = 'connection_failed';

    public const EVENT_SOURCE_FAILED = 'source_failed';

    protected $fillable = [
        'user_id',
        'level',
        'event',
        'source',
        'message',
        'context',
        'subject_type',
        'subject_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function levelLabel(): string
    {
        return $this->level === self::LEVEL_SUCCESS ? 'Publicado' : 'Error';
    }
}
