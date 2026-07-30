<?php

namespace App\Services;

use App\Models\Publication;
use App\Models\SystemLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemLogService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function recordError(
        string $event,
        string $source,
        string $message,
        ?Model $subject = null,
        array $context = [],
        ?int $userId = null,
    ): ?SystemLog {
        return $this->store([
            'user_id' => $userId ?? $this->userIdFrom($subject),
            'level' => SystemLog::LEVEL_ERROR,
            'event' => $event,
            'source' => $source,
            'message' => $message,
            'context' => $context ?: null,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'occurred_at' => now(),
        ]);
    }

    public function recordPublication(Publication $publication): ?SystemLog
    {
        if ($publication->status !== Publication::STATUS_FAILED
            && ! $this->isConfirmedRemotePublication($publication)) {
            return null;
        }

        if ($publication->status === Publication::STATUS_FAILED) {
            $publication->loadMissing(['aiArticle:id,title', 'wordpressSite:id,name']);

            return $this->recordError(
                SystemLog::EVENT_PUBLICATION_FAILED,
                'Publicaciones',
                $publication->error_message ?: 'El destino rechazó la publicación.',
                $publication,
                [
                    'article' => $publication->aiArticle?->title,
                    'profile' => $publication->wordpressSite?->name,
                ],
                $publication->user_id,
            );
        }

        $publication->loadMissing(['aiArticle:id,title', 'wordpressSite:id,name']);

        $article = $publication->aiArticle?->title ?: 'Artículo sin título';
        $profile = $publication->wordpressSite?->name ?: 'perfil externo';

        return $this->store([
            'user_id' => $publication->user_id,
            'level' => SystemLog::LEVEL_SUCCESS,
            'event' => SystemLog::EVENT_PUBLICATION_PUBLISHED,
            'source' => 'Publicaciones',
            'message' => "“{$article}” se publicó en {$profile}.",
            'context' => [
                'article' => $article,
                'profile' => $profile,
                'remote_url' => $publication->remote_url,
            ],
            'subject_type' => $publication->getMorphClass(),
            'subject_id' => $publication->getKey(),
            'occurred_at' => $publication->published_at ?: now(),
        ]);
    }

    public function recordException(Throwable $exception): ?SystemLog
    {
        $context = [
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        try {
            if (app()->bound('request')) {
                $request = request();
                $context['method'] = $request->method();
                $context['url'] = $request->url();
                $context['route'] = $request->route()?->getName();
            }
        } catch (Throwable) {
            // Request context is optional when the application is already failing.
        }

        return $this->recordError(
            SystemLog::EVENT_SYSTEM_ERROR,
            'Sistema',
            $exception->getMessage() ?: class_basename($exception),
            context: $context,
            userId: $this->authenticatedUserId(),
        );
    }

    private function isConfirmedRemotePublication(Publication $publication): bool
    {
        return $publication->status === Publication::STATUS_PUBLISHED
            && filled($publication->published_at)
            && (filled($publication->remote_url)
                || filled($publication->remote_post_id)
                || filled($publication->remote_post_key));
    }

    /**
     * Logging must never interrupt the process it is observing.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function store(array $attributes): ?SystemLog
    {
        try {
            if (! Schema::hasColumns('system_logs', ['level', 'event', 'message', 'occurred_at'])) {
                return null;
            }

            return SystemLog::query()->create($attributes);
        } catch (Throwable) {
            return null;
        }
    }

    private function userIdFrom(?Model $subject): ?int
    {
        $subjectUserId = $subject?->getAttribute('user_id');

        return $subjectUserId ? (int) $subjectUserId : $this->authenticatedUserId();
    }

    private function authenticatedUserId(): ?int
    {
        try {
            return auth()->id();
        } catch (Throwable) {
            return null;
        }
    }
}
