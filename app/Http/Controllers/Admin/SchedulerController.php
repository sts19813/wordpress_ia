<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Scheduler;
use App\Services\SchedulerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchedulerController extends Controller
{
    public function __construct(private readonly SchedulerService $scheduler) {}

    public function index(Request $request): View
    {
        $tasks = Scheduler::query()
            ->with(['article:id,user_id,title,status', 'article.images:id,ai_article_id,status,type'])
            ->latest()
            ->get();

        $counts = collect(Scheduler::statusOptions())
            ->mapWithKeys(fn (string $label, string $status) => [
                $status => $tasks->where('status', $status)->count(),
            ]);

        return view('admin.scheduler.index', [
            'tasks' => $tasks,
            'counts' => $counts,
            'selectedTaskId' => (int) $request->query('task', 0),
            'databaseQueueSize' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
            'failedQueueSize' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            'workerMayBeStopped' => $tasks->contains(fn (Scheduler $task) => $task->status === Scheduler::STATUS_QUEUED && $task->created_at->lt(now()->subMinutes(2))),
        ]);
    }

    public function status(Request $request, Scheduler $scheduler): JsonResponse
    {
        $scheduler->load('article:id,user_id,title,status');

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
                    : 'La ejecución manual terminó con un error. Revisa la bitácora del trabajo.',
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
}
