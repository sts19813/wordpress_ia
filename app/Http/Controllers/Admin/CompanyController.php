<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Company::class);

        return view('admin.companies.index', [
            'companies' => $request->user()->companies()
                ->withCount(['publicationProfiles', 'sourceSites'])
                ->with(['publicationProfiles' => fn ($query) => $query->orderBy('type')->orderBy('name')])
                ->orderBy('name')
                ->get(),
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

    public function edit(Company $company): View
    {
        Gate::authorize('update', $company);

        return view('admin.companies.edit', ['company' => $company]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return redirect()->route('admin.companies.index')
            ->with('status', 'Empresa actualizada correctamente.');
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
