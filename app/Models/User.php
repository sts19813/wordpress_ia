<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name',
    'email',
    'password',
    'profile_photo_path',
    'google_id',
    'google_avatar_url',
    'email_verified_at',
])]
#[Hidden(['password', 'remember_token'])]

//Modelos de usuarios del sistema.
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function avatarUrl(): string
    {
        if ($this->profile_photo_path) {
            return Storage::disk('public')->url($this->profile_photo_path);
        }

        return $this->google_avatar_url ?: asset('/metronic/assets/media/avatars/blank.png');
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->map(fn(string $word) => mb_substr($word, 0, 1))
            ->take(2)
            ->join('');
    }
}
