<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpFeedback extends Model
{
    public const RATING_HELPFUL = 'helpful';

    public const RATING_NOT_HELPFUL = 'not_helpful';

    protected $fillable = [
        'user_id',
        'article_slug',
        'rating',
        'search_term',
        'message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
