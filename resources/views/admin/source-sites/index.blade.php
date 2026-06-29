@extends('layouts.admin')

@section('title', 'Sitios Fuente | '.config('app.name'))

@php
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
                <table class="table align-middle table-row-dashed fs-6 gy-5 admin-datatable">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-220px">Nombre</th>
                            <th class="min-w-160px">Tipo</th>
                            <th class="min-w-125px">Estado</th>
                            <th class="min-w-125px">Frecuencia</th>
                            <th class="min-w-125px">Categoría</th>
                            <th class="min-w-100px">Prioridad</th>
                            <th class="min-w-150px">Última sync</th>
                            <th class="min-w-100px">Activo</th>
                            <th class="text-end min-w-100px no-sort no-search">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @foreach ($sourceSites as $sourceSite)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.source-sites.edit', $sourceSite) }}" class="text-gray-900 text-hover-primary fw-bold">{{ $sourceSite->name }}</a>
                                    <div class="text-muted text-truncate mw-300px">{{ $sourceSite->url }}</div>
                                    <div class="text-muted fs-8">{{ strtoupper($sourceSite->language) }} @if ($sourceSite->country) · {{ $sourceSite->country }} @endif</div>
                                </td>
                                <td>{{ $sourceSite->typeLabel() }}</td>
                                <td><span class="badge {{ $statusClasses[$sourceSite->status] ?? 'badge-light' }}">{{ $sourceSite->statusLabel() }}</span></td>
                                <td data-order="{{ $sourceSite->frequency_minutes }}">{{ $sourceSite->frequency_minutes }} min</td>
                                <td>{{ $sourceSite->category ?: '-' }}</td>
                                <td data-order="{{ $sourceSite->priority }}"><span class="badge badge-light-primary">{{ $sourceSite->priority }}</span></td>
                                <td data-order="{{ $sourceSite->last_synced_at?->timestamp ?: 0 }}">{{ $sourceSite->last_synced_at?->format('d/m/Y H:i') ?: '-' }}</td>
                                <td data-order="{{ $sourceSite->active ? 1 : 0 }}">
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
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
