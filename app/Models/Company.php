<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicationProfiles(): HasMany
    {
        return $this->hasMany(WordPressSite::class);
    }

    public function sourceSites(): HasMany
    {
        return $this->hasMany(SourceSite::class);
    }

    public function aiArticles(): HasMany
    {
        return $this->hasMany(AiArticle::class);
    }
}
