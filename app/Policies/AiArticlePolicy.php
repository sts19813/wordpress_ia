<?php

namespace App\Policies;

use App\Models\AiArticle;
use App\Models\User;

class AiArticlePolicy
{
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
        return $article->user_id === $user->id;
    }

    public function update(User $user, AiArticle $article): bool
    {
        return $this->view($user, $article);
    }

    public function delete(User $user, AiArticle $article): bool
    {
        return $this->view($user, $article);
    }
}
