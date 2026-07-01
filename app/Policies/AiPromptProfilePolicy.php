<?php

namespace App\Policies;

use App\Models\AiPromptProfile;
use App\Models\User;

class AiPromptProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, AiPromptProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function update(User $user, AiPromptProfile $profile): bool
    {
        return $this->view($user, $profile);
    }

    public function delete(User $user, AiPromptProfile $profile): bool
    {
        return $this->view($user, $profile);
    }
}
