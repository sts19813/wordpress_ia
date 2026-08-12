<?php

namespace App\Policies;

use App\Models\AiPromptProfile;
use App\Models\User;

class AiPromptProfilePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function view(User $user, AiPromptProfile $profile): bool
    {
        return true;
    }

    public function update(User $user, AiPromptProfile $profile): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, AiPromptProfile $profile): bool
    {
        return false;
    }
}
