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
            ->with(['wordpressSite:id,user_id,type,name,rest_api_url,facebook_page_id', 'aiArticle:id,user_id,title,slug'])
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
        $activeProfiles = $request->user()->wordpressSites()
            ->where('active', true)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        if ($activeProfiles->isEmpty()) {
            return redirect()->route('admin.wordpress-sites.create')
                ->with('warning', 'Configura tu primer perfil de publicación antes de publicar.');
        }

        if ($activeProfiles->count() === 1) {
            $selectedProfiles = $activeProfiles;
        } else {
            $selectedIds = collect($request->validated('site_ids', []))->map(fn ($id) => (int) $id);

            if ($selectedIds->isEmpty()) {
                return back()->withErrors(['site_ids' => 'Selecciona al menos un perfil de publicación.']);
            }

            $selectedProfiles = $activeProfiles->whereIn('id', $selectedIds);
        }

        $aiArticle->load('images');
        $results = $selectedProfiles
            ->sortBy(fn ($profile) => $profile->isFacebookPage() ? 1 : 0)
            ->map(fn ($profile) => $this->publicationService->publishNow($profile, $aiArticle, $aiArticle->mainImage()));
        $successful = $results->filter(fn (Publication $publication) => $publication->isSuccessful())->count();
        $failed = $results->where('status', Publication::STATUS_FAILED)->count();

        $redirect = redirect()->route('admin.ai-articles.show', $aiArticle);

        if ($successful > 0) {
            $redirect->with('status', $successful === 1
                ? 'Artículo publicado correctamente.'
                : "Artículo publicado correctamente en {$successful} perfiles.");
        }

        if ($failed > 0) {
            $redirect->with('warning', $failed === 1
                ? 'Un perfil no pudo publicar el artículo. Revisa el detalle en Publicaciones.'
                : "{$failed} perfiles no pudieron publicar el artículo. Revisa el detalle en Publicaciones.");
        }

        return $redirect;
    }
}
