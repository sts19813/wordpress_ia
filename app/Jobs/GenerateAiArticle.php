<?php

namespace App\Jobs;

use App\Models\AiArticle;
use App\Models\Scheduler;
use App\Models\SourcePost;
use App\Services\AiArticleService;
use App\Services\QuickPosts\OriginalPostImageService;
use App\Services\SchedulerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class GenerateAiArticle implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 240;

    public int $uniqueFor = 600;

    private bool $dispatchImage = true;

    private bool $dispatchPublication = true;

    public function __construct(public readonly int $taskId) {}

    public function withoutImageDispatch(): self
    {
        $this->dispatchImage = false;

        return $this;
    }

    public function withoutPublicationDispatch(): self
    {
        $this->dispatchPublication = false;

        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function uniqueId(): string
    {
        return (string) $this->taskId;
    }

    public function handle(
        AiArticleService $articles,
        SchedulerService $scheduler,
        OriginalPostImageService $originalImages,
    ): void {
        $lock = Cache::lock("scheduler:generate-ai-article:{$this->taskId}", $this->timeout + 60);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->handleLocked($articles, $scheduler, $originalImages);
        } finally {
            $lock->release();
        }
    }

    private function handleLocked(
        AiArticleService $articles,
        SchedulerService $scheduler,
        OriginalPostImageService $originalImages,
    ): void {
        $task = Scheduler::query()->with('article')->findOrFail($this->taskId);

        if ($task->status === Scheduler::STATUS_COMPLETED) {
            return;
        }

        $payload = $task->payload ?: [];
        $profile = $task->user?->aiPromptProfiles()->findOrFail($payload['profile_id'] ?? null);

        if ($task->article && array_key_exists('company_id', $payload)) {
            $task->article->update(['company_id' => $payload['company_id']]);
        }

        if ($task->article?->status === AiArticle::STATUS_DRAFT) {
            $this->completeImageChoice($task, $task->article, $scheduler, $originalImages, $profile->generate_image);

            return;
        }

        if (in_array($task->article?->status, [AiArticle::STATUS_PENDING, AiArticle::STATUS_FAILED], true)) {
            $task->article->delete();
            $task->update(['ai_article_id' => null]);
        }

        $attempt = max(1, $this->attempts());
        $scheduler->running($task, 'Analizando fuentes y redactando el artículo', 15, $attempt);

        $sourceIds = array_map('intval', $payload['source_post_ids'] ?? []);
        $sourcePosts = SourcePost::query()
            ->whereIn('id', $sourceIds)
            ->where('status', SourcePost::STATUS_FETCHED)
            ->get();

        if ($sourcePosts->count() !== count(array_unique($sourceIds))) {
            throw new RuntimeException('Una o más noticias seleccionadas ya no están disponibles.');
        }

        $article = $articles->generateTextDraft(
            $task->user,
            $profile,
            $sourcePosts,
            function (AiArticle $pendingArticle) use ($task, $payload): void {
                $pendingArticle->update(['company_id' => $payload['company_id'] ?? null]);
                $task->update(['ai_article_id' => $pendingArticle->id]);
            },
        );
        $task->update(['ai_article_id' => $article->id]);

        if ($article->status === AiArticle::STATUS_FAILED) {
            $this->handleAttemptFailure($task, $scheduler, $article->generation_error ?: 'No fue posible generar el artículo.');

            return;
        }

        $this->completeImageChoice($task, $article, $scheduler, $originalImages, $profile->generate_image);
    }

    public function failed(?Throwable $exception): void
    {
        $task = Scheduler::query()->find($this->taskId);

        if ($task && $task->status !== Scheduler::STATUS_COMPLETED) {
            app(SchedulerService::class)->failed(
                $task,
                $exception?->getMessage() ?: 'La generación del artículo agotó sus reintentos.',
            );
        }
    }

    private function handleAttemptFailure(Scheduler $task, SchedulerService $scheduler, string $message): void
    {
        if (config('queue.default') === 'sync') {
            $scheduler->failed($task, $message);

            return;
        }

        if ($this->attempts() < $this->tries) {
            $scheduler->retrying($task, $message);
        }

        throw new RuntimeException($message);
    }

    private function completeImageChoice(
        Scheduler $task,
        AiArticle $article,
        SchedulerService $scheduler,
        OriginalPostImageService $originalImages,
        bool $profileGeneratesImage,
    ): void {
        $payload = $task->payload ?: [];

        if (($payload['image_mode'] ?? null) === 'original') {
            $sourcePost = SourcePost::query()
                ->whereIn('id', array_map('intval', $payload['source_post_ids'] ?? []))
                ->where('origin_type', SourcePost::ORIGIN_QUICK_POST)
                ->with('media')
                ->first();

            if (! $sourcePost) {
                throw new RuntimeException('La publicación original no está disponible para conservar sus imágenes.');
            }

            $count = $originalImages->attach($article, $sourcePost);
            $scheduler->completeOrPublish(
                $task,
                $article,
                "El borrador quedó listo con {$count} imagen(es) originales conservadas para su publicación.",
                $this->dispatchPublication,
            );

            return;
        }

        if ((bool) ($payload['generate_image'] ?? $profileGeneratesImage)) {
            $scheduler->awaitingImage($task, $article, $this->dispatchImage);

            return;
        }

        $scheduler->completeOrPublish(
            $task,
            $article,
            'El borrador de texto quedó listo.',
            $this->dispatchPublication,
        );
    }
}
