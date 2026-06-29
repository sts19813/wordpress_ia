@extends('layouts.admin')

@section('title', 'Sitios Fuente | '.config('app.name'))

@php
    $currentSort = request('sort', 'created_at');
    $currentDirection = request('direction', 'desc');
    $sortUrl = function (string $field) use ($currentSort, $currentDirection) {
        $direction = $currentSort === $field && $currentDirection === 'asc' ? 'desc' : 'asc';

        return request()->fullUrlWithQuery([
            'sort' => $field,
            'direction' => $direction,
            'page' => null,
        ]);
    };
    $sortIcon = function (string $field) use ($currentSort, $currentDirection) {
        if ($currentSort !== $field) {
            return 'ki-arrow-up-down';
        }

        return $currentDirection === 'asc' ? 'ki-arrow-up' : 'ki-arrow-down';
    };
    $statusClasses = [
        'pending' => 'badge-light-warning',
        'active' => 'badge-light-success',
        'paused' => 'badge-light-secondary',
        'error' => 'badge-light-danger',
    ];
@endphp

@section('toolbar')
    <div>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Sitios Fuente</h1>
        <div class="text-muted fw-semibold fs-7 pt-1">Administra fuentes WordPress REST, RSS y HTML.</div>
    </div>
    <a href="{{ route('admin.source-sites.create') }}" class="btn btn-primary">
        <i class="ki-outline ki-plus fs-2"></i>
        Nuevo sitio
    </a>
@endsection

