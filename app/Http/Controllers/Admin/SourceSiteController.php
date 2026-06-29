<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SourceSiteRequest;
use App\Models\SourceSite;
use App\Repositories\SourceSiteRepository;
use App\Services\SourceSiteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SourceSiteController extends Controller
{
    public function __construct(
        private readonly SourceSiteRepository $sourceSites,
        private readonly SourceSiteService $sourceSiteService,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.source-sites.index', [
            'sourceSites' => $this->sourceSites->getForAdmin($request->query()),
            'filterOptions' => $this->sourceSites->distinctFilterOptions(),
            'typeOptions' => SourceSite::typeOptions(),
            'statusOptions' => SourceSite::statusOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.source-sites.create', [
            'sourceSite' => new SourceSite([
                'type' => SourceSite::TYPE_RSS,
                'status' => SourceSite::STATUS_PENDING,
                'frequency_minutes' => 60,
                'language' => 'es',
                'priority' => 5,
                'auth_method' => SourceSite::AUTH_NONE,
                'active' => true,
            ]),
            'typeOptions' => SourceSite::typeOptions(),
            'statusOptions' => SourceSite::statusOptions(),
            'authMethodOptions' => SourceSite::authMethodOptions(),
        ]);
    }

    public function store(SourceSiteRequest $request): RedirectResponse
    {
        $this->sourceSiteService->create($request->validated());

        return redirect()
            ->route('admin.source-sites.index')
            ->with('status', 'Sitio fuente creado correctamente.');
    }

    public function edit(SourceSite $sourceSite): View
    {
        return view('admin.source-sites.edit', [
            'sourceSite' => $sourceSite,
            'typeOptions' => SourceSite::typeOptions(),
            'statusOptions' => SourceSite::statusOptions(),
            'authMethodOptions' => SourceSite::authMethodOptions(),
        ]);
    }

    public function update(SourceSiteRequest $request, SourceSite $sourceSite): RedirectResponse
    {
        $this->sourceSiteService->update($sourceSite, $request->validated());

        return redirect()
            ->route('admin.source-sites.index')
            ->with('status', 'Sitio fuente actualizado correctamente.');
    }

    public function destroy(SourceSite $sourceSite): RedirectResponse
    {
        $this->sourceSiteService->delete($sourceSite);

        return redirect()
            ->route('admin.source-sites.index')
            ->with('status', 'Sitio fuente eliminado correctamente.');
    }
}
