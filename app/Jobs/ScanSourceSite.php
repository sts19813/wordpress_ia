<?php

namespace App\Jobs;

use App\Models\Scheduler;
use App\Services\NewsSources\SourceImportService;
use App\Services\SchedulerService;
use App\Services\SourcePipelineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class ScanSourceSite implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $taskId,
        public readonly int $sourceSiteId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->sourceSiteId;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(
        SourceImportService $imports,
        SourcePipelineService $pipeline,
        SchedulerService $scheduler,
    ): void {
        $task = Scheduler::query()->with('sourceSite')->findOrFail($this->taskId);

        if ($task->status === Scheduler::STATUS_COMPLETED) {
            return;
        }

        $scheduler->running(
            $task,
            'Consultando la fuente y aplicando filtros inteligentes',
            10,
            max(1, $this->attempts()),
        );

        try {
            if (! $task->sourceSite) {
                throw new RuntimeException('El sitio fuente ya no está disponible.');
            }

            $result = $imports->importSource($task->sourceSite);

            if ($result['error']) {
                throw new RuntimeException($result['error']);
            }

            $scheduler->progress(
                $task,
                'Consulta terminada; preparando notas aceptadas',
                35,
                "{$result['fetched']} de {$result['consultation_limit']} posibles revisadas, {$result['created']} nuevas, {$result['discarded']} descartadas y {$result['duplicates']} duplicadas.",
            );

            $generationLimit = max(1, (int) ($task->sourceSite->max_generations_per_scan ?: 5));
            $autoGenerate = (bool) data_get($task->payload, 'auto_generate', true);
            $generationPostIds = $autoGenerate
                ? collect($result['created_post_ids'])->take($generationLimit)->values()->all()
                : [];
            $generationSkipped = $autoGenerate
                ? max(0, count($result['created_post_ids']) - count($generationPostIds))
                : 0;
            $articleTasks = $pipeline->enqueueArticles($task->fresh(), $generationPostIds);

            if ($generationSkipped > 0) {
                $scheduler->addEvent(
                    $task,
                    'warning',
                    "{$generationSkipped} nota(s) aceptadas excedieron el máximo de {$generationLimit} generaciones por consulta. Quedaron guardadas en Noticias para generación manual.",
                );
            }

            $task->update([
                'payload' => [
                    ...($task->payload ?: []),
                    'scan_result' => $result,
                    'article_task_ids' => $articleTasks->pluck('id')->all(),
                    'generation_limit' => $generationLimit,
                    'generation_skipped' => $generationSkipped,
                ],
            ]);

            $message = $articleTasks->isEmpty()
                ? 'La consulta terminó y no hay notas nuevas que requieran generación.'
                : "La consulta terminó y creó {$articleTasks->count()} trabajo(s) de generación y publicación.";

            $scheduler->completed($task, $message);
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
                $exception?->getMessage() ?: 'La consulta de la fuente agotó sus reintentos.',
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
