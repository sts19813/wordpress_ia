<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Scheduler;
use App\Models\SourceSite;
use App\Services\SchedulerService;
use App\Services\SourcePipelineService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SchedulerController extends Controller
{
    public function __construct(
        private readonly SchedulerService $scheduler,
        private readonly SourcePipelineService $sourcePipeline,
    ) {}

    public function index(Request $request): View
    {
        $taskRelations = [
            'article:id,user_id,title,status',
            'article.images:id,ai_article_id,status,type',
            'article.publications' => fn ($query) => $query
                ->select('id', 'ai_article_id', 'status', 'remote_url', 'published_at')
                ->where('status', 'published')
                ->whereNotNull('remote_url')
                ->latest('published_at'),
            'sourceSite:id,name',
            'sourcePost:id,title',
            'publication:id,status,remote_url,error_message',
        ];
        $activeTasks = Scheduler::query()
            ->with($taskRelations)
            ->whereIn('status', [
                Scheduler::STATUS_QUEUED,
                Scheduler::STATUS_RUNNING,
                Scheduler::STATUS_FAILED,
            ])
            ->latest()
            ->get();
        $completedTasks = Scheduler::query()
            ->with($taskRelations)
            ->where('status', Scheduler::STATUS_COMPLETED)
            ->where(function ($query): void {
                $query
                    ->whereHas('publication', fn ($publicationQuery) => $publicationQuery
                        ->where('status', 'published')
                        ->whereNotNull('remote_url'))
                    ->orWhereHas('article.publications', fn ($publicationQuery) => $publicationQuery
                        ->where('status', 'published')
                        ->whereNotNull('remote_url'));
            })
            ->latest('finished_at')
            ->latest('id')
            ->get();
        $selectedTaskId = (int) $request->query('task', 0);
        $activeTab = ($request->query('tab') === 'completed'
            || $completedTasks->contains('id', $selectedTaskId))
                ? 'completed'
                : 'active';
        $sourceSites = SourceSite::query()
            ->with([
                'promptProfile:id,name',
                'wordpressSite:id,name',
            ])
            ->orderBy('next_scan_at')
            ->orderBy('name')
            ->get();
        $activeSourceTasks = Scheduler::query()
            ->where('type', Scheduler::TYPE_SOURCE_SCAN)
            ->whereIn('status', [Scheduler::STATUS_QUEUED, Scheduler::STATUS_RUNNING])
            ->latest('id')
            ->get()
            ->keyBy('source_site_id');

        return view('admin.scheduler.index', [
            'activeTasks' => $activeTasks,
            'completedTasks' => $completedTasks,
            'activeTab' => $activeTab,
            'selectedTaskId' => $selectedTaskId,
            'workerMayBeStopped' => $activeTasks->contains(fn (Scheduler $task) => $task->status === Scheduler::STATUS_QUEUED && $task->created_at->lt(now()->subMinutes(2))),
            'sourceSites' => $sourceSites,
            'activeSourceTasks' => $activeSourceTasks,
        ]);
    }

    public function status(Request $request, Scheduler $scheduler): JsonResponse
    {
        $scheduler->load([
            'article:id,user_id,title,status',
            'sourceSite:id,name',
            'publication:id,status,remote_url,error_message',
        ]);

        return response()->json([
            'id' => $scheduler->id,
            'status' => $scheduler->status,
            'status_label' => $scheduler->statusLabel(),
            'step' => $scheduler->step,
            'progress' => $scheduler->progress,
            'attempts' => $scheduler->attempts,
            'max_attempts' => $scheduler->max_attempts,
            'last_error' => $scheduler->last_error,
            'events' => $scheduler->events ?: [],
            'article_url' => $scheduler->article ? route('admin.ai-articles.show', $scheduler->article) : null,
            'publication_url' => $scheduler->publication?->remote_url,
            'source_site' => $scheduler->sourceSite?->name,
            'updated_at' => $scheduler->updated_at->toIso8601String(),
        ]);
    }

    public function execute(Scheduler $scheduler): RedirectResponse
    {
        abort_unless($scheduler->status === Scheduler::STATUS_QUEUED, 422, 'Sólo se pueden ejecutar trabajos que estén en cola.');

        $task = $this->scheduler->executeNow($scheduler);

        return redirect()
            ->route('admin.scheduler.index', ['task' => $task->id])
            ->with(
                $task->status === Scheduler::STATUS_COMPLETED ? 'status' : 'warning',
                $task->status === Scheduler::STATUS_COMPLETED
                    ? 'El trabajo se ejecutó manualmente y quedó completado.'
                    : 'La ejecución manual terminó con un error. Revisa el proceso.',
            );
    }

    public function retry(Request $request, Scheduler $scheduler): RedirectResponse
    {
        abort_unless($scheduler->status === Scheduler::STATUS_FAILED, 422, 'Sólo se pueden reintentar trabajos con error.');

        $this->scheduler->retry($scheduler);

        return redirect()
            ->route('admin.scheduler.index', ['task' => $scheduler->id])
            ->with('status', 'El trabajo se añadió nuevamente a la cola.');
    }

    public function destroy(Request $request, Scheduler $scheduler): RedirectResponse
    {
        abort_unless(
            in_array($scheduler->status, [Scheduler::STATUS_FAILED, Scheduler::STATUS_COMPLETED], true),
            422,
            'Sólo se pueden eliminar ejecuciones finalizadas o con error.',
        );

        $scheduler->delete();

        return redirect()
            ->route('admin.scheduler.index', ['tab' => $request->input('return_tab')])
            ->with('status', 'Ejecución eliminada. El contenido relacionado se conservó.');
    }

    public function runSource(Request $request, SourceSite $sourceSite): RedirectResponse
    {
        abort_unless($sourceSite->active && $sourceSite->status !== SourceSite::STATUS_PAUSED, 422, 'El sitio fuente no está activo.');

        $task = $this->sourcePipeline->enqueueScan($sourceSite, 'manual', $request->user());

        abort_unless($task, 422, 'No fue posible añadir la consulta a la cola.');

        return redirect()
            ->route('admin.scheduler.index', ['task' => $task->id])
            ->with('status', 'La consulta está en la cola. Si el procesador no responde, usa “Ejecutar” en el trabajo.');
    }
}
