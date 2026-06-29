<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publication extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_DELETED = 'deleted';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'wordpress_site_id',
        'ai_article_id',
        'ai_image_id',
        'remote_post_id',
        'remote_featured_media_id',
        'remote_url',
        'status',
        'scheduled_at',
        'last_action',
        'request_payload',
        'full_response',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'request_payload' => 'array',
            'full_response' => 'array',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_PUBLISHED => 'Publicado',
            self::STATUS_SCHEDULED => 'Programado',
            self::STATUS_DELETED => 'Eliminado',
            self::STATUS_FAILED => 'Fallido',
        ];
    }

    public function wordpressSite(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class);
    }

    public function aiArticle(): BelongsTo
    {
        return $this->belongsTo(AiArticle::class);
    }

    public function aiImage(): BelongsTo
    {
        return $this->belongsTo(AiImage::class);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }
}
