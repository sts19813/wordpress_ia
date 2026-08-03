@extends('layouts.admin')

@section('title', 'Empresas | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-2 my-0">Empresas</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Organiza tus marcas y controla sus destinos de publicación desde un solo lugar.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.wordpress-sites.index') }}" class="btn btn-light-primary"><i class="ki-outline ki-send fs-2"></i>Catálogo de perfiles</a>
            <a href="{{ route('admin.companies.create') }}" class="btn btn-primary"><i class="ki-outline ki-plus fs-2"></i>Nueva empresa</a>
        </div>
    </div>
@endsection

@section('content')
    @if ($companies->isEmpty())
        <div class="card card-flush"><div class="card-body text-center py-15">
            <div class="symbol symbol-80px mb-6"><div class="symbol-label bg-light-primary"><i class="ki-outline ki-briefcase fs-3x text-primary"></i></div></div>
            <h2 class="fw-bold text-gray-900">Crea tu primera empresa</h2>
            <p class="text-muted fw-semibold mb-7">Después podrás relacionar sus perfiles de WordPress, Facebook, Instagram y X desde un catálogo visual.</p>
            <a href="{{ route('admin.companies.create') }}" class="btn btn-primary">Crear empresa</a>
        </div></div>
    @else
        <div class="row g-5 mb-7">
            <div class="col-sm-6 col-xl-3">
                <div class="card card-flush h-100"><div class="card-body d-flex align-items-center gap-4 py-5">
                    <div class="symbol symbol-45px"><div class="symbol-label bg-light-primary"><i class="ki-outline ki-briefcase fs-2 text-primary"></i></div></div>
                    <div><div class="fs-2 fw-bold text-gray-900">{{ $companies->count() }}</div><div class="text-muted fw-semibold fs-7">Empresas</div></div>
                </div></div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-flush h-100"><div class="card-body d-flex align-items-center gap-4 py-5">
                    <div class="symbol symbol-45px"><div class="symbol-label bg-light-success"><i class="ki-outline ki-check-circle fs-2 text-success"></i></div></div>
                    <div><div class="fs-2 fw-bold text-gray-900">{{ $companies->where('active', true)->count() }}</div><div class="text-muted fw-semibold fs-7">Empresas activas</div></div>
                </div></div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-flush h-100"><div class="card-body d-flex align-items-center gap-4 py-5">
                    <div class="symbol symbol-45px"><div class="symbol-label bg-light-info"><i class="ki-outline ki-send fs-2 text-info"></i></div></div>
                    <div><div class="fs-2 fw-bold text-gray-900">{{ $profileCount }}</div><div class="text-muted fw-semibold fs-7">Perfiles totales</div></div>
                </div></div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-flush h-100"><div class="card-body d-flex align-items-center gap-4 py-5">
                    <div class="symbol symbol-45px"><div class="symbol-label {{ $unassignedProfileCount ? 'bg-light-warning' : 'bg-light-success' }}"><i class="ki-outline ki-disconnect fs-2 {{ $unassignedProfileCount ? 'text-warning' : 'text-success' }}"></i></div></div>
                    <div><div class="fs-2 fw-bold text-gray-900">{{ $unassignedProfileCount }}</div><div class="text-muted fw-semibold fs-7">Sin empresa</div></div>
                </div></div>
            </div>
        </div>

        @if ($unassignedProfileCount)
            <div class="alert alert-warning d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4 mb-7">
                <div class="d-flex align-items-start"><i class="ki-outline ki-information-5 fs-2 me-3 mt-1"></i><div><strong>{{ $unassignedProfileCount }} {{ $unassignedProfileCount === 1 ? 'perfil necesita' : 'perfiles necesitan' }} una empresa.</strong><div class="fs-7">Abre una empresa y selecciónalos desde la pestaña Destinos de publicación.</div></div></div>
                <a href="{{ route('admin.wordpress-sites.index') }}" class="btn btn-sm btn-light-warning text-nowrap">Ver perfiles</a>
            </div>
        @endif

        <div class="card card-flush">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="position-relative">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute top-50 translate-middle-y ms-4"></i>
                        <input type="search" class="form-control form-control-solid ps-12 w-300px" placeholder="Buscar empresa o destino" data-company-search>
                    </div>
                </div>
            </div>
            <div class="card-body pt-3">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed gy-5">
                        <thead><tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0"><th>Empresa</th><th>Destinos relacionados</th><th>Uso</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody class="fw-semibold text-gray-700" data-company-list>
                            @foreach ($companies as $company)
                                <tr data-company-row data-search="{{ str($company->name.' '.$company->description.' '.$company->publicationProfiles->pluck('name')->join(' '))->lower() }}">
                                    <td class="min-w-225px">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="symbol symbol-45px"><div class="symbol-label bg-light-primary fw-bold text-primary">{{ str($company->name)->substr(0, 2)->upper() }}</div></div>
                                            <div>
                                                <a href="{{ route('admin.companies.edit', $company) }}" class="fw-bold fs-5 text-gray-900 text-hover-primary">{{ $company->name }}</a>
                                                <div class="text-muted fs-8 mw-300px text-truncate">{{ $company->description ?: 'Sin descripción' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="min-w-300px">
                                        @if ($company->publicationProfiles->isNotEmpty())
                                            <div class="d-flex flex-wrap gap-2">
                                                @foreach ($company->publicationProfiles->take(3) as $profile)
                                                    <span class="badge badge-light-primary">{{ $profile->typeLabel() }} · {{ $profile->name }}</span>
                                                @endforeach
                                                @if ($company->publicationProfiles->count() > 3)<span class="badge badge-light">+{{ $company->publicationProfiles->count() - 3 }}</span>@endif
                                            </div>
                                        @else
                                            <span class="text-muted fs-7">Sin destinos asignados</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $company->active ? 'badge-light-success' : 'badge-light-warning' }} mb-1">{{ $company->active ? 'Activa' : 'Pausada' }}</span>
                                        <div class="text-muted fs-8">{{ $company->source_sites_count }} sitios fuente</div>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('admin.companies.edit', ['company' => $company, 'tab' => 'destinos']) }}" class="btn btn-sm btn-light-primary">Administrar destinos</a>
                                        <a href="{{ route('admin.companies.edit', $company) }}" class="btn btn-sm btn-icon btn-light" title="Editar empresa"><i class="ki-outline ki-pencil fs-3"></i></a>
                                        <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" class="d-inline" data-confirm-delete data-confirm-title="Eliminar empresa" data-confirm-text="Solo se puede eliminar si no tiene destinos ni sitios fuente.">@csrf @method('DELETE')<button class="btn btn-sm btn-icon btn-light-danger" type="submit" title="Eliminar empresa"><i class="ki-outline ki-trash fs-3"></i></button></form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="text-center text-muted fw-semibold py-10 d-none" data-no-companies>No hay empresas que coincidan con la búsqueda.</div>
            </div>
        </div>
    @endif
@endsection

@if ($companies->isNotEmpty())
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const search = document.querySelector('[data-company-search]');
        const rows = Array.from(document.querySelectorAll('[data-company-row]'));
        const empty = document.querySelector('[data-no-companies]');
        search.addEventListener('input', function () {
            const term = search.value.trim().toLocaleLowerCase('es');
            let visible = 0;
            rows.forEach(function (row) {
                const show = !term || row.dataset.search.includes(term);
                row.classList.toggle('d-none', !show);
                if (show) visible++;
            });
            empty.classList.toggle('d-none', visible !== 0);
        });
    });
    </script>
    @endpush
@endif
