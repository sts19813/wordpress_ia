<?php

namespace App\Jobs;

use App\Models\AiArticle;
use App\Models\Publication;
use App\Models\Scheduler;
use App\Models\WordPressSite;
use App\Services\PublicationService;
use App\Services\SchedulerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class PublishQuickPost implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $taskId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function uniqueId(): string
    {
        return (string) $this->taskId;
    }

    public function handle(PublicationService $publications, SchedulerService $scheduler): void
    {
        $task = Scheduler::query()
            ->with(['user', 'article.images'])
            ->findOrFail($this->taskId);

        if ($task->status === Scheduler::STATUS_COMPLETED) {
            return;
        }

        try {
            $this->publish($task, $publications, $scheduler);
        } catch (Throwable $exception) {
            $this->handleFailure($task, $scheduler, $exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $task = Scheduler::query()->find($this->taskId);

        if ($task && $task->status !== Scheduler::STATUS_COMPLETED) {
            app(SchedulerService::class)->failed(
                $task,
                $exception?->getMessage() ?: 'La publicación agotó sus reintentos.',
            );
        }
    }

    private function publish(
        Scheduler $task,
        PublicationService $publications,
        SchedulerService $scheduler,
    ): void {
        $user = $task->user;
        $article = $task->article;
        $profileIds = array_values(array_unique(array_map(
            'intval',
            $task->payload['publication_profile_ids'] ?? [],
        )));

        if (! $user || ! $article || $article->status !== AiArticle::STATUS_DRAFT) {
            throw new RuntimeException('El borrador no está disponible para publicar.');
        }

        if ($profileIds === []) {
            $scheduler->completed($task, 'El post quedó guardado como borrador.');

            return;
        }

        $profiles = $user->wordpressSites()
            ->whereIn('id', $profileIds)
            ->where('active', true)
            ->where('status', WordPressSite::STATUS_ACTIVE)
            ->get()
            ->sortBy(fn (WordPressSite $profile) => $profile->isSocial() ? 1 : 0)
            ->values();
        $unavailableCount = count($profileIds) - $profiles->count();

        $scheduler->running(
            $task,
            'Publicando el post en los destinos seleccionados',
            92,
            max(1, $this->attempts()),
        );

        $successful = collect();
        $failed = collect();
        $image = $article->mainImage();

        foreach ($profiles as $profile) {
            $existing = Publication::query()
                ->where('wordpress_site_id', $profile->id)
                ->where('ai_article_id', $article->id)
                ->where('status', Publication::STATUS_PUBLISHED)
                ->latest('id')
                ->first();

            if ($existing) {
                $successful->push($existing);

                continue;
            }

            $scheduler->progress(
                $task,
                'Publicando en '.$profile->name,
                min(99, 92 + $successful->count() + $failed->count()),
                'Enviando el post a '.$profile->typeLabel().' “'.$profile->name.'”.',
            );
            $publication = $publications->publishNow($profile, $article, $image);
            $task->update(['publication_id' => $publication->id]);

            if ($publication->isSuccessful()) {
                $successful->push($publication);
            } else {
                $failed->push($publication);
                $scheduler->addEvent(
                    $task,
                    'warning',
                    $profile->name.': '.($publication->error_message ?: 'el destino no confirmó la publicación.'),
                );
            }
        }

        $failedCount = $failed->count() + $unavailableCount;

        if ($successful->isEmpty() && $failedCount > 0) {
            $scheduler->failed(
                $task,
                $failedCount === 1
                    ? 'No fue posible publicar en el perfil seleccionado. El borrador quedó guardado.'
                    : "No fue posible publicar en los {$failedCount} perfiles seleccionados. El borrador quedó guardado.",
            );

            return;
        }

        $message = $successful->count() === 1
            ? 'Post publicado correctamente en 1 perfil.'
            : 'Post publicado correctamente en '.$successful->count().' perfiles.';

        if ($failedCount > 0) {
            $message .= " {$failedCount} destino(s) requieren revisión en Publicaciones.";
        }

        $scheduler->completed($task, $message);
    }

    private function handleFailure(Scheduler $task, SchedulerService $scheduler, Throwable $exception): void
    {
        if (config('queue.default') === 'sync') {
            $scheduler->failed($task, $exception->getMessage());

            return;
        }

        if ($this->attempts() < $this->tries) {
            $scheduler->retrying($task, $exception->getMessage());
        }

        throw $exception;
    }
}
