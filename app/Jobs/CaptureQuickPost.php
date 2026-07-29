<?php

namespace App\Jobs;

use App\Models\Scheduler;
use App\Services\QuickPosts\SocialPostCaptureService;
use App\Services\SchedulerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class CaptureQuickPost implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 300;

    private bool $dispatchArticle = true;

    public function __construct(public readonly int $taskId) {}

    public function withoutArticleDispatch(): self
    {
        $this->dispatchArticle = false;

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

    public function handle(SocialPostCaptureService $capture, SchedulerService $scheduler): void
    {
        $task = Scheduler::query()->with(['sourcePost', 'article'])->findOrFail($this->taskId);

        if ($task->status === Scheduler::STATUS_COMPLETED) {
            return;
        }

        if ($task->sourcePost) {
            $this->queueArticle($task, $scheduler);

            return;
        }

        $payload = $task->payload ?: [];
        $url = trim((string) ($payload['url'] ?? ''));

        if ($url === '') {
            throw new RuntimeException('La tarea no contiene la URL de la publicación original.');
        }

        $scheduler->running(
            $task,
            'Abriendo y archivando la publicación original',
            10,
            max(1, $this->attempts()),
        );

        try {
            $sourcePost = $capture->capture($url);
            $task->update([
                'source_post_id' => $sourcePost->id,
                'payload' => [
                    ...$payload,
                    'source_post_ids' => [$sourcePost->id],
                ],
            ]);
            $scheduler->progress(
                $task,
                'Original archivado; preparando recreación con IA',
                35,
                "Se guardaron el texto y {$sourcePost->media->count()} imagen(es) originales.",
                'success',
            );
            $this->queueArticle($task->fresh(), $scheduler);
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
                $exception?->getMessage() ?: 'La captura social agotó sus reintentos.',
            );
        }
    }

    private function queueArticle(Scheduler $task, SchedulerService $scheduler): void
    {
        $task->update([
            'status' => Scheduler::STATUS_QUEUED,
            'step' => 'Original listo; texto IA en cola',
            'progress' => max(35, $task->progress),
            'last_error' => null,
        ]);
        $scheduler->addEvent($task, 'info', 'La recreación del texto se añadió a la cola de IA.');

        if ($this->dispatchArticle) {
            GenerateAiArticle::dispatch($task->id)->onQueue('ai-text');
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
