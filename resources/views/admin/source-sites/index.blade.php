@extends('layouts.admin')

@section('title', 'Sitios fuente | '.config('app.name'))

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
                                <th class="min-w-170px">Nombre</th>
                                <th class="min-w-150px">Empresa</th>
                                <th class="min-w-240px">URL</th>
                                <th class="min-w-105px">Estado</th>
                                <th class="min-w-100px">Frecuencia</th>
                                <th class="min-w-135px">Última sincronización</th>
                                <th class="min-w-80px">Activo</th>
                                <th class="text-end min-w-260px no-sort no-search">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @foreach ($sourceSites as $sourceSite)
                                <tr>
                                    <td data-label="Nombre">
                                        <a href="{{ route('admin.source-sites.edit', $sourceSite) }}" class="source-site-name text-gray-900 text-hover-primary fw-bold">
                                            {{ $sourceSite->name }}
                                        </a>
                                    </td>
                                    <td data-label="Empresa">{{ $sourceSite->company?->name ?: '—' }}</td>
                                    <td data-label="URL">
                                        <a href="{{ $sourceSite->url }}" target="_blank" rel="noopener noreferrer" class="source-site-url text-gray-600 text-hover-primary" title="{{ $sourceSite->url }}">
                                            {{ $sourceSite->url }}
                                        </a>
                                    </td>
                                    <td data-label="Estado">
                                        <span class="badge {{ $statusClasses[$sourceSite->status] ?? 'badge-light' }}">{{ $sourceSite->statusLabel() }}</span>
                                    </td>
                                    <td data-label="Frecuencia" class="text-nowrap" data-order="{{ $sourceSite->frequency_minutes }}">
                                        {{ max(1, (int) ceil($sourceSite->frequency_minutes / 60)) }} h
                                    </td>
                                    <td data-label="Última sincronización" class="text-nowrap" data-order="{{ $sourceSite->last_synced_at?->timestamp ?: 0 }}">
                                        {{ $sourceSite->last_synced_at?->format('d/m/Y H:i') ?: '—' }}
                                    </td>
                                    <td data-label="Activo" data-order="{{ $sourceSite->active ? 1 : 0 }}">
                                        @if ($sourceSite->active)
                                            <span class="badge badge-light-success">Sí</span>
                                        @else
                                            <span class="badge badge-light-danger">No</span>
                                        @endif
                                    </td>
                                    <td data-label="Acciones" class="text-end">
                                        <div class="d-inline-flex align-items-center justify-content-end gap-2">
                                            <a href="{{ route('admin.source-scan-logs.index', ['source_site_id' => $sourceSite->id]) }}" class="btn btn-sm btn-light-info text-nowrap">
                                                <i class="ki-outline ki-note-2 fs-4"></i>
                                                Ver bitácora
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
            margin-bottom: 0 !important;
        }

        .source-sites-table th,
        .source-sites-table td {
            padding-top: .8rem !important;
            padding-bottom: .8rem !important;
        }

        .source-sites-table .source-site-name {
            display: block;
            overflow: hidden;
            max-width: 260px;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-decoration: none;
        }

        .source-sites-table .source-site-url {
            display: block;
            overflow: hidden;
            max-width: 360px;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-decoration: none;
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
