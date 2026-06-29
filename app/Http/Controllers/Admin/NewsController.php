<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SourcePost;
use App\Repositories\SourcePostRepository;
use App\Services\NewsSources\SourceImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(
        private readonly SourcePostRepository $sourcePosts,
        private readonly SourceImportService $sourceImportService,
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

        $result = $this->sourceImportService->import($validated['source_site_id'] ?? null);

        $message = "Importación terminada: {$result['fetched']} obtenidas, {$result['created']} nuevas, {$result['duplicates']} duplicadas.";

        if ($result['sites'] === 0) {
            $message = 'No hay sitios fuente activos para importar.';
        }

        return redirect()
            ->route('admin.news.index', $request->only('source_site_id'))
            ->with('status', $message)
            ->with('import_errors', $result['errors']);
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
