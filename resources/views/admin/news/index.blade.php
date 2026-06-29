@extends('layouts.admin')

@section('title', 'Noticias Obtenidas | '.config('app.name'))

@php
    $statusClasses = [
        'fetched' => 'badge-light-success',
        'duplicate' => 'badge-light-warning',
        'discarded' => 'badge-light-danger',
    ];
@endphp

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Noticias Obtenidas</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Lectura de noticias obtenidas desde sitios fuente.</div>
        </div>

        <form method="POST" action="{{ route('admin.news.fetch') }}" class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
            @csrf
            <select name="source_site_id" class="form-select form-select-solid w-250px" aria-label="Sitio fuente para importar">
                <option value="">Todos los sitios activos</option>
                @foreach ($filterOptions['sourceSites'] as $id => $name)
                    <option value="{{ $id }}" @selected((string) request('source_site_id') === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary">
                <i class="ki-outline ki-cloud-download fs-2"></i>
                Obtener noticias
            </button>
        </form>
    </div>
@endsection

@section('content')
    @if (session('import_errors'))
        <div class="alert alert-warning mb-6">
            <div class="fw-bold mb-2">Algunas fuentes no pudieron importarse.</div>
            @foreach (session('import_errors') as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card card-flush">
        <div class="card-header align-items-center gap-4 py-5">
            <div class="card-title w-100">
                <form method="GET" action="{{ route('admin.news.index') }}" class="d-flex flex-column flex-xl-row align-items-xl-center gap-3 w-100">
                    <div class="position-relative">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4 top-50 translate-middle-y text-gray-500"></i>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-solid ps-12 w-275px" placeholder="Buscar título, contenido, URL...">
                    </div>

                    <select name="source_site_id" class="form-select form-select-solid w-200px">
                        <option value="">Todas las fuentes</option>
                        @foreach ($filterOptions['sourceSites'] as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('source_site_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="form-select form-select-solid w-160px">
                        <option value="">Estado</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="language" class="form-select form-select-solid w-125px">
                        <option value="">Idioma</option>
                        @foreach ($filterOptions['languages'] as $language)
                            <option value="{{ $language }}" @selected(request('language') === $language)>{{ strtoupper($language) }}</option>
                        @endforeach
                    </select>

                    <select name="category" class="form-select form-select-solid w-175px">
                        <option value="">Categoría</option>
                        @foreach ($filterOptions['categories'] as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>

                    <select name="tag" class="form-select form-select-solid w-175px">
                        <option value="">Tag</option>
                        @foreach ($filterOptions['tags'] as $tag)
                            <option value="{{ $tag }}" @selected(request('tag') === $tag)>{{ $tag }}</option>
                        @endforeach
                    </select>

                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-solid w-150px" aria-label="Fecha desde">
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-solid w-150px" aria-label="Fecha hasta">

                    <button type="submit" class="btn btn-light-primary">
                        <i class="ki-outline ki-filter fs-2"></i>
                        Filtrar
                    </button>
                    <a href="{{ route('admin.news.index') }}" class="btn btn-light">Limpiar</a>
                </form>
            </div>
        </div>

        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5 admin-datatable">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-300px">Título</th>
                            <th class="min-w-170px">Fuente</th>
                            <th class="min-w-150px">Autor</th>
                            <th class="min-w-150px">Fecha</th>
                            <th class="min-w-125px">Estado</th>
                            <th class="min-w-100px">Idioma</th>
                            <th class="text-end min-w-125px no-sort no-search">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @foreach ($sourcePosts as $sourcePost)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.news.show', $sourcePost) }}" class="text-gray-900 text-hover-primary fw-bold">{{ $sourcePost->title }}</a>
                                    <div class="text-muted text-truncate mw-400px">{{ $sourcePost->url }}</div>
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        @foreach (array_slice($sourcePost->categories ?: [], 0, 3) as $category)
                                            <span class="badge badge-light-primary">{{ $category }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>{{ $sourcePost->sourceSite?->name ?: '-' }}</td>
                                <td>{{ $sourcePost->author ?: '-' }}</td>
                                <td data-order="{{ $sourcePost->published_at?->timestamp ?: 0 }}">{{ $sourcePost->published_at?->format('d/m/Y H:i') ?: '-' }}</td>
                                <td><span class="badge {{ $statusClasses[$sourcePost->status] ?? 'badge-light' }}">{{ $sourcePost->statusLabel() }}</span></td>
                                <td>{{ $sourcePost->language ? strtoupper($sourcePost->language) : '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.news.show', $sourcePost) }}" class="btn btn-icon btn-light btn-sm me-2" aria-label="Ver detalle">
                                        <i class="ki-outline ki-eye fs-3"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.news.destroy', $sourcePost) }}" class="d-inline" data-confirm-delete data-confirm-title="Eliminar noticia" data-confirm-text="Se eliminará {{ $sourcePost->title }}. Esta acción no se puede deshacer.">
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
