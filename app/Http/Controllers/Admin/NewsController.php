<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SourcePost;
use App\Models\SourceSite;
use App\Repositories\SourcePostRepository;
use App\Services\SourcePipelineService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(
        private readonly SourcePostRepository $sourcePosts,
        private readonly SourcePipelineService $sourcePipeline,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.news.index', [
            'sourcePosts' => $this->sourcePosts->getForAdmin($request->query()),
            'filterOptions' => $this->sourcePosts->filterOptions(),
            'statusOptions' => SourcePost::statusOptions(),
        ]);
    }

    public function fetch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_site_id' => ['nullable', 'integer', 'exists:source_sites,id'],
        ]);

        $sites = SourceSite::query()
            ->where('active', true)
            ->where('status', '!=', SourceSite::STATUS_PAUSED)
            ->when($validated['source_site_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->get();
        $tasks = $sites
            ->map(fn (SourceSite $site) => $this->sourcePipeline->enqueueScan($site, 'manual', $request->user()))
            ->filter();

        if ($tasks->isEmpty()) {
            return back()->with('warning', 'No hay sitios fuente activos para consultar.');
        }

        return redirect()
            ->route('admin.scheduler.index', ['task' => $tasks->last()->id])
            ->with('status', "{$tasks->count()} consulta(s) añadidas a la cola. El progreso ya está disponible en el Programador.");
    }

    public function show(SourcePost $sourcePost): View
    {
        $sourcePost->load('sourceSite:id,name,url');

        return view('admin.news.show', [
            'sourcePost' => $sourcePost,
        ]);
    }

    public function destroy(SourcePost $sourcePost): RedirectResponse
    {
        $title = $sourcePost->title;

        $sourcePost->delete();

        return redirect()
            ->route('admin.news.index')
            ->with('status', "Noticia eliminada correctamente: {$title}");
    }
}
