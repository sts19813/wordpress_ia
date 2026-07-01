<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiArticleGenerateRequest;
use App\Http\Requests\AiArticleUpdateRequest;
use App\Models\AiArticle;
use App\Models\SourcePost;
use App\Services\AiArticleService;
use App\Services\AiPromptProfileService;
use App\Support\SafeHtml;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AiArticleController extends Controller
{
    public function __construct(
        private readonly AiArticleService $articles,
        private readonly AiPromptProfileService $profiles,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AiArticle::class);

        return view('admin.ai-articles.index', [
            'articles' => $request->user()->aiArticles()
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

        $article = $this->articles->generateDraft($request->user(), $profile, $sourcePosts);
        $message = $article->status === AiArticle::STATUS_FAILED
            ? 'La generación no pudo completarse. Revisa el detalle y la configuración de OpenAI.'
            : 'Borrador generado y guardado. No se ha publicado en ningún sitio.';

        return redirect()->route('admin.ai-articles.show', $article)->with('status', $message);
    }

    public function show(AiArticle $aiArticle): View
    {
        Gate::authorize('view', $aiArticle);
        $aiArticle->load(['images', 'promptProfile:id,name']);

        return view('admin.ai-articles.show', [
            'article' => $aiArticle,
            'sourcePosts' => $aiArticle->sourcePosts()->load('sourceSite:id,name'),
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
