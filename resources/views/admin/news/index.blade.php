@extends('layouts.admin')

@section('title', 'Noticias obtenidas | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Noticias obtenidas</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Revisa las notas encontradas en tus sitios fuente y conviértelas en contenido.</div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-3">
            <a href="{{ route('admin.source-scan-logs.index') }}" class="btn btn-light-info">
                <i class="ki-outline ki-note-2 fs-2"></i>Ver bitácora
            </a>
            <form method="POST" action="{{ route('admin.news.fetch') }}" class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
                @csrf
                <select
                    name="source_site_id"
                    class="form-select form-select-solid w-250px"
                    aria-label="Filtrar y escanear por sitio"
                    data-news-source-filter
                    data-filter-url="{{ route('admin.news.index') }}"
                >
                    <option value="">Todos los sitios</option>
                    @foreach ($sourceSites as $id => $name)
                        <option value="{{ $id }}" @selected((int) $selectedSourceSiteId === (int) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary text-nowrap">
                    <i class="ki-outline ki-cloud-download fs-2"></i>
                    Escanear noticias
                </button>
            </form>
        </div>
    </div>
@endsection

@section('content')
    <div class="news-index-page">
        @if (session('import_errors'))
            <div class="alert alert-warning mb-6">
                <div class="fw-bold mb-2">Algunas fuentes no pudieron importarse.</div>
                @foreach (session('import_errors') as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card card-flush">
            <div class="card-body py-5">
                <div class="table-responsive news-table-wrap">
                    <table class="table align-middle table-row-dashed fs-7 gy-3 admin-datatable news-table" data-page-length="50">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-8 text-uppercase gs-0">
                                <th class="min-w-300px">Título</th>
                                <th class="min-w-140px">Fuente</th>
                                <th class="min-w-140px">Autor</th>
                                <th class="min-w-125px">Fecha</th>
                                <th class="text-end min-w-315px no-sort no-search">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @foreach ($sourcePosts as $sourcePost)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.news.show', $sourcePost) }}" class="news-title text-gray-900 text-hover-primary fw-bold">
                                            {{ $sourcePost->title }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-gray-800">{{ $sourcePost->originLabel() }}</span>
                                        @if ($sourcePost->isQuickPost())
                                            <span class="badge badge-light-primary ms-1 fs-9">Post rápido</span>
                                        @endif
                                    </td>
                                    <td>{{ $sourcePost->author ?: '—' }}</td>
                                    <td class="text-nowrap" data-order="{{ $sourcePost->published_at?->timestamp ?: 0 }}">
                                        {{ $sourcePost->published_at?->format('d/m/Y H:i') ?: '—' }}
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex align-items-center justify-content-end gap-2">
                                            @if ($sourcePost->status === \App\Models\SourcePost::STATUS_FETCHED)
                                                <a href="{{ route('admin.ai-articles.create', ['source_post_ids' => [$sourcePost->id]]) }}" class="btn btn-sm btn-light-primary text-nowrap">
                                                    <i class="ki-outline ki-sparkles fs-4"></i>
                                                    Generar nota con IA
                                                </a>
                                            @endif

                                            <div class="dropdown">
                                                <button type="button" class="btn btn-sm btn-light dropdown-toggle text-nowrap" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
                                                    Opciones
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end py-2 shadow-sm">
                                                    <a href="{{ route('admin.news.show', $sourcePost) }}" class="dropdown-item d-flex align-items-center gap-2 py-2">
                                                        <i class="ki-outline ki-eye fs-4"></i>
                                                        Ver detalle
                                                    </a>
                                                    @if ($sourcePost->url)
                                                        <a href="{{ $sourcePost->url }}" target="_blank" rel="noopener noreferrer" class="dropdown-item d-flex align-items-center gap-2 py-2">
                                                            <i class="ki-outline ki-exit-up fs-4"></i>
                                                            Ir al post original
                                                        </a>
                                                    @endif
                                                    <div class="dropdown-divider"></div>
                                                    <form method="POST" action="{{ route('admin.news.destroy', $sourcePost) }}" data-confirm-delete data-confirm-title="Eliminar noticia" data-confirm-text="Se eliminará {{ $sourcePost->title }}. Esta acción no se puede deshacer.">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger">
                                                            <i class="ki-outline ki-trash fs-4"></i>
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .news-index-page {
            padding: 0 20px 8px;
        }

        .news-index-page .card {
            overflow: visible;
        }

        .news-table-wrap {
            padding: 0 2px;
        }

        .news-table th,
        .news-table td {
            padding-top: .8rem !important;
            padding-bottom: .8rem !important;
        }

        .news-table .news-title {
            display: -webkit-box;
            overflow: hidden;
            max-width: 640px;
            line-height: 1.45;
            text-decoration: none;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .news-index-page .admin-datatable-wrapper {
            width: 100%;
            overflow: visible;
        }

        .news-index-page .dataTables_filter {
            width: 100%;
            padding-right: 2px;
        }

        .news-index-page .dataTables_filter label {
            width: 100%;
            justify-content: flex-end;
        }

        .news-index-page .dataTables_filter input {
            width: 280px !important;
            max-width: calc(100% - 68px);
        }

        .news-index-page .dropdown-menu {
            min-width: 210px;
            z-index: 1080;
        }

        @media (max-width: 767.98px) {
            .news-index-page {
                padding-right: 16px;
                padding-left: 16px;
            }

            .news-index-page .dataTables_filter label {
                justify-content: center;
            }

            .news-index-page .dataTables_filter input {
                width: 100% !important;
                max-width: 100%;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var sourceFilter = document.querySelector('[data-news-source-filter]');

            if (!sourceFilter) {
                return;
            }

            sourceFilter.addEventListener('change', function () {
                var target = new URL(sourceFilter.dataset.filterUrl, window.location.origin);

                if (sourceFilter.value) {
                    target.searchParams.set('source_site_id', sourceFilter.value);
                }

                window.location.assign(target.toString());
            });
        });
    </script>
@endpush
