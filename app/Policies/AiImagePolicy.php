<?php

namespace App\Policies;

use App\Models\AiImage;
use App\Models\User;

class AiImagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AiImage $image): bool
    {
        return $image->article?->user_id === $user->id;
    }
}
