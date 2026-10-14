<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuickPostRequest;
use App\Models\AiPromptProfile;
use App\Models\SourcePost;
use App\Services\AiPromptProfileService;
use App\Services\SchedulerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
                ->where(fn ($query) => $query->whereNull('social_platform')->orWhere('social_platform', '!=', 'manual'))
                ->latest('captured_at')
                ->get(),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('admin.manual-posts.create', ['mode' => 'url']);
    }

    public function store(QuickPostRequest $request): RedirectResponse|JsonResponse
    {
        $profile = AiPromptProfile::query()->findOrFail($request->validated('ai_prompt_profile_id'));
        $companyIds = $request->companyIds();
        $publicationProfileIds = $request->publicationProfileIds();
        $task = $this->scheduler->createQuickPostTask(
            $request->user(),
            $profile,
            $request->validated('url'),
            $request->validated('image_mode'),
            $publicationProfileIds,
            count($companyIds) === 1 ? $companyIds[0] : null,
            true,
            $companyIds,
        );

        $imageMessage = $request->validated('image_mode') === 'original'
            ? 'conservando sus imágenes originales'
            : 'generando imágenes nuevas con IA';
        $publicationCount = count($publicationProfileIds);
        $publicationMessage = $publicationCount > 0
            ? " Podrás revisarlo antes de publicarlo en {$publicationCount} destino(s)."
            : ' El resultado quedará como borrador.';

        $message = "La captura y recreación con IA se añadieron a la cola, {$imageMessage}.{$publicationMessage}";

        if ($request->expectsJson()) {
            return response()->json([
                'task_id' => $task->id,
                'status_url' => route('admin.scheduler.status', $task),
                'message' => $message,
            ], 202);
        }

        return redirect()
            ->route('admin.scheduler.index', ['task' => $task->id])
            ->with('status', $message);
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
