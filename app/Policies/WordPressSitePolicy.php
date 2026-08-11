<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WordPressSite;

class WordPressSitePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, WordPressSite $site): bool
    {
        return $site->user_id === $user->id;
    }

    public function update(User $user, WordPressSite $site): bool
    {
        return $this->view($user, $site);
    }

    public function delete(User $user, WordPressSite $site): bool
    {
        return $this->view($user, $site);
    }
}
