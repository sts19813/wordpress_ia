<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuickPostRequest;
use App\Models\SourcePost;
use App\Services\AiPromptProfileService;
use App\Services\SchedulerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuickPostController extends Controller
{
    public function __construct(
        private readonly AiPromptProfileService $profiles,
        private readonly SchedulerService $scheduler,
    ) {}

    public function index(): View
    {
        return view('admin.quick-posts.index', [
            'posts' => SourcePost::query()
                ->with(['media', 'sourceSite:id,name'])
                ->where('origin_type', SourcePost::ORIGIN_QUICK_POST)
                ->latest('captured_at')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.quick-posts.create', [
            'profile' => $this->profiles->ensureDefaultFor($request->user()),
        ]);
    }

    public function store(QuickPostRequest $request): RedirectResponse
    {
        $profile = $this->profiles->ensureDefaultFor($request->user());
        $task = $this->scheduler->createQuickPostTask(
            $request->user(),
            $profile,
            $request->validated('url'),
            $request->validated('image_mode'),
        );

        $imageMessage = $request->validated('image_mode') === 'original'
            ? 'conservando sus imágenes originales'
            : 'generando imágenes nuevas con IA';

        return redirect()
            ->route('admin.scheduler.index', ['task' => $task->id])
            ->with('status', "La captura y recreación con IA se añadieron a la cola, {$imageMessage}.");
    }

    public function destroy(SourcePost $sourcePost): RedirectResponse
    {
        abort_unless($sourcePost->isQuickPost(), 404);
        $title = $sourcePost->title;
        $sourcePost->delete();

        return redirect()
            ->route('admin.quick-posts.index')
            ->with('status', "Post original eliminado: {$title}");
    }
}
