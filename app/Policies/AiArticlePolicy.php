<?php

namespace App\Policies;

use App\Models\AiArticle;
use App\Models\User;

class AiArticlePolicy
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

    public function view(User $user, AiArticle $article): bool
    {
        return true;
    }

    public function update(User $user, AiArticle $article): bool
    {
        return $article->user_id === $user->id;
    }

    public function delete(User $user, AiArticle $article): bool
    {
        return $article->user_id === $user->id;
    }
}
