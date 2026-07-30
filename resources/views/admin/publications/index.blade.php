@extends('layouts.admin')

@section('title', 'Publicaciones | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Publicaciones</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Todos los posts enviados a tus perfiles de publicación.</div>
        </div>
        <div class="d-flex gap-3">
            <a href="{{ route('admin.wordpress-sites.index') }}" class="btn btn-light-primary"><i class="ki-outline ki-setting-2 fs-2"></i>Configurar perfiles</a>
            <a href="{{ route('admin.ai-articles.index') }}" class="btn btn-primary"><i class="ki-outline ki-plus fs-2"></i>Elegir artículo</a>
        </div>
    </div>
@endsection

@section('content')
    @if ($sites->isEmpty())
        <div class="alert alert-primary d-flex align-items-center mb-8">
            <i class="ki-outline ki-information-5 fs-2hx text-primary me-4"></i>
            <div class="flex-grow-1"><div class="fw-bold">Todavía no hay un perfil conectado</div><div>Agrega WordPress o una página de Facebook para habilitar el botón Publicar.</div></div>
            <a href="{{ route('admin.wordpress-sites.create') }}" class="btn btn-sm btn-primary">Agregar perfil</a>
        </div>
    @endif

    <div class="card card-flush">
        <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Historial</h3></div></div>
        <div class="card-body pt-0">
            <table class="table align-middle table-row-dashed fs-6 gy-4 admin-datatable publications-table">
                <thead><tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                    <th>Artículo</th>
                    <th>Perfil</th>
                    <th>Fecha</th>
                    <th class="text-end no-sort no-search">Acciones</th>
                </tr></thead>
                <tbody class="text-gray-700 fw-semibold">
                    @foreach ($publications as $publication)
                        @php($publicationDate = $publication->published_at ?: $publication->updated_at)
                        <tr>
                            <td data-label="Artículo">
                                @if ($publication->aiArticle)
                                    <a href="{{ route('admin.ai-articles.show', $publication->aiArticle) }}" class="text-gray-900 text-hover-primary fw-bold">{{ $publication->aiArticle->title }}</a>
                                @else
                                    <span class="text-muted">Artículo eliminado</span>
                                @endif
                            </td>
                            <td data-label="Perfil">
                                <span class="fw-bold text-gray-900">{{ $publication->wordpressSite?->name ?: 'Perfil eliminado' }}</span>
                            </td>
                            <td data-label="Fecha" data-order="{{ $publicationDate->timestamp }}">
                                <span class="publication-date text-muted">
                                    <span>{{ $publicationDate->format('d/m/y') }}</span>
                                    <span>{{ $publicationDate->format('H:i') }}</span>
                                </span>
                            </td>
                            <td class="text-end" data-label="Acciones">
                                @if ($publication->remote_url)
                                    <a href="{{ $publication->remote_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light-primary">Ver entrada <i class="ki-outline ki-exit-up fs-4 ms-1"></i></a>
                                @elseif ($publication->aiArticle)
                                    <a href="{{ route('admin.ai-articles.show', $publication->aiArticle) }}" class="btn btn-sm btn-light">Revisar</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .publications-table {
            width: 100% !important;
            table-layout: fixed;
        }

        .publications-table th:nth-child(1) { width: 46%; }
        .publications-table th:nth-child(2) { width: 24%; }
        .publications-table th:nth-child(3) { width: 12%; }
        .publications-table th:nth-child(4) { width: 18%; }

        .publications-table td {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .publication-date {
            display: inline-flex;
            flex-direction: row;
            gap: .4rem;
            font-size: .75rem;
            line-height: 1.25;
            white-space: nowrap;
        }

        @media (max-width: 767.98px) {
            .publications-table,
            .publications-table tbody,
            .publications-table tr,
            .publications-table td {
                display: block;
                width: 100% !important;
            }

            .publications-table thead {
                display: none;
            }

            .publications-table tbody tr {
                padding: .85rem 0;
            }

            .publications-table tbody td {
                display: grid;
                grid-template-columns: minmax(5.5rem, 28%) minmax(0, 1fr);
                gap: .75rem;
                padding: .35rem 0 !important;
                text-align: left !important;
                border: 0;
            }

            .publications-table tbody td::before {
                content: attr(data-label);
                color: var(--bs-gray-600);
                font-size: .75rem;
                font-weight: 700;
                text-transform: uppercase;
            }

            .publications-table tbody td:last-child a {
                justify-self: start;
            }

        }
    </style>
@endpush
