@extends('layouts.admin')

@section('title', 'Administrar '.$company->name.' | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-4 w-100">
        <div>
            <a href="{{ route('admin.companies.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3"><i class="ki-outline ki-left fs-4 me-1"></i>Empresas</a>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <h1 class="page-heading text-gray-900 fw-bold fs-2 my-0">{{ $company->name }}</h1>
                <span class="badge {{ $company->active ? 'badge-light-success' : 'badge-light-warning' }}">{{ $company->active ? 'Activa' : 'Pausada' }}</span>
            </div>
            <div class="text-muted fw-semibold fs-7 pt-1">Configura la empresa y decide qué destinos de tu catálogo puede utilizar.</div>
        </div>
        <a href="{{ route('admin.wordpress-sites.create', ['company' => $company->id, 'return_company' => $company->id]) }}" class="btn btn-primary"><i class="ki-outline ki-plus fs-2"></i>Nuevo perfil</a>
    </div>
@endsection

@section('content')
    <div class="card card-flush mb-7">
        <div class="card-body py-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4">
                <ul class="nav nav-pills nav-pills-custom gap-2 mb-0">
                    <li class="nav-item">
                        <a class="nav-link btn btn-flex btn-color-gray-600 btn-active-color-primary fw-bold {{ $activeTab === 'general' ? 'active' : '' }}" href="{{ route('admin.companies.edit', ['company' => $company, 'tab' => 'general']) }}">
                            <i class="ki-outline ki-setting-2 fs-3 me-2"></i>Datos generales
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-flex btn-color-gray-600 btn-active-color-primary fw-bold {{ $activeTab === 'destinos' ? 'active' : '' }}" href="{{ route('admin.companies.edit', ['company' => $company, 'tab' => 'destinos']) }}">
                            <i class="ki-outline ki-send fs-3 me-2"></i>Destinos de publicación
                            <span class="badge badge-circle badge-light-primary ms-2">{{ $company->publication_profiles_count }}</span>
                        </a>
                    </li>
                </ul>
                <div class="d-flex gap-6 text-nowrap">
                    <div><span class="text-muted fs-8 d-block">Destinos</span><span class="fw-bold fs-5">{{ $company->publication_profiles_count }}</span></div>
                    <div><span class="text-muted fs-8 d-block">Sitios fuente</span><span class="fw-bold fs-5">{{ $company->source_sites_count }}</span></div>
                </div>
            </div>
        </div>
    </div>

    @if ($activeTab === 'general')
        <div class="row g-7">
            <div class="col-xl-8">@include('admin.companies._form')</div>
            <div class="col-xl-4">
                <div class="card card-flush bg-light-primary">
                    <div class="card-body">
                        <i class="ki-outline ki-information-5 fs-2hx text-primary mb-4"></i>
                        <h3 class="fw-bold text-gray-900">Organiza por empresa</h3>
                        <p class="text-gray-700 fw-semibold mb-4">Cada destino puede pertenecer a una sola empresa. Desde la pestaña de destinos puedes asignarlo, quitarlo o moverlo desde otra empresa.</p>
                        <a href="{{ route('admin.companies.edit', ['company' => $company, 'tab' => 'destinos']) }}" class="btn btn-sm btn-light-primary">Administrar destinos</a>
                    </div>
                </div>
            </div>
        </div>
    @else
        @php
            $selectedProfileIds = collect(old('publication_profile_ids', $publicationProfiles->where('company_id', $company->id)->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
        @endphp

        <form id="company-destinations-form" method="POST" action="{{ route('admin.companies.destinations.update', $company) }}">
            @csrf
            @method('PUT')

            <div class="card card-flush">
                <div class="card-header border-0 pt-6">
                    <div class="card-title flex-column align-items-start">
                        <h2 class="fw-bold mb-1">Catálogo de destinos</h2>
                        <div class="text-muted fs-7">Marca los perfiles que deben estar relacionados con {{ $company->name }}.</div>
                    </div>
                    <div class="card-toolbar">
                        <span class="badge badge-light-primary fs-7 px-4 py-3"><span data-selected-count>{{ count($selectedProfileIds) }}</span> seleccionados</span>
                    </div>
                </div>
                <div class="card-body pt-4">
                    @if ($publicationProfiles->isEmpty())
                        <div class="text-center py-12">
                            <div class="symbol symbol-70px mb-5"><div class="symbol-label bg-light-primary"><i class="ki-outline ki-send fs-2x text-primary"></i></div></div>
                            <h3 class="fw-bold text-gray-900">Aún no hay perfiles en tu catálogo</h3>
                            <p class="text-muted fw-semibold mb-6">Crea un perfil de WordPress, Facebook, Instagram o X y quedará asignado a esta empresa.</p>
                            <a href="{{ route('admin.wordpress-sites.create', ['company' => $company->id, 'return_company' => $company->id]) }}" class="btn btn-primary">Crear primer perfil</a>
                        </div>
                    @else
                        <div class="alert alert-light-primary d-flex align-items-start mb-7">
                            <i class="ki-outline ki-information-5 fs-2 text-primary me-3 mt-1"></i>
                            <div><strong>Los cambios se aplican al guardar.</strong> Si seleccionas un destino que pertenece a otra empresa, se moverá a {{ $company->name }}. Si desmarcas uno actual, quedará disponible sin empresa.</div>
                        </div>

                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-4 mb-6">
                            <div class="position-relative flex-grow-1 mw-lg-400px">
                                <i class="ki-outline ki-magnifier fs-3 position-absolute top-50 translate-middle-y ms-4"></i>
                                <input type="search" class="form-control form-control-solid ps-12" placeholder="Buscar por nombre, plataforma o empresa" data-destination-search>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-light-primary" data-select-visible>Seleccionar visibles</button>
                                <button type="button" class="btn btn-sm btn-light" data-clear-visible>Quitar visibles</button>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-7" data-platform-filters>
                            <button type="button" class="btn btn-sm btn-primary" data-platform="all">Todos <span class="badge badge-circle badge-light ms-1">{{ $publicationProfiles->count() }}</span></button>
                            @foreach (App\Models\WordPressSite::typeOptions() as $type => $label)
                                <button type="button" class="btn btn-sm btn-light" data-platform="{{ $type }}">{{ $label }} <span class="badge badge-circle badge-light-primary ms-1">{{ $publicationProfiles->where('type', $type)->count() }}</span></button>
                            @endforeach
                        </div>

                        <div class="row g-5" data-destination-list>
                            @foreach ($publicationProfiles as $profile)
                                @php
                                    $isSelected = in_array($profile->id, $selectedProfileIds, true);
                                    $isReady = $profile->active && $profile->status === App\Models\WordPressSite::STATUS_ACTIVE;
                                    $platformIcon = match ($profile->type) {
                                        App\Models\WordPressSite::TYPE_FACEBOOK_PAGE => 'ki-facebook',
                                        App\Models\WordPressSite::TYPE_INSTAGRAM => 'ki-instagram',
                                        App\Models\WordPressSite::TYPE_X => 'ki-message-text-2',
                                        default => 'ki-wordpress',
                                    };
                                @endphp
                                <div class="col-12 col-xxl-6" data-destination-item data-platform="{{ $profile->type }}" data-search="{{ str($profile->name.' '.$profile->typeLabel().' '.($profile->company?->name ?? 'sin empresa'))->lower() }}">
                                    <div class="border border-gray-300 rounded p-5 h-100 destination-choice {{ $isSelected ? 'border-primary bg-light-primary' : '' }}" data-choice-card>
                                        <div class="d-flex align-items-start gap-4">
                                            <label class="form-check form-check-custom form-check-solid mt-2 flex-shrink-0" title="Relacionar con {{ $company->name }}">
                                                <input class="form-check-input" type="checkbox" name="publication_profile_ids[]" value="{{ $profile->id }}" @checked($isSelected) data-destination-checkbox>
                                            </label>
                                            <div class="symbol symbol-45px flex-shrink-0"><div class="symbol-label bg-white"><i class="ki-outline {{ $platformIcon }} fs-2x text-primary"></i></div></div>
                                            <div class="min-w-0 flex-grow-1">
                                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                                    <div>
                                                        <div class="fw-bold fs-5 text-gray-900">{{ $profile->name }}</div>
                                                        <div class="text-muted text-truncate mw-300px">{{ $profile->destinationLabel() ?: 'Destino sin identificar' }}</div>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge badge-light-primary">{{ $profile->typeLabel() }}</span>
                                                        <span class="badge {{ $isReady ? 'badge-light-success' : ($profile->status === App\Models\WordPressSite::STATUS_ERROR ? 'badge-light-danger' : 'badge-light-warning') }}">{{ $isReady ? 'Listo' : $profile->statusLabel() }}</span>
                                                    </div>
                                                </div>
                                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                                                    <div class="fs-8 fw-semibold">
                                                        @if ($profile->company_id === $company->id)
                                                            <span class="text-success"><i class="ki-outline ki-check-circle text-success"></i> Asignado a esta empresa</span>
                                                        @elseif ($profile->company)
                                                            <span class="text-warning"><i class="ki-outline ki-arrow-right-left text-warning"></i> Actualmente en {{ $profile->company->name }}</span>
                                                        @else
                                                            <span class="text-muted">Sin empresa asignada</span>
                                                        @endif
                                                        <span class="text-muted ms-3">{{ $profile->publications_count }} publicaciones</span>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <button type="submit" form="test-profile-{{ $profile->id }}" class="btn btn-sm btn-light-primary">{{ $profile->isX() && blank($profile->x_access_token) ? 'Conectar' : 'Probar' }}</button>
                                                        <a href="{{ route('admin.wordpress-sites.edit', ['wordpressSite' => $profile, 'return_company' => $company->id]) }}" class="btn btn-sm btn-light">Editar</a>
                                                    </div>
                                                </div>
                                                @if ($profile->connection_error)<div class="text-danger fs-8 mt-3">{{ $profile->connection_error }}</div>@endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="text-center text-muted fw-semibold py-10 d-none" data-no-destinations>No hay destinos que coincidan con los filtros.</div>
                    @endif
                </div>
                @if ($publicationProfiles->isNotEmpty())
                    <div class="card-footer d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-4">
                        <div class="text-muted fs-7"><i class="ki-outline ki-shield-tick text-success fs-4 me-1"></i>Solo puedes administrar perfiles de tu cuenta.</div>
                        <button type="submit" class="btn btn-primary"><i class="ki-outline ki-check fs-2"></i>Guardar relaciones</button>
                    </div>
                @endif
            </div>
        </form>

        @foreach ($publicationProfiles as $profile)
            <form id="test-profile-{{ $profile->id }}" method="POST" action="{{ route('admin.wordpress-sites.test', $profile) }}" class="d-none">
                @csrf
                <input type="hidden" name="return_company_id" value="{{ $company->id }}">
            </form>
        @endforeach
    @endif
@endsection

@push('styles')
<style>
    .destination-choice { transition: border-color .18s ease, background-color .18s ease, transform .18s ease; }
    .destination-choice:hover { border-color: var(--bs-primary) !important; transform: translateY(-1px); }
    .mw-lg-400px { max-width: 400px; }
</style>
@endpush

@if ($activeTab === 'destinos' && $publicationProfiles->isNotEmpty())
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const items = Array.from(document.querySelectorAll('[data-destination-item]'));
        const search = document.querySelector('[data-destination-search]');
        const count = document.querySelector('[data-selected-count]');
        const empty = document.querySelector('[data-no-destinations]');
        let platform = 'all';

        const refreshSelection = function () {
            const selected = document.querySelectorAll('[data-destination-checkbox]:checked').length;
            if (count) count.textContent = selected;

            items.forEach(function (item) {
                const checkbox = item.querySelector('[data-destination-checkbox]');
                const card = item.querySelector('[data-choice-card]');
                card.classList.toggle('border-primary', checkbox.checked);
                card.classList.toggle('bg-light-primary', checkbox.checked);
            });
        };

        const refreshFilters = function () {
            const term = (search.value || '').trim().toLocaleLowerCase('es');
            let visible = 0;
            items.forEach(function (item) {
                const matchesPlatform = platform === 'all' || item.dataset.platform === platform;
                const matchesSearch = !term || item.dataset.search.includes(term);
                const show = matchesPlatform && matchesSearch;
                item.classList.toggle('d-none', !show);
                if (show) visible++;
            });
            empty.classList.toggle('d-none', visible !== 0);
        };

        document.querySelectorAll('[data-destination-checkbox]').forEach(function (checkbox) {
            checkbox.addEventListener('change', refreshSelection);
        });
        search.addEventListener('input', refreshFilters);

        document.querySelectorAll('[data-platform-filters] button[data-platform]').forEach(function (button) {
            button.addEventListener('click', function () {
                platform = button.dataset.platform;
                document.querySelectorAll('[data-platform-filters] button[data-platform]').forEach(function (candidate) {
                    candidate.classList.toggle('btn-primary', candidate === button);
                    candidate.classList.toggle('btn-light', candidate !== button);
                });
                refreshFilters();
            });
        });

        const setVisible = function (checked) {
            items.filter(function (item) { return !item.classList.contains('d-none'); }).forEach(function (item) {
                item.querySelector('[data-destination-checkbox]').checked = checked;
            });
            refreshSelection();
        };
        document.querySelector('[data-select-visible]').addEventListener('click', function () { setVisible(true); });
        document.querySelector('[data-clear-visible]').addEventListener('click', function () { setVisible(false); });
        refreshSelection();
    });
    </script>
    @endpush
@endif
