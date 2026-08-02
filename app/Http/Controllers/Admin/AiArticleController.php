<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiArticleGenerateRequest;
use App\Http\Requests\AiArticleUpdateRequest;
use App\Models\AiArticle;
use App\Models\SourcePost;
use App\Services\AiPromptProfileService;
use App\Services\SchedulerService;
use App\Support\SafeHtml;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AiArticleController extends Controller
{
    public function __construct(
        private readonly AiPromptProfileService $profiles,
        private readonly SchedulerService $scheduler,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AiArticle::class);

        return view('admin.ai-articles.index', [
            'articles' => AiArticle::query()
                ->with(['images', 'promptProfile:id,name'])
                ->latest()
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', AiArticle::class);
        $this->profiles->ensureDefaultFor($request->user());
        $selectedIds = collect($request->query('source_post_ids', []))->map(fn ($id) => (int) $id)->filter()->all();

        return view('admin.ai-articles.create', [
            'sourcePosts' => SourcePost::query()
                ->with('sourceSite:id,name')
                ->where('status', SourcePost::STATUS_FETCHED)
                ->latest('published_at')
                ->limit(200)
                ->get(),
            'profiles' => $request->user()->aiPromptProfiles()->orderByDesc('is_default')->orderBy('name')->get(),
            'selectedIds' => $selectedIds,
        ]);
    }

    public function store(AiArticleGenerateRequest $request): RedirectResponse
    {
        Gate::authorize('create', AiArticle::class);
        $validated = $request->validated();
        $profile = $request->user()->aiPromptProfiles()->findOrFail($validated['ai_prompt_profile_id']);
        $sourcePosts = SourcePost::query()
            ->whereIn('id', $validated['source_post_ids'])
            ->where('status', SourcePost::STATUS_FETCHED)
            ->get();

        abort_unless($sourcePosts->count() === count($validated['source_post_ids']), 422, 'Una de las fuentes seleccionadas no está disponible.');

        $task = $this->scheduler->createArticleTask(
            $request->user(),
            $profile,
            $sourcePosts->pluck('id')->all(),
        );

        // Con la cola sync (pruebas/desarrollo puntual) el trabajo ya terminó.
        if ($task->article && in_array($task->status, ['completed', 'failed'], true)) {
            $message = $task->status === 'failed'
                ? 'La generación terminó con observaciones. Revisa el detalle y la bitácora.'
                : 'Borrador generado y guardado. No se ha publicado en ningún sitio.';

            return redirect()->route('admin.ai-articles.show', $task->article)->with('status', $message);
        }

        return redirect()
            ->route('admin.scheduler.index', ['task' => $task->id])
            ->with('status', 'La generación se añadió a la cola. Puedes salir de esta página; continuará en segundo plano.');
    }

    public function show(Request $request, AiArticle $aiArticle): View
    {
        Gate::authorize('view', $aiArticle);
        $aiArticle->load([
            'images',
            'company:id,name',
            'promptProfile:id,name',
            'publications' => fn ($query) => $query
                ->with('wordpressSite:id,user_id,type,name,rest_api_url,facebook_page_id')
                ->latest(),
        ]);

        return view('admin.ai-articles.show', [
            'article' => $aiArticle,
            'sourcePosts' => $aiArticle->sourcePosts()->load('sourceSite:id,name'),
            'publicationProfiles' => $request->user()->wordpressSites()
                ->when($aiArticle->company_id, fn ($query, $companyId) => $query->where('company_id', $companyId))
                ->where('active', true)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function edit(AiArticle $aiArticle): View
    {
        Gate::authorize('update', $aiArticle);

        return view('admin.ai-articles.edit', ['article' => $aiArticle]);
    }

    public function update(AiArticleUpdateRequest $request, AiArticle $aiArticle): RedirectResponse
    {
        Gate::authorize('update', $aiArticle);
        $data = $request->validated();
        $data['content'] = SafeHtml::clean($data['content']);
        $data['slug'] = filled($data['slug'] ?? null) ? str($data['slug'])->slug()->toString() : str($data['title'])->slug()->toString();

        foreach (['categories', 'tags', 'seo_keywords'] as $field) {
            $data[$field] = collect(explode(',', (string) ($data[$field] ?? '')))
                ->map(fn (string $value) => trim($value))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $aiArticle->update([...$data, 'status' => AiArticle::STATUS_DRAFT]);

        return redirect()->route('admin.ai-articles.show', $aiArticle)->with('status', 'Borrador actualizado. Continúa sin publicarse.');
    }

    public function destroy(AiArticle $aiArticle): RedirectResponse
    {
        Gate::authorize('delete', $aiArticle);

        foreach ($aiArticle->images as $image) {
            if ($image->file_path) {
                Storage::disk('local')->delete($image->file_path);
            }
        }

        $aiArticle->delete();

        return redirect()->route('admin.ai-articles.index')->with('status', 'Borrador eliminado.');
    }
}