@section('content')
    <div class="card card-flush">
        <div class="card-header align-items-center gap-4 py-5">
            <div class="card-title">
                <form method="GET" action="{{ route('admin.source-sites.index') }}" class="d-flex flex-column flex-xl-row align-items-xl-center gap-3">
                    <div class="position-relative">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4 top-50 translate-middle-y text-gray-500"></i>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-solid ps-12 w-250px" placeholder="Buscar sitio, URL, categoría...">
                    </div>

                    <select name="type" class="form-select form-select-solid w-200px">
                        <option value="">Todos los tipos</option>
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="form-select form-select-solid w-175px">
                        <option value="">Todos los estados</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="active" class="form-select form-select-solid w-150px">
                        <option value="">Activo/Inactivo</option>
                        <option value="1" @selected(request('active') === '1')>Activo</option>
                        <option value="0" @selected(request('active') === '0')>Inactivo</option>
                    </select>

                    <select name="category" class="form-select form-select-solid w-175px">
                        <option value="">Categoría</option>
                        @foreach ($filterOptions['categories'] as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>

                    <select name="language" class="form-select form-select-solid w-150px">
                        <option value="">Idioma</option>
                        @foreach ($filterOptions['languages'] as $language)
                            <option value="{{ $language }}" @selected(request('language') === $language)>{{ strtoupper($language) }}</option>
                        @endforeach
                    </select>

                    <select name="country" class="form-select form-select-solid w-175px">
                        <option value="">País</option>
                        @foreach ($filterOptions['countries'] as $country)
                            <option value="{{ $country }}" @selected(request('country') === $country)>{{ $country }}</option>
                        @endforeach
                    </select>

                    <input type="hidden" name="sort" value="{{ request('sort', 'created_at') }}">
                    <input type="hidden" name="direction" value="{{ request('direction', 'desc') }}">

                    <button type="submit" class="btn btn-light-primary">
                        <i class="ki-outline ki-filter fs-2"></i>
                        Filtrar
                    </button>

                    <a href="{{ route('admin.source-sites.index') }}" class="btn btn-light">Limpiar</a>
                </form>
            </div>
        </div>

        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-220px">
                                <a href="{{ $sortUrl('name') }}" class="text-gray-500 text-hover-primary">
                                    Nombre <i class="ki-outline {{ $sortIcon('name') }} fs-7"></i>
                                </a>
                            </th>
                            <th class="min-w-160px">
                                <a href="{{ $sortUrl('type') }}" class="text-gray-500 text-hover-primary">
                                    Tipo <i class="ki-outline {{ $sortIcon('type') }} fs-7"></i>
                                </a>
                            </th>
                            <th class="min-w-125px">
                                <a href="{{ $sortUrl('status') }}" class="text-gray-500 text-hover-primary">
                                    Estado <i class="ki-outline {{ $sortIcon('status') }} fs-7"></i>
                                </a>
                            </th>
                            <th class="min-w-125px">
                                <a href="{{ $sortUrl('frequency_minutes') }}" class="text-gray-500 text-hover-primary">
                                    Frecuencia <i class="ki-outline {{ $sortIcon('frequency_minutes') }} fs-7"></i>
                                </a>
                            </th>
                            <th class="min-w-125px">
                                <a href="{{ $sortUrl('category') }}" class="text-gray-500 text-hover-primary">
                                    Categoría <i class="ki-outline {{ $sortIcon('category') }} fs-7"></i>
                                </a>
                            </th>
                            <th class="min-w-100px">
                                <a href="{{ $sortUrl('priority') }}" class="text-gray-500 text-hover-primary">
                                    Prioridad <i class="ki-outline {{ $sortIcon('priority') }} fs-7"></i>
                                </a>
                            </th>
                            <th class="min-w-150px">
                                <a href="{{ $sortUrl('last_synced_at') }}" class="text-gray-500 text-hover-primary">
                                    Última sync <i class="ki-outline {{ $sortIcon('last_synced_at') }} fs-7"></i>
                                </a>
                            </th>
                            <th class="min-w-100px">
                                <a href="{{ $sortUrl('active') }}" class="text-gray-500 text-hover-primary">
                                    Activo <i class="ki-outline {{ $sortIcon('active') }} fs-7"></i>
                                </a>
                            </th>
                            <th class="text-end min-w-100px">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @forelse ($sourceSites as $sourceSite)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.source-sites.edit', $sourceSite) }}" class="text-gray-900 text-hover-primary fw-bold">{{ $sourceSite->name }}</a>
                                    <div class="text-muted text-truncate mw-300px">{{ $sourceSite->url }}</div>
                                    <div class="text-muted fs-8">{{ strtoupper($sourceSite->language) }} @if ($sourceSite->country) · {{ $sourceSite->country }} @endif</div>
                                </td>
                                <td>{{ $sourceSite->typeLabel() }}</td>
                                <td><span class="badge {{ $statusClasses[$sourceSite->status] ?? 'badge-light' }}">{{ $sourceSite->statusLabel() }}</span></td>
                                <td>{{ $sourceSite->frequency_minutes }} min</td>
                                <td>{{ $sourceSite->category ?: '-' }}</td>
                                <td><span class="badge badge-light-primary">{{ $sourceSite->priority }}</span></td>
                                <td>{{ $sourceSite->last_synced_at?->format('d/m/Y H:i') ?: '-' }}</td>
                                <td>
                                    @if ($sourceSite->active)
                                        <span class="badge badge-light-success">Sí</span>
                                    @else
                                        <span class="badge badge-light-danger">No</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.source-sites.edit', $sourceSite) }}" class="btn btn-icon btn-light btn-sm me-2" aria-label="Editar">
                                        <i class="ki-outline ki-pencil fs-3"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.source-sites.destroy', $sourceSite) }}" class="d-inline" data-confirm-delete data-confirm-title="Eliminar sitio fuente" data-confirm-text="Se eliminará {{ $sourceSite->name }}. Esta acción no elimina noticias ya importadas.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-icon btn-light-danger btn-sm" aria-label="Eliminar">
                                            <i class="ki-outline ki-trash fs-3"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-15">
                                    <div class="text-gray-500 fw-semibold">No hay sitios fuente registrados.</div>
                                    <a href="{{ route('admin.source-sites.create') }}" class="btn btn-primary mt-5">
                                        <i class="ki-outline ki-plus fs-2"></i>
                                        Crear primer sitio
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end pt-5">
                {{ $sourceSites->links() }}
            </div>
        </div>
    </div>
@endsection
