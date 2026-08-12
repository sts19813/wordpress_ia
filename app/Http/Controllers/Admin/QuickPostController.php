<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuickPostRequest;
use App\Models\AiPromptProfile;
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
        $this->profiles->ensureDefaultFor($request->user());

        $companies = $request->user()->companies()
            ->where('active', true)
            ->with(['publicationProfiles' => fn ($query) => $query
                ->where('active', true)
                ->where('status', 'active')
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('admin.quick-posts.create', [
            'profiles' => AiPromptProfile::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'publicationProfiles' => $request->user()
                ->wordpressSites()
                ->where('active', true)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'companies' => $companies,
        ]);
    }

    public function store(QuickPostRequest $request): RedirectResponse
    {
        $profile = AiPromptProfile::query()->findOrFail($request->validated('ai_prompt_profile_id'));
        $task = $this->scheduler->createQuickPostTask(
            $request->user(),
            $profile,
            $request->validated('url'),
            $request->validated('image_mode'),
            $request->validated()['publication_profile_ids'] ?? [],
            $request->validated('company_id'),
        );

        $imageMessage = $request->validated('image_mode') === 'original'
            ? 'conservando sus imágenes originales'
            : 'generando imágenes nuevas con IA';
        $publicationCount = count($request->validated()['publication_profile_ids'] ?? []);
        $publicationMessage = $publicationCount > 0
            ? ($publicationCount === 1
                ? ' Después se publicará automáticamente en el perfil seleccionado.'
                : " Después se publicará automáticamente en {$publicationCount} perfiles.")
            : ' El resultado quedará como borrador.';

        return redirect()
            ->route('admin.scheduler.index', ['task' => $task->id])
            ->with('status', "La captura y recreación con IA se añadieron a la cola, {$imageMessage}.{$publicationMessage}");
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
