<?php

namespace App\Policies;

use App\Models\Publication;
use App\Models\User;

class PublicationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Publication $publication): bool
    {
        return $publication->user_id === $user->id
            || $publication->wordpressSite?->user_id === $user->id;
    }

    public function update(User $user, Publication $publication): bool
    {
        return $this->view($user, $publication);
    }
}
