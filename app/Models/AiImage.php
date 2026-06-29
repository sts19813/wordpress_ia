<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiImage extends Model
{
    public const TYPE_MAIN = 'main';

    public const TYPE_VARIANT = 'variant';

    public const TYPE_THUMBNAIL = 'thumbnail';

    public const TYPE_BANNER = 'banner';

    public const TYPE_OG = 'og';

    public const STATUS_PENDING = 'pending_generation';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type',
        'title',
        'prompt',
        'seed',
        'model',
        'cost',
        'duration_ms',
        'resolution',
        'status',
        'source_context',
        'full_response',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'cost' => 'decimal:6',
            'duration_ms' => 'integer',
            'source_context' => 'array',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_MAIN => 'Imagen principal',
            self::TYPE_VARIANT => 'Variante',
            self::TYPE_THUMBNAIL => 'Miniatura',
            self::TYPE_BANNER => 'Banner',
            self::TYPE_OG => 'Imagen OG',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente de generación',
            self::STATUS_GENERATED => 'Generada',
            self::STATUS_FAILED => 'Fallida',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }
}
