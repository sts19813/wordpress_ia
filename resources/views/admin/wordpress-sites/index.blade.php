@extends('layouts.admin')

@section('title', 'Perfiles de publicación | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 w-100">
        <div>
            <a href="{{ route('admin.publications.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3"><i class="ki-outline ki-left fs-4 me-1"></i>Publicaciones</a>
            <h1 class="page-heading text-gray-900 fw-bold fs-2 my-0">Perfiles de publicación</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">{{ auth()->user()->isAdmin() ? 'Catálogo global de WordPress, Facebook, Instagram y X.' : 'Tu catálogo central de WordPress, Facebook, Instagram y X.' }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.companies.index') }}" class="btn btn-light-primary"><i class="ki-outline ki-briefcase fs-2"></i>Administrar por empresa</a>
            <a href="{{ route('admin.wordpress-sites.create') }}" class="btn btn-primary"><i class="ki-outline ki-plus fs-2"></i>Agregar perfil</a>
        </div>
    </div>
@endsection

@section('content')
    @php
        $readyCount = $sites->filter(fn ($site) => $site->active && $site->status === App\Models\WordPressSite::STATUS_ACTIVE)->count();
        $attentionCount = $sites->count() - $readyCount;
        $unassignedCount = $sites->whereNull('company_id')->count();
    @endphp

    @if ($sites->isEmpty())
        <div class="card card-flush"><div class="card-body text-center py-15">
            <div class="symbol symbol-80px mb-6"><div class="symbol-label bg-light-primary"><i class="ki-outline ki-send fs-3x text-primary"></i></div></div>
            <h2 class="fw-bold text-gray-900">Conecta tu primer destino</h2>
            <p class="text-muted fw-semibold mb-7">Crea un perfil para publicar automáticamente en WordPress, Facebook, Instagram o X.</p>
            <a href="{{ route('admin.wordpress-sites.create') }}" class="btn btn-primary">Agregar perfil</a>
        </div></div>
    @else
        <div class="row g-5 mb-7">
            <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100"><div class="card-body d-flex align-items-center gap-4 py-5"><div class="symbol symbol-45px"><div class="symbol-label bg-light-primary"><i class="ki-outline ki-send fs-2 text-primary"></i></div></div><div><div class="fs-2 fw-bold">{{ $sites->count() }}</div><div class="text-muted fs-7 fw-semibold">Perfiles totales</div></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100"><div class="card-body d-flex align-items-center gap-4 py-5"><div class="symbol symbol-45px"><div class="symbol-label bg-light-success"><i class="ki-outline ki-check-circle fs-2 text-success"></i></div></div><div><div class="fs-2 fw-bold">{{ $readyCount }}</div><div class="text-muted fs-7 fw-semibold">Listos para publicar</div></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100"><div class="card-body d-flex align-items-center gap-4 py-5"><div class="symbol symbol-45px"><div class="symbol-label {{ $attentionCount ? 'bg-light-warning' : 'bg-light-success' }}"><i class="ki-outline ki-notification-status fs-2 {{ $attentionCount ? 'text-warning' : 'text-success' }}"></i></div></div><div><div class="fs-2 fw-bold">{{ $attentionCount }}</div><div class="text-muted fs-7 fw-semibold">Requieren atención</div></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100"><div class="card-body d-flex align-items-center gap-4 py-5"><div class="symbol symbol-45px"><div class="symbol-label {{ $unassignedCount ? 'bg-light-warning' : 'bg-light-info' }}"><i class="ki-outline ki-briefcase fs-2 {{ $unassignedCount ? 'text-warning' : 'text-info' }}"></i></div></div><div><div class="fs-2 fw-bold">{{ $unassignedCount }}</div><div class="text-muted fs-7 fw-semibold">Sin empresa</div></div></div></div></div>
        </div>

        <div class="card card-flush">
            <div class="card-header border-0 pt-6 pb-2">
                <div class="card-title flex-column align-items-start">
                    <h2 class="fw-bold mb-1">Todos los perfiles</h2>
                    <div class="text-muted fs-7">Busca, prueba o modifica cualquier destino configurado.</div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 mb-6">
                    <div class="position-relative flex-grow-1 mw-400px">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute top-50 translate-middle-y ms-4"></i>
                        <input type="search" class="form-control form-control-solid ps-12" placeholder="Buscar perfil, destino o empresa" data-profile-search>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-3">
                        <select class="form-select form-select-solid min-w-200px" data-company-filter aria-label="Filtrar por empresa">
                            <option value="all">Todas las empresas</option>
                            <option value="none">Sin empresa</option>
                            @foreach ($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach
                        </select>
                        <select class="form-select form-select-solid min-w-175px" data-status-filter aria-label="Filtrar por estado">
                            <option value="all">Todos los estados</option>
                            <option value="ready">Listos</option>
                            <option value="attention">Requieren atención</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-7" data-type-filters>
                    <button type="button" class="btn btn-sm btn-primary" data-type="all">Todos <span class="badge badge-circle badge-light ms-1">{{ $sites->count() }}</span></button>
                    @foreach (App\Models\WordPressSite::typeOptions() as $type => $label)
                        <button type="button" class="btn btn-sm btn-light" data-type="{{ $type }}">{{ $label }} <span class="badge badge-circle badge-light-primary ms-1">{{ $sites->where('type', $type)->count() }}</span></button>
                    @endforeach
                </div>

                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed gy-5">
                        <thead><tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0"><th>Perfil</th><th>Empresa</th><th>Estado</th><th>Actividad</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody class="fw-semibold text-gray-700">
                            @foreach ($sites as $site)
                                @php
                                    $ready = $site->active && $site->status === App\Models\WordPressSite::STATUS_ACTIVE;
                                    $platformIcon = match ($site->type) {
                                        App\Models\WordPressSite::TYPE_FACEBOOK_PAGE => 'ki-facebook',
                                        App\Models\WordPressSite::TYPE_INSTAGRAM => 'ki-instagram',
                                        App\Models\WordPressSite::TYPE_X => 'ki-message-text-2',
                                        default => 'ki-wordpress',
                                    };
                                @endphp
                                <tr data-profile-row data-type="{{ $site->type }}" data-company="{{ $site->company_id ?: 'none' }}" data-state="{{ $ready ? 'ready' : 'attention' }}" data-search="{{ str($site->name.' '.$site->typeLabel().' '.$site->destinationLabel().' '.($site->company?->name ?? 'sin empresa'))->lower() }}">
                                    <td class="min-w-275px">
                                        <div class="d-flex align-items-center gap-4">
                                            <div class="symbol symbol-45px"><div class="symbol-label bg-light-primary"><i class="ki-outline {{ $platformIcon }} fs-2x text-primary"></i></div></div>
                                            <div class="min-w-0">
                                                <a href="{{ route('admin.wordpress-sites.edit', $site) }}" class="fw-bold fs-5 text-gray-900 text-hover-primary">{{ $site->name }}</a>
                                                <div class="text-muted text-truncate mw-300px">{{ $site->destinationLabel() ?: 'Destino sin identificar' }}</div>
                                                <span class="badge badge-light-primary mt-1">{{ $site->typeLabel() }}</span>
                                                @if (auth()->user()->isAdmin())
                                                    <div class="text-primary fs-8 mt-1"><i class="ki-outline ki-user me-1"></i>{{ $site->user?->name }} · {{ $site->user?->email }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="min-w-175px">
                                        @if ($site->company)
                                            <a href="{{ route('admin.companies.edit', ['company' => $site->company, 'tab' => 'destinos']) }}" class="text-gray-800 text-hover-primary fw-bold"><i class="ki-outline ki-briefcase me-1"></i>{{ $site->company->name }}</a>
                                        @else
                                            <span class="badge badge-light-warning">Sin empresa</span>
                                        @endif
                                    </td>
                                    <td class="min-w-150px">
                                        <span class="badge {{ $ready ? 'badge-light-success' : ($site->status === App\Models\WordPressSite::STATUS_ERROR ? 'badge-light-danger' : 'badge-light-warning') }}">{{ $ready ? 'Listo' : $site->statusLabel() }}</span>
                                        @if (! $site->active)<div class="text-muted fs-8 mt-1">No disponible</div>@endif
                                        @if ($site->connection_error)<div class="text-danger fs-8 mt-2 mw-250px text-truncate" title="{{ $site->connection_error }}">{{ $site->connection_error }}</div>@endif
                                    </td>
                                    <td class="min-w-150px">
                                        <div><span class="fw-bold">{{ $site->publications_count }}</span> <span class="text-muted fs-8">publicaciones</span></div>
                                        <div class="text-muted fs-8">Probado: {{ $site->last_tested_at?->format('d/m/Y H:i') ?: 'nunca' }}</div>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <form method="POST" action="{{ route('admin.wordpress-sites.test', $site) }}" class="d-inline">@csrf<button class="btn btn-sm btn-light-primary" type="submit">{{ $site->isX() && blank($site->x_access_token) ? 'Conectar' : 'Probar' }}</button></form>
                                        <a href="{{ route('admin.wordpress-sites.edit', $site) }}" class="btn btn-sm btn-icon btn-light" title="Editar perfil"><i class="ki-outline ki-pencil fs-3"></i></a>
                                        <form method="POST" action="{{ route('admin.wordpress-sites.destroy', $site) }}" class="d-inline" data-confirm-delete data-confirm-title="Eliminar perfil de publicación" data-confirm-text="Se quitará {{ $site->name }}, pero se conservará su historial de publicaciones.">@csrf @method('DELETE')<button class="btn btn-sm btn-icon btn-light-danger" type="submit" title="Eliminar perfil"><i class="ki-outline ki-trash fs-3"></i></button></form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="text-center py-12 d-none" data-no-profiles>
                    <i class="ki-outline ki-magnifier fs-2hx text-muted mb-3"></i>
                    <h3 class="fw-bold text-gray-800">No encontramos perfiles</h3>
                    <div class="text-muted">Cambia los filtros o el texto de búsqueda.</div>
                </div>
            </div>
        </div>
    @endif
@endsection

@if ($sites->isNotEmpty())
    @push('styles')
    <style>
        .mw-400px { max-width: 400px; }
        .mw-250px { max-width: 250px; }
    </style>
    @endpush
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const rows = Array.from(document.querySelectorAll('[data-profile-row]'));
        const search = document.querySelector('[data-profile-search]');
        const company = document.querySelector('[data-company-filter]');
        const status = document.querySelector('[data-status-filter]');
        const empty = document.querySelector('[data-no-profiles]');
        let type = 'all';

        const refresh = function () {
            const term = search.value.trim().toLocaleLowerCase('es');
            let visible = 0;
            rows.forEach(function (row) {
                const show = (type === 'all' || row.dataset.type === type)
                    && (company.value === 'all' || row.dataset.company === company.value)
                    && (status.value === 'all' || row.dataset.state === status.value)
                    && (!term || row.dataset.search.includes(term));
                row.classList.toggle('d-none', !show);
                if (show) visible++;
            });
            empty.classList.toggle('d-none', visible !== 0);
        };

        document.querySelectorAll('[data-type-filters] button[data-type]').forEach(function (button) {
            button.addEventListener('click', function () {
                type = button.dataset.type;
                document.querySelectorAll('[data-type-filters] button[data-type]').forEach(function (candidate) {
                    candidate.classList.toggle('btn-primary', candidate === button);
                    candidate.classList.toggle('btn-light', candidate !== button);
                });
                refresh();
            });
        });
        search.addEventListener('input', refresh);
        company.addEventListener('change', refresh);
        status.addEventListener('change', refresh);
    });
    </script>
    @endpush
@endif
