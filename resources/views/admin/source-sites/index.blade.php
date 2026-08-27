@extends('layouts.admin')

@section('title', 'Sitios fuente | '.config('app.name'))

@section('toolbar')
    <div>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Sitios fuente</h1>
        <div class="text-muted fw-semibold fs-7 pt-1">Administra los sitios que alimentan el flujo de noticias.</div>
    </div>
    <a href="{{ route('admin.source-sites.create') }}" class="btn btn-primary">
        <i class="ki-outline ki-plus fs-2"></i>
        Nuevo sitio
    </a>
@endsection

@section('content')
    <div class="source-sites-index-page">
        <div class="card card-flush">
            <div class="card-body py-5">
                <div class="source-sites-table-wrap">
                    <table class="table align-middle table-row-dashed fs-7 gy-3 admin-datatable source-sites-table" data-page-length="50">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-8 text-uppercase gs-0">
                                <th>Fuente</th>
                                <th class="source-sites-table__goal">Meta</th>
                                <th class="source-sites-table__sync">Última sinc.</th>
                                <th class="source-sites-table__active">Activo</th>
                                <th class="source-sites-table__actions text-end no-sort no-search">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @foreach ($sourceSites as $sourceSite)
                                @php
                                    $dailyTarget = $sourceSite->company_id
                                        ? $sourceSite->dailyPublicationTarget()
                                        : collect($sourceSite->normalizedPublicationSchedules())->sum('daily_target');
                                    $scheduleSummary = $sourceSite->publicationScheduleSummary();
                                @endphp
                                <tr>
                                    <td data-label="Fuente">
                                        <div class="source-site-summary">
                                            <a href="{{ route('admin.source-sites.edit', $sourceSite) }}" class="source-site-name text-gray-900 text-hover-primary fw-bold" title="{{ $sourceSite->name }}">
                                                {{ $sourceSite->name }}
                                            </a>
                                            <div class="source-site-meta text-muted">
                                                <span class="source-site-company">{{ $sourceSite->company?->name ?: 'Sin empresa' }}</span>
                                                <span class="source-site-meta-separator" aria-hidden="true">·</span>
                                                <a href="{{ $sourceSite->url }}" target="_blank" rel="noopener noreferrer" class="source-site-url text-muted text-hover-primary" title="{{ $sourceSite->url }}">
                                                    {{ $sourceSite->url }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Meta" class="text-nowrap" data-order="{{ $dailyTarget }}">
                                        <span class="source-site-goal" title="{{ $scheduleSummary }}">{{ number_format($dailyTarget) }}/día</span>
                                    </td>
                                    <td data-label="Última sinc." class="text-nowrap" data-order="{{ $sourceSite->last_synced_at?->timestamp ?: 0 }}">
                                        @if ($sourceSite->last_synced_at)
                                            <span class="source-site-sync-date">{{ $sourceSite->last_synced_at->format('d/m/y') }}</span>
                                            <span class="source-site-sync-time text-muted">{{ $sourceSite->last_synced_at->format('H:i') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td data-label="Activo" data-order="{{ $sourceSite->active ? 1 : 0 }}">
                                        @if ($sourceSite->active)
                                            <span class="source-site-active source-site-active--yes" title="Activo"><span aria-hidden="true"></span>Sí</span>
                                        @else
                                            <span class="source-site-active source-site-active--no" title="Inactivo"><span aria-hidden="true"></span>No</span>
                                        @endif
                                    </td>
                                    <td data-label="Acciones" class="text-end">
                                        <div class="source-site-actions d-inline-flex align-items-center justify-content-end gap-1">
                                            <a href="{{ route('admin.source-scan-logs.index', ['source_site_id' => $sourceSite->id]) }}" class="btn btn-icon btn-light-info btn-sm" aria-label="Ver bitácora" title="Ver bitácora">
                                                <i class="ki-outline ki-note-2 fs-3"></i>
                                            </a>
                                            <a href="{{ route('admin.source-sites.edit', $sourceSite) }}" class="btn btn-icon btn-light btn-sm" aria-label="Editar" title="Editar">
                                                <i class="ki-outline ki-pencil fs-3"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.source-sites.destroy', $sourceSite) }}" data-confirm-delete data-confirm-title="Eliminar sitio fuente" data-confirm-text="Se eliminará {{ $sourceSite->name }}. Esta acción no elimina noticias ya importadas.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-icon btn-light-danger btn-sm" aria-label="Eliminar" title="Eliminar">
                                                    <i class="ki-outline ki-trash fs-3"></i>
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
        .source-sites-index-page {
            padding: 0 0 8px;
        }

        .source-sites-table-wrap {
            width: 100%;
            min-width: 0;
            overflow: visible;
        }

        .source-sites-table {
            width: 100% !important;
            table-layout: fixed;
            margin-bottom: 0 !important;
        }

        .source-sites-table th,
        .source-sites-table td {
            padding: .65rem .55rem !important;
        }

        .source-sites-table th:first-child,
        .source-sites-table td:first-child {
            width: auto;
            padding-left: .15rem !important;
        }

        .source-sites-table__goal {
            width: 74px;
        }

        .source-sites-table__sync {
            width: 126px;
        }

        .source-sites-table__active {
            width: 66px;
        }

        .source-sites-table__actions {
            width: 124px;
            padding-right: .15rem !important;
        }

        .source-site-summary {
            min-width: 0;
        }

        .source-sites-table .source-site-name {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-decoration: none;
        }

        .source-sites-table .source-site-meta {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .35rem;
            margin-top: .15rem;
            font-size: .72rem;
            line-height: 1.25;
        }

        .source-sites-table .source-site-company {
            max-width: 38%;
            overflow: hidden;
            flex: 0 1 auto;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .source-sites-table .source-site-meta-separator {
            flex: 0 0 auto;
        }

        .source-sites-table .source-site-url {
            display: block;
            min-width: 0;
            overflow: hidden;
            flex: 1 1 auto;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-decoration: none;
        }

        .source-site-goal {
            font-weight: 700;
            color: var(--bs-gray-800);
        }

        .source-site-sync-date,
        .source-site-sync-time {
            display: inline;
        }

        .source-site-active {
            display: inline-flex;
            align-items: center;
            gap: .32rem;
            font-size: .72rem;
            font-weight: 700;
        }

        .source-site-active > span {
            width: .48rem;
            height: .48rem;
            border-radius: 50%;
            background: currentColor;
        }

        .source-site-active--yes {
            color: var(--bs-success);
        }

        .source-site-active--no {
            color: var(--bs-danger);
        }

        .source-site-actions .btn {
            width: 34px;
            height: 34px;
        }

        .source-sites-index-page .admin-datatable-wrapper {
            width: 100%;
            overflow: visible;
        }

        .source-sites-index-page .dataTables_filter {
            width: 100%;
            padding-right: 2px;
        }

        .source-sites-index-page .dataTables_filter label {
            width: 100%;
            justify-content: flex-end;
        }

        .source-sites-index-page .dataTables_filter input {
            width: 280px !important;
            max-width: calc(100% - 68px);
        }

        @media (max-width: 991.98px) {
            .source-sites-table {
                min-width: 0;
            }

            .source-site-sync-date,
            .source-site-sync-time {
                display: block;
            }
        }

        @media (max-width: 767.98px) {
            .source-sites-index-page {
                padding: 0;
            }

            .source-sites-index-page .card-body {
                padding: 1rem;
            }

            .source-sites-table-wrap,
            .source-sites-index-page .admin-datatable-wrapper {
                overflow: hidden;
            }

            .source-sites-table,
            .source-sites-table tbody,
            .source-sites-table tr,
            .source-sites-table td {
                display: block;
                width: 100% !important;
            }

            .source-sites-table thead {
                display: none;
            }

            .source-sites-table tbody tr {
                padding: .8rem 0;
                border-bottom: 1px dashed var(--bs-gray-300);
            }

            .source-sites-table tbody tr:last-child {
                border-bottom: 0;
            }

            .source-sites-table tbody td {
                display: grid;
                grid-template-columns: minmax(6.5rem, 36%) minmax(0, 1fr);
                gap: .65rem;
                padding: .35rem 0 !important;
                border: 0;
                text-align: left !important;
                white-space: normal !important;
                overflow-wrap: anywhere;
            }

            .source-sites-table tbody td::before {
                content: attr(data-label);
                color: var(--bs-gray-600);
                font-size: .68rem;
                font-weight: 700;
                text-transform: uppercase;
            }

            .source-sites-table .source-site-name,
            .source-sites-table .source-site-url {
                max-width: 100%;
                white-space: normal;
                overflow-wrap: anywhere;
            }

            .source-sites-table .source-site-meta {
                flex-wrap: wrap;
            }

            .source-sites-table .source-site-company {
                max-width: 100%;
            }

            .source-sites-table .source-site-url {
                flex-basis: 100%;
            }

            .source-site-sync-date,
            .source-site-sync-time {
                display: inline;
            }

            .source-sites-table td[data-label="Acciones"] > div {
                flex-wrap: wrap;
                justify-content: flex-start !important;
            }

            .source-sites-index-page .dataTables_filter label {
                justify-content: center;
            }

            .source-sites-index-page .dataTables_filter input {
                width: 100% !important;
                max-width: 100%;
            }
        }
    </style>
@endpush
