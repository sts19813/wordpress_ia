<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicationRequest;
use App\Models\AiArticle;
use App\Models\Publication;
use App\Services\PublicationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PublicationController extends Controller
{
    public function __construct(
        private readonly PublicationService $publicationService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Publication::class);

        $publications = Publication::query()
            ->with(['wordpressSite:id,user_id,name,rest_api_url', 'aiArticle:id,user_id,title,slug'])
            ->latest()
            ->get();

        return view('admin.publications.index', [
            'publications' => $publications,
            'sites' => $request->user()->wordpressSites()->latest()->get(),
            'publishedCount' => $publications->where('status', Publication::STATUS_PUBLISHED)->count(),
            'failedCount' => $publications->where('status', Publication::STATUS_FAILED)->count(),
        ]);
    }

    public function publish(PublicationRequest $request, AiArticle $aiArticle): RedirectResponse
    {
        $activeSites = $request->user()->wordpressSites()
            ->where('active', true)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        if ($activeSites->isEmpty()) {
            return redirect()->route('admin.wordpress-sites.create')
                ->with('warning', 'Configura tu primer sitio WordPress antes de publicar.');
        }

        if ($activeSites->count() === 1) {
            $selectedSites = $activeSites;
        } else {
            $selectedIds = collect($request->validated('site_ids', []))->map(fn ($id) => (int) $id);

            if ($selectedIds->isEmpty()) {
                return back()->withErrors(['site_ids' => 'Selecciona al menos un sitio WordPress.']);
            }

            $selectedSites = $activeSites->whereIn('id', $selectedIds);
        }

        $aiArticle->load('images');
        $results = $selectedSites->map(fn ($site) => $this->publicationService->publishNow($site, $aiArticle, $aiArticle->mainImage()));
        $successful = $results->filter(fn (Publication $publication) => $publication->isSuccessful())->count();
        $failed = $results->where('status', Publication::STATUS_FAILED)->count();

        $redirect = redirect()->route('admin.ai-articles.show', $aiArticle);

        if ($successful > 0) {
            $redirect->with('status', $successful === 1
                ? 'Artículo publicado correctamente en WordPress.'
                : "Artículo publicado correctamente en {$successful} sitios WordPress.");
        }

        if ($failed > 0) {
            $redirect->with('warning', $failed === 1
                ? 'Un sitio no pudo publicar el artículo. Revisa el detalle en Publicaciones.'
                : "{$failed} sitios no pudieron publicar el artículo. Revisa el detalle en Publicaciones.");
        }

        return $redirect;
    }
}
