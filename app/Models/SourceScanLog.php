<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceScanLog extends Model
{
    public const OUTCOME_ACCEPTED = 'accepted';

    public const OUTCOME_DISCARDED = 'discarded';

    public const OUTCOME_DUPLICATE = 'duplicate';

    public const OUTCOME_INVALID = 'invalid';

    protected $fillable = [
        'source_site_id',
        'source_post_id',
        'title',
        'url',
        'outcome',
        'applies',
        'reason',
        'matched_topics',
        'filter_method',
        'metadata',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'applies' => 'boolean',
            'matched_topics' => 'array',
            'metadata' => 'array',
            'scanned_at' => 'datetime',
        ];
    }

    public static function outcomeOptions(): array
    {
        return [
            self::OUTCOME_ACCEPTED => 'Aplicó',
            self::OUTCOME_DISCARDED => 'No aplicó',
            self::OUTCOME_DUPLICATE => 'Ya escaneada',
            self::OUTCOME_INVALID => 'No interpretable',
        ];
    }

    public function sourceSite(): BelongsTo
    {
        return $this->belongsTo(SourceSite::class);
    }

    public function sourcePost(): BelongsTo
    {
        return $this->belongsTo(SourcePost::class);
    }

    public function outcomeLabel(): string
    {
        return self::outcomeOptions()[$this->outcome] ?? $this->outcome;
    }
}
