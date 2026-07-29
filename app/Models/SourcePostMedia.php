<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SourcePostMedia extends Model
{
    protected $table = 'source_post_media';

    protected $fillable = [
        'source_post_id',
        'type',
        'position',
        'original_url',
        'url_hash',
        'file_path',
        'mime_type',
        'width',
        'height',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (SourcePostMedia $media): void {
            if ($media->file_path) {
                Storage::disk('local')->delete($media->file_path);
            }
        });
    }

    public function sourcePost(): BelongsTo
    {
        return $this->belongsTo(SourcePost::class);
    }
}
