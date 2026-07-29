<?php

namespace App\Jobs;

use App\Models\AiArticle;
use App\Models\Scheduler;
use App\Models\WordPressSite;
use App\Services\AiArticleService;
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

class GenerateSourceArticle implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public int $uniqueFor = 1200;

    public function __construct(
        public readonly int $taskId,
        public readonly int $sourceSiteId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->taskId;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 180];
    }

    public function handle(
        AiArticleService $articles,
        PublicationService $publications,
        SchedulerService $scheduler,
    ): void {
        $task = Scheduler::query()
            ->with(['user', 'article.images', 'sourcePost', 'publication'])
            ->findOrFail($this->taskId);

        if ($task->status === Scheduler::STATUS_COMPLETED) {
            return;
        }

        try {
            $payload = $task->payload ?: [];
            $user = $task->user;
            $sourcePost = $task->sourcePost;
            $profile = $user?->aiPromptProfiles()->find($payload['profile_id'] ?? null);

            if (! $user || ! $profile) {
                throw new RuntimeException('Configura un usuario y un perfil IA para automatizar esta fuente.');
            }

            if (! $sourcePost) {
                throw new RuntimeException('La noticia fuente ya no está disponible.');
            }

            $article = $task->article;

            if ($article?->status === AiArticle::STATUS_FAILED) {
                $article->delete();
                $task->update(['ai_article_id' => null]);
                $article = null;
            }

            if (! $article || $article->status !== AiArticle::STATUS_DRAFT) {
                $scheduler->running(
                    $task,
                    'Generando el artículo y su imagen con IA',
                    50,
                    max(1, $this->attempts()),
                );
                $article = $articles->generateDraft($user, $profile, [$sourcePost]);
                $task->update(['ai_article_id' => $article->id]);

                if ($article->status !== AiArticle::STATUS_DRAFT) {
                    throw new RuntimeException($article->generation_error ?: 'La IA no pudo generar el artículo.');
                }

                $scheduler->progress(
                    $task,
                    'Artículo generado; preparando publicación',
                    80,
                    'El texto'.($profile->generate_image ? ' y la imagen principal' : '').' quedaron listos.',
                    'success',
                );
            }

            if (! ($payload['auto_publish'] ?? false)) {
                $scheduler->completed($task, 'El artículo quedó guardado como borrador; la publicación automática está desactivada.');

                return;
            }

            $publicationProfile = WordPressSite::query()
                ->whereKey($payload['wordpress_site_id'] ?? null)
                ->where('user_id', $user->id)
                ->where('active', true)
                ->where('status', WordPressSite::STATUS_ACTIVE)
                ->first();

            if (! $publicationProfile) {
                throw new RuntimeException('El perfil de publicación automático no está disponible.');
            }

            $scheduler->progress(
                $task,
                'Publicando en '.$publicationProfile->name,
                90,
                'Se inició el envío del artículo a '.$publicationProfile->typeLabel().'.',
            );
            $publication = $publications->publishNow($publicationProfile, $article->fresh('images'), $article->fresh('images')->mainImage());
            $task->update(['publication_id' => $publication->id]);

            if (! $publication->isSuccessful()) {
                throw new RuntimeException($publication->error_message ?: 'El destino no confirmó la publicación.');
            }

            $scheduler->completed(
                $task,
                'Artículo publicado correctamente'.($publication->remote_url ? ': '.$publication->remote_url : '.'),
            );
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
                $exception?->getMessage() ?: 'La generación y publicación agotó sus reintentos.',
            );
        }
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
