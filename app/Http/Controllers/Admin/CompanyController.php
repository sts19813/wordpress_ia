<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyDestinationsRequest;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Models\SourceSite;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Company::class);

        return view('admin.companies.index', [
            'companies' => $request->user()->accessibleCompanies()
                ->with('user:id,name,email')
                ->withCount(['publicationProfiles', 'sourceSites'])
                ->with(['publicationProfiles' => fn ($query) => $query->orderBy('type')->orderBy('name')])
                ->orderBy('name')
                ->get(),
            'profileCount' => $request->user()->accessibleWordPressSites()->count(),
            'unassignedProfileCount' => $request->user()->accessibleWordPressSites()->whereNull('company_id')->count(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Company::class);

        return view('admin.companies.create', [
            'company' => new Company(['active' => true]),
        ]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = $request->user()->companies()->create($request->validated());

        return redirect()->route('admin.companies.index')
            ->with('status', "Empresa {$company->name} creada correctamente.");
    }

    public function edit(Request $request, Company $company): View
    {
        Gate::authorize('update', $company);

        $company->loadMissing('user')->loadCount(['publicationProfiles', 'sourceSites']);

        return view('admin.companies.edit', [
            'company' => $company,
            'publicationProfiles' => $company->user->wordpressSites()
                ->with('company:id,name')
                ->withCount('publications')
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
            'activeTab' => $request->query('tab') === 'destinos' ? 'destinos' : 'general',
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return redirect()->route('admin.companies.edit', ['company' => $company, 'tab' => 'general'])
            ->with('status', 'Empresa actualizada correctamente.');
    }

    public function updateDestinations(CompanyDestinationsRequest $request, Company $company): RedirectResponse
    {
        $selectedIds = $request->validated('publication_profile_ids');
        $company->loadMissing('user');

        DB::transaction(function () use ($company, $selectedIds): void {
            $profiles = $company->user->wordpressSites()->lockForUpdate()->get(['id', 'company_id']);
            $selectedLookup = array_fill_keys($selectedIds, true);

            foreach ($profiles as $profile) {
                if (isset($selectedLookup[$profile->id])) {
                    if ($profile->company_id !== $company->id) {
                        $profile->update(['company_id' => $company->id]);
                    }

                    continue;
                }

                if ($profile->company_id === $company->id) {
                    $profile->update(['company_id' => null]);
                }
            }

            $profilesById = $company->user->wordpressSites()
                ->get(['id', 'company_id'])
                ->keyBy('id');

            SourceSite::query()
                ->where('automation_user_id', $company->user_id)
                ->whereNotNull('publication_profile_ids')
                ->get()
                ->each(function (SourceSite $source) use ($profilesById): void {
                    $validIds = collect($source->publication_profile_ids)
                        ->map(fn ($id) => (int) $id)
                        ->filter(fn (int $id) => $profilesById->get($id)?->company_id !== null)
                        ->unique()
                        ->values()
                        ->all();

                    if ($validIds !== $source->selectedPublicationProfileIds()) {
                        $source->update([
                            'publication_profile_ids' => $validIds,
                            'wordpress_site_id' => $validIds[0] ?? null,
                        ]);
                    }
                });
        });

        return redirect()->route('admin.companies.edit', ['company' => $company, 'tab' => 'destinos'])
            ->with('status', 'Destinos de publicación actualizados correctamente.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        Gate::authorize('delete', $company);

        if ($company->publicationProfiles()->exists() || $company->sourceSites()->exists()) {
            return back()->with('warning', 'No se puede eliminar una empresa con perfiles de publicación o sitios fuente. Reasígnalos primero.');
        }

        $company->delete();

        return redirect()->route('admin.companies.index')->with('status', 'Empresa eliminada correctamente.');
    }
}
