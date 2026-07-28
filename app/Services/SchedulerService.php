<?php

namespace App\Services;

use App\Jobs\GenerateAiArticle;
use App\Jobs\GenerateAiImage;
use App\Jobs\GenerateSourceArticle;
use App\Jobs\ScanSourceSite;
use App\Models\AiArticle;
use App\Models\AiPromptProfile;
use App\Models\Scheduler;
use App\Models\User;
use Throwable;

class SchedulerService
{
    /**
     * @param  array<int, int>  $sourcePostIds
     */
    public function createArticleTask(User $user, AiPromptProfile $profile, array $sourcePostIds): Scheduler
    {
        $task = Scheduler::query()->create([
            'user_id' => $user->id,
            'type' => Scheduler::TYPE_AI_ARTICLE,
            'name' => 'Generar borrador con IA',
            'status' => Scheduler::STATUS_QUEUED,
            'step' => 'Esperando un procesador de cola',
            'progress' => 0,
            'max_attempts' => 3,
            'payload' => [
                'profile_id' => $profile->id,
                'source_post_ids' => array_values($sourcePostIds),
                'generate_image' => (bool) $profile->generate_image,
            ],
            'events' => [],
        ]);

        $this->addEvent($task, 'info', 'Solicitud recibida y añadida a la cola.');

        GenerateAiArticle::dispatch($task->id)->onQueue('ai-text');

        return $task->fresh();
    }

    public function running(Scheduler $task, string $step, int $progress, int $attempt): Scheduler
    {
        $task->update([
            'status' => Scheduler::STATUS_RUNNING,
            'step' => $step,
            'progress' => $progress,
            'attempts' => $attempt,
            'started_at' => $task->started_at ?: now(),
            'finished_at' => null,
            'last_error' => null,
        ]);
        $this->addEvent($task, 'info', $step.' (intento '.$attempt.'/'.$task->max_attempts.').');

        return $task->fresh();
    }

    public function progress(
        Scheduler $task,
        string $step,
        int $progress,
        string $message,
        string $level = 'info',
    ): Scheduler {
        $task->update([
            'status' => Scheduler::STATUS_RUNNING,
            'step' => $step,
            'progress' => min(99, max(0, $progress)),
            'last_error' => null,
        ]);
        $this->addEvent($task, $level, $message);

        return $task->fresh();
    }

    public function awaitingImage(Scheduler $task, AiArticle $article, bool $dispatch = true): Scheduler
    {
        $task->update([
            'ai_article_id' => $article->id,
            'status' => Scheduler::STATUS_QUEUED,
            'step' => 'Texto listo; imagen en cola',
            'progress' => 70,
        ]);
        $this->addEvent($task, 'success', 'El borrador de texto quedó guardado.');
        $this->addEvent(
            $task,
            'info',
            $dispatch
                ? 'La imagen principal se añadió a una cola independiente.'
                : 'La ejecución manual continuará con la imagen principal.',
        );

        if ($dispatch) {
            GenerateAiImage::dispatch($task->id)->onQueue('ai-image');
        }

        return $task->fresh();
    }

    public function completed(Scheduler $task, string $message = 'Proceso completado correctamente.'): Scheduler
    {
        $task->update([
            'status' => Scheduler::STATUS_COMPLETED,
            'step' => 'Finalizado',
            'progress' => 100,
            'last_error' => null,
            'finished_at' => now(),
        ]);
        $this->addEvent($task, 'success', $message);

        return $task->fresh();
    }

    public function retrying(Scheduler $task, string $message): Scheduler
    {
        $task->update([
            'status' => Scheduler::STATUS_QUEUED,
            'step' => 'Esperando reintento automático',
            'last_error' => $message,
        ]);
        $this->addEvent($task, 'warning', 'El intento falló; la cola volverá a intentarlo: '.$message);

        return $task->fresh();
    }

    public function failed(Scheduler $task, string $message): Scheduler
    {
        $task->update([
            'status' => Scheduler::STATUS_FAILED,
            'step' => 'Proceso detenido',
            'last_error' => $message,
            'finished_at' => now(),
        ]);
        $this->addEvent($task, 'error', $message);

        return $task->fresh();
    }

    public function retry(Scheduler $task): Scheduler
    {
        $task->update([
            'status' => Scheduler::STATUS_QUEUED,
            'step' => 'Reintento manual en cola',
            'progress' => $task->article?->status === AiArticle::STATUS_DRAFT ? 70 : 0,
            'attempts' => 0,
            'last_error' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);
        $this->addEvent($task, 'info', 'Se solicitó un reintento manual.');

        $this->dispatchTask($task);

        return $task->fresh();
    }

    public function executeNow(Scheduler $task): Scheduler
    {
        $task->load('article.images');
        $this->addEvent($task, 'info', 'Ejecución manual iniciada desde el programador.');

        try {
            if ($task->type === Scheduler::TYPE_SOURCE_SCAN) {
                app()->call([new ScanSourceSite($task->id, (int) $task->source_site_id), 'handle']);
            } elseif ($task->type === Scheduler::TYPE_SOURCE_ARTICLE) {
                app()->call([new GenerateSourceArticle($task->id, (int) $task->source_site_id), 'handle']);
            } elseif ($this->shouldGenerateImage($task)) {
                $job = new GenerateAiImage($task->id);
                $job->handle(app(AiArticleService::class), $this);
            } else {
                $job = (new GenerateAiArticle($task->id))->withoutImageDispatch();
                $job->handle(app(AiArticleService::class), $this);

                $task->refresh()->load('article.images');

                if ($task->status === Scheduler::STATUS_QUEUED && $this->shouldGenerateImage($task)) {
                    $imageJob = new GenerateAiImage($task->id);
                    $imageJob->handle(app(AiArticleService::class), $this);
                }
            }
        } catch (Throwable $exception) {
            $task->refresh();

            if ($task->status !== Scheduler::STATUS_COMPLETED) {
                $this->failed($task, $exception->getMessage() ?: 'La ejecución manual no pudo completarse.');
            }

            report($exception);
        }

        return $task->fresh(['article.images']);
    }

    public function addEvent(Scheduler $task, string $level, string $message): void
    {
        $events = $task->events ?: [];
        $events[] = [
            'at' => now()->toIso8601String(),
            'level' => $level,
            'message' => $message,
        ];

        $task->update(['events' => array_slice($events, -30)]);
    }

    private function shouldGenerateImage(Scheduler $task): bool
    {
        return $task->article?->status === AiArticle::STATUS_DRAFT
            && (bool) ($task->payload['generate_image'] ?? false);
    }

    private function dispatchTask(Scheduler $task): void
    {
        if ($task->type === Scheduler::TYPE_SOURCE_SCAN) {
            ScanSourceSite::dispatch($task->id, (int) $task->source_site_id)->onQueue('source-pipeline');

            return;
        }

        if ($task->type === Scheduler::TYPE_SOURCE_ARTICLE) {
            GenerateSourceArticle::dispatch($task->id, (int) $task->source_site_id)->onQueue('source-pipeline');

            return;
        }

        if ($task->article?->status === AiArticle::STATUS_DRAFT && ($task->payload['generate_image'] ?? false)) {
            GenerateAiImage::dispatch($task->id)->onQueue('ai-image');
        } else {
            GenerateAiArticle::dispatch($task->id)->onQueue('ai-text');
        }
    }
}
