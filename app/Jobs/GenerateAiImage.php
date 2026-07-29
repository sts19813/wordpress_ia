<?php

namespace App\Jobs;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Scheduler;
use App\Services\AiArticleService;
use App\Services\SchedulerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class GenerateAiImage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    private bool $dispatchPublication = true;

    public function __construct(public readonly int $taskId) {}

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
        return [20, 90];
    }

    public function uniqueId(): string
    {
        return (string) $this->taskId;
    }

    public function handle(AiArticleService $articles, SchedulerService $scheduler): void
    {
        $task = Scheduler::query()->with('article.images')->findOrFail($this->taskId);

        if ($task->status === Scheduler::STATUS_COMPLETED) {
            return;
        }

        $article = $task->article;

        if (! $article || $article->status !== AiArticle::STATUS_DRAFT) {
            throw new RuntimeException('El borrador no está disponible para generar su imagen.');
        }

        $generatedImage = $article->images
            ->first(fn (AiImage $image) => $image->type === AiImage::TYPE_MAIN && $image->status === AiImage::STATUS_GENERATED);

        if ($generatedImage) {
            $scheduler->completeOrPublish(
                $task,
                $article,
                'El borrador y su imagen principal quedaron listos.',
                $this->dispatchPublication,
            );

            return;
        }

        $article->images()
            ->where('type', AiImage::TYPE_MAIN)
            ->whereIn('status', [AiImage::STATUS_PENDING, AiImage::STATUS_FAILED])
            ->delete();

        $attempt = max(1, $this->attempts());
        $scheduler->running($task, 'Generando la imagen principal', 80, $attempt);
        $profile = $task->user?->aiPromptProfiles()->findOrFail($task->payload['profile_id'] ?? null);
        $image = $articles->generateMainImage($article, $profile);

        if ($image->status === AiImage::STATUS_FAILED) {
            $this->handleAttemptFailure($task, $scheduler, $image->generation_error ?: 'No fue posible generar la imagen.');

            return;
        }

        $scheduler->completeOrPublish(
            $task,
            $article,
            'El borrador y su imagen principal quedaron listos.',
            $this->dispatchPublication,
        );
    }

    public function failed(?Throwable $exception): void
    {
        $task = Scheduler::query()->find($this->taskId);

        if ($task && $task->status !== Scheduler::STATUS_COMPLETED) {
            app(SchedulerService::class)->failed(
                $task,
                'El texto quedó guardado, pero la imagen no se completó: '.($exception?->getMessage() ?: 'se agotaron los reintentos.'),
            );
        }
    }

    private function handleAttemptFailure(Scheduler $task, SchedulerService $scheduler, string $message): void
    {
        if (config('queue.default') === 'sync') {
            $scheduler->failed($task, 'El texto quedó guardado, pero la imagen falló: '.$message);

            return;
        }

        if ($this->attempts() < $this->tries) {
            $scheduler->retrying($task, $message);
        }

        throw new RuntimeException($message);
    }
}
