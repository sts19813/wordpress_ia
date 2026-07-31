<?php

namespace App\Jobs;

use App\Models\AiArticle;
use App\Models\Publication;
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

            $profileIds = array_values(array_unique(array_filter(array_map(
                'intval',
                $payload['publication_profile_ids']
                    ?? array_values(array_filter([$payload['wordpress_site_id'] ?? null])),
            ))));
            $publicationProfiles = WordPressSite::query()
                ->whereIn('id', $profileIds)
                ->where('user_id', $user->id)
                ->where('active', true)
                ->where('status', WordPressSite::STATUS_ACTIVE)
                ->get()
                ->sortBy(fn (WordPressSite $profile) => $profile->isSocial() ? 1 : 0)
                ->values();

            if ($profileIds === [] || $publicationProfiles->isEmpty()) {
                throw new RuntimeException('Los perfiles de publicación automática no están disponibles.');
            }

            $scheduler->progress(
                $task,
                'Publicando el artículo en los destinos seleccionados',
                90,
                count($profileIds) === 1
                    ? 'Se inició el envío al perfil de publicación seleccionado.'
                    : 'Se inició el envío a '.count($profileIds).' perfiles de publicación.',
            );
            $successful = collect();
            $failed = collect();
            $article->load('images');
            $image = $article->mainImage();

            foreach ($publicationProfiles as $publicationProfile) {
                $existing = Publication::query()
                    ->where('wordpress_site_id', $publicationProfile->id)
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
                    'Publicando en '.$publicationProfile->name,
                    min(99, 91 + $successful->count() + $failed->count()),
                    'Enviando el artículo a '.$publicationProfile->typeLabel().' “'.$publicationProfile->name.'”.',
                );
                $publication = $publications->publishNow($publicationProfile, $article, $image);
                $task->update(['publication_id' => $publication->id]);

                if ($publication->isSuccessful()) {
                    $successful->push($publication);
                } else {
                    $failed->push($publication);
                    $scheduler->addEvent(
                        $task,
                        'warning',
                        $publicationProfile->name.': '.($publication->error_message ?: 'el destino no confirmó la publicación.'),
                    );
                }
            }

            $failedCount = $failed->count() + (count($profileIds) - $publicationProfiles->count());

            if ($successful->isEmpty()) {
                throw new RuntimeException(
                    $failedCount === 1
                        ? 'No fue posible publicar en el perfil seleccionado. El borrador quedó guardado.'
                        : "No fue posible publicar en los {$failedCount} perfiles seleccionados. El borrador quedó guardado.",
                );
            }

            $message = $successful->count() === 1
                ? 'Artículo publicado correctamente en 1 perfil.'
                : 'Artículo publicado correctamente en '.$successful->count().' perfiles.';

            if ($failedCount > 0) {
                $message .= " {$failedCount} destino(s) requieren revisión en Publicaciones.";
            }

            $scheduler->completed(
                $task,
                $message,
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
