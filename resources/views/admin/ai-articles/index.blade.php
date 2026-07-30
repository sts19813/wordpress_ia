@extends('layouts.admin')

@section('title', 'Artículos IA | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Artículos IA</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Borradores generados; ninguno se publica automáticamente.</div>
        </div>
        <a href="{{ route('admin.ai-articles.create') }}" class="btn btn-primary text-nowrap">
            <i class="ki-outline ki-plus fs-2"></i>Nueva nota con IA
        </a>
    </div>
@endsection

@section('content')
    <div class="ai-articles-index-page">
        <div class="card card-flush">
            <div class="card-body py-5">
                <div class="ai-articles-table-wrap">
                    <table class="table align-middle table-row-dashed fs-7 gy-3 admin-datatable ai-articles-table" data-page-length="50">
                        <colgroup>
                            <col class="ai-article-col-title">
                            <col class="ai-article-col-profile">
                            <col class="ai-article-col-status">
                            <col class="ai-article-col-date">
                            <col class="ai-article-col-actions">
                        </colgroup>
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-8 text-uppercase">
                                <th>Borrador</th>
                                <th>Perfil</th>
                                <th>Estado</th>
                                <th>Creado</th>
                                <th class="text-end no-sort no-search">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @foreach ($articles as $article)
                                <tr>
                                    <td data-label="Borrador">
                                        <a href="{{ route('admin.ai-articles.show', $article) }}" class="ai-article-title text-gray-900 text-hover-primary fw-bold">
                                            {{ $article->title ?: 'Generación sin título #'.$article->id }}
                                        </a>
                                        @if ($article->excerpt || $article->generation_error)
                                            <div class="ai-article-excerpt text-muted fs-8">{{ $article->excerpt ?: $article->generation_error }}</div>
                                        @endif
                                    </td>
                                    <td data-label="Perfil">
                                        <div class="text-gray-800 text-truncate">{{ $article->promptProfile?->name ?: '—' }}</div>
                                        @if ($article->model)
                                            <div class="text-muted fs-9 text-truncate">{{ $article->model }}</div>
                                        @endif
                                    </td>
                                    <td data-label="Estado">
                                        <span class="badge {{ $article->status === 'draft' ? 'badge-light-success' : ($article->status === 'failed' ? 'badge-light-danger' : 'badge-light-warning') }}">
                                            {{ $article->statusLabel() }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap text-muted" data-label="Creado" data-order="{{ $article->created_at->timestamp }}">
                                        {{ $article->created_at->format('d/m/y H:i') }}
                                    </td>
                                    <td class="text-end" data-label="Acciones">
                                        <div class="dropdown d-inline-block">
                                            <button
                                                type="button"
                                                class="btn btn-icon btn-sm btn-light"
                                                data-bs-toggle="dropdown"
                                                data-bs-boundary="viewport"
                                                aria-expanded="false"
                                                aria-label="Acciones de {{ $article->title ?: 'la generación' }}"
                                            >
                                                <i class="ki-outline ki-dots-vertical fs-3"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end py-2 shadow-sm">
                                                <a href="{{ route('admin.ai-articles.show', $article) }}" class="dropdown-item d-flex align-items-center gap-2 py-2">
                                                    <i class="ki-outline ki-eye fs-4"></i>
                                                    Ver borrador
                                                </a>
                                                @if ($article->status === 'draft' && auth()->user()->can('update', $article))
                                                    <a href="{{ route('admin.ai-articles.edit', $article) }}" class="dropdown-item d-flex align-items-center gap-2 py-2">
                                                        <i class="ki-outline ki-pencil fs-4"></i>
                                                        Editar
                                                    </a>
                                                @endif
                                                @if (auth()->user()->can('delete', $article))
                                                    <div class="dropdown-divider"></div>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('admin.ai-articles.destroy', $article) }}"
                                                        data-confirm-delete
                                                        data-confirm-title="Eliminar borrador"
                                                        data-confirm-text="Se eliminarán también sus imágenes. Esta acción no se puede deshacer."
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger">
                                                            <i class="ki-outline ki-trash fs-4"></i>
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                @endif
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
        .ai-articles-index-page {
            min-width: 0;
            padding: 0 20px 8px;
        }

        .ai-articles-index-page .card,
        .ai-articles-index-page .card-body,
        .ai-articles-table-wrap,
        .ai-articles-index-page .admin-datatable-wrapper {
            min-width: 0;
            overflow: visible;
        }

        .ai-articles-table {
            width: 100% !important;
            table-layout: fixed;
        }

        .ai-article-col-title {
            width: 43%;
        }

        .ai-article-col-profile {
            width: 19%;
        }

        .ai-article-col-status {
            width: 14%;
        }

        .ai-article-col-date {
            width: 16%;
        }

        .ai-article-col-actions {
            width: 8%;
        }

        .ai-articles-table th,
        .ai-articles-table td {
            min-width: 0 !important;
            padding: .7rem .55rem !important;
            overflow-wrap: anywhere;
        }

        .ai-article-title,
        .ai-article-excerpt {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ai-articles-index-page .dataTables_filter {
            width: 100%;
            min-width: 0;
        }

        .ai-articles-index-page .dataTables_filter label {
            width: 100%;
            min-width: 0;
            justify-content: flex-end;
        }

        .ai-articles-index-page .dataTables_filter input {
            width: 280px !important;
            max-width: calc(100% - 68px);
            min-width: 0;
        }

        .ai-articles-index-page .dropdown-menu {
            min-width: 185px;
            z-index: 1080;
        }

        @media (max-width: 767.98px) {
            .ai-articles-index-page {
                padding-right: 16px;
                padding-left: 16px;
            }

            .ai-articles-index-page .dataTables_filter label {
                align-items: stretch;
                flex-direction: column;
            }

            .ai-articles-index-page .dataTables_filter input {
                width: 100% !important;
                max-width: 100%;
            }

            .ai-articles-table colgroup,
            .ai-articles-table thead {
                display: none;
            }

            .ai-articles-table,
            .ai-articles-table tbody,
            .ai-articles-table tr,
            .ai-articles-table td {
                display: block;
                width: 100% !important;
            }

            .ai-articles-table tr {
                margin-bottom: .75rem;
                padding: .5rem .75rem;
                border: 1px solid var(--bs-gray-200);
                border-radius: .65rem;
            }

            .ai-articles-table td {
                display: grid;
                grid-template-columns: 88px minmax(0, 1fr);
                gap: .75rem;
                align-items: center;
                border: 0 !important;
                padding: .42rem 0 !important;
                text-align: left !important;
            }

            .ai-articles-table td::before {
                color: var(--bs-gray-500);
                content: attr(data-label);
                font-size: .75rem;
                font-weight: 700;
                text-transform: uppercase;
            }

            .ai-articles-table td:first-child {
                align-items: start;
            }

            .ai-articles-table td:last-child > * {
                justify-self: start;
            }
        }
    </style>
@endpush
