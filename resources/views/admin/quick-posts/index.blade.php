@extends('layouts.admin')

@section('title', 'Post rápido | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Post rápido</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Originales sociales archivados y listos para reutilizar en el flujo de IA.</div>
        </div>
        <a href="{{ route('admin.quick-posts.create') }}" class="btn btn-primary">
            <i class="ki-outline ki-plus fs-2"></i>Nuevo desde URL
        </a>
    </div>
@endsection

@section('content')
    <div class="quick-posts-table-page">
        <div class="card card-flush">
            <div class="card-body py-5">
                <div class="quick-posts-table-wrap">
                    <table class="table align-middle table-row-dashed fs-7 gy-3 admin-datatable quick-posts-table" data-page-length="50">
                        <colgroup>
                            <col class="quick-post-col-original">
                            <col class="quick-post-col-network">
                            <col class="quick-post-col-author">
                            <col class="quick-post-col-images">
                            <col class="quick-post-col-date">
                            <col class="quick-post-col-actions">
                        </colgroup>
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-8 text-uppercase">
                            <th>Original</th>
                            <th>Red</th>
                            <th>Autor</th>
                            <th>Imágenes</th>
                            <th>Capturado</th>
                            <th class="text-end no-sort no-search">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @foreach ($posts as $post)
                            <tr>
                                <td data-label="Original">
                                    <div class="quick-post-original d-flex align-items-center gap-3">
                                        @if ($post->media->first()?->file_path)
                                            <img src="{{ route('admin.source-post-media.file', $post->media->first()) }}" alt="" class="quick-post-thumb rounded">
                                        @else
                                            <div class="quick-post-thumb-placeholder rounded bg-light-primary">
                                                <i class="ki-outline ki-picture fs-3 text-primary"></i>
                                            </div>
                                        @endif
                                        <div class="quick-post-copy">
                                            <a href="{{ route('admin.news.show', $post) }}" class="quick-post-title text-gray-900 text-hover-primary fw-bold">{{ $post->title }}</a>
                                            <div class="quick-post-url text-muted fs-9">{{ $post->canonical_url ?: $post->url }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Red"><span class="badge badge-light-primary">{{ $post->originLabel() }}</span></td>
                                <td data-label="Autor"><div class="text-truncate">{{ $post->author ?: '—' }}</div></td>
                                <td data-label="Imágenes">
                                    <span
                                        class="badge badge-light-info"
                                        title="{{ $post->media->count() }} {{ $post->media->count() === 1 ? 'imagen archivada' : 'imágenes archivadas' }}"
                                    >
                                        {{ $post->media->count() }}
                                    </span>
                                </td>
                                <td class="text-nowrap text-muted" data-label="Capturado" data-order="{{ $post->captured_at?->timestamp ?: 0 }}">
                                    {{ $post->captured_at?->format('d/m/y H:i') ?: '—' }}
                                </td>
                                <td class="text-end" data-label="Acciones">
                                    <div class="quick-post-actions d-inline-flex align-items-center justify-content-end gap-1">
                                        <a href="{{ route('admin.news.show', $post) }}" class="btn btn-sm btn-light-info text-nowrap">
                                            <i class="ki-outline ki-eye fs-4"></i>Ver original
                                        </a>
                                        <a href="{{ route('admin.ai-articles.create', ['source_post_ids' => [$post->id]]) }}" class="btn btn-sm btn-light-primary text-nowrap">
                                            <i class="ki-outline ki-sparkles fs-4"></i>Generar
                                        </a>
                                        <form method="POST" action="{{ route('admin.quick-posts.destroy', $post) }}" data-confirm-delete data-confirm-title="Eliminar post original" data-confirm-text="También se borrarán sus imágenes archivadas.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-sm btn-light-danger" aria-label="Eliminar">
                                                <i class="ki-outline ki-trash fs-4"></i>
                                            </button>
                                        </form>
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
        .quick-posts-table-page {
            min-width: 0;
            padding: 0 20px 8px;
        }

        .quick-posts-table-page .card,
        .quick-posts-table-page .card-body,
        .quick-posts-table-wrap,
        .quick-posts-table-page .admin-datatable-wrapper {
            min-width: 0;
            overflow: visible;
        }

        .quick-posts-table {
            width: 100% !important;
            table-layout: fixed;
        }

        .quick-post-col-original { width: 34%; }
        .quick-post-col-network { width: 10%; }
        .quick-post-col-author { width: 12%; }
        .quick-post-col-images { width: 8%; }
        .quick-post-col-date { width: 13%; }
        .quick-post-col-actions { width: 23%; }

        .quick-posts-table th,
        .quick-posts-table td {
            min-width: 0 !important;
            padding: .65rem .5rem !important;
            overflow-wrap: anywhere;
        }

        .quick-post-thumb,
        .quick-post-thumb-placeholder {
            width: 46px;
            height: 40px;
            flex: 0 0 46px;
        }

        .quick-post-thumb {
            object-fit: cover;
        }

        .quick-post-thumb-placeholder {
            display: grid;
            place-items: center;
        }

        .quick-post-title,
        .quick-post-url {
            display: block;
            overflow: hidden;
            max-width: 100%;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .quick-posts-table td:first-child,
        .quick-post-original {
            min-width: 0;
            overflow: hidden;
        }

        .quick-post-copy {
            width: 0;
            min-width: 0 !important;
            flex: 1 1 auto;
            overflow: hidden;
        }

        .quick-post-actions {
            white-space: nowrap;
        }

        .quick-posts-table-page .dataTables_filter {
            width: 100%;
            min-width: 0;
        }

        .quick-posts-table-page .dataTables_filter label {
            width: 100%;
            min-width: 0;
            justify-content: flex-end;
        }

        .quick-posts-table-page .dataTables_filter input {
            width: 280px !important;
            max-width: calc(100% - 68px);
            min-width: 0;
        }

        @media (max-width: 767.98px) {
            .quick-posts-table-page {
                padding-right: 16px;
                padding-left: 16px;
            }

            .quick-posts-table-page .dataTables_filter label {
                align-items: stretch;
                flex-direction: column;
            }

            .quick-posts-table-page .dataTables_filter input {
                width: 100% !important;
                max-width: 100%;
            }

            .quick-posts-table colgroup,
            .quick-posts-table thead {
                display: none;
            }

            .quick-posts-table,
            .quick-posts-table tbody,
            .quick-posts-table tr,
            .quick-posts-table td {
                display: block;
                width: 100% !important;
            }

            .quick-posts-table tr {
                margin-bottom: .75rem;
                padding: .5rem .75rem;
                border: 1px solid var(--bs-gray-200);
                border-radius: .65rem;
            }

            .quick-posts-table td {
                display: grid;
                grid-template-columns: 82px minmax(0, 1fr);
                gap: .75rem;
                align-items: center;
                border: 0 !important;
                padding: .42rem 0 !important;
                text-align: left !important;
            }

            .quick-posts-table td::before {
                color: var(--bs-gray-500);
                content: attr(data-label);
                font-size: .75rem;
                font-weight: 700;
                text-transform: uppercase;
            }

            .quick-posts-table td:last-child > * {
                justify-self: start;
            }

            .quick-post-actions {
                flex-wrap: wrap;
                justify-content: flex-start !important;
            }
        }
    </style>
@endpush
