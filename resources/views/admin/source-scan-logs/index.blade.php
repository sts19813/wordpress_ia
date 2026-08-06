@extends('layouts.admin')

@section('title', 'Bitácora de fuentes | '.config('app.name'))

@php
    $outcomeClasses = [
        'accepted' => 'badge-light-success',
        'discarded' => 'badge-light-danger',
        'duplicate' => 'badge-light-warning',
        'invalid' => 'badge-light-dark',
    ];

    $filterMethodLabels = [
        'ai' => 'Inteligencia artificial',
        'keyword_fallback' => 'Respaldo por palabras',
        'no_filter' => 'Sin filtro',
        'validation' => 'Validación',
    ];
@endphp

@section('toolbar')
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Bitácora de fuentes</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Historial de notas escaneadas y su decisión.</div>
        </div>

        <form
            method="POST"
            action="{{ route('admin.source-scan-logs.destroy') }}"
            data-confirm-delete
            data-confirm-title="Borrar bitácora"
            data-confirm-text="Se eliminarán permanentemente los {{ $logs->count() }} registros de la bitácora. Esta acción no se puede deshacer."
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-light-danger text-nowrap" @disabled($logs->isEmpty())>
                <i class="ki-outline ki-trash fs-2"></i>
                Borrar bitácora
            </button>
        </form>
    </div>
@endsection

@section('content')
    <div class="source-scan-logs-page">
        <div class="card card-flush">
            <div class="card-body py-5">
                <div class="source-scan-logs-table-wrap">
                    <table class="table align-middle table-row-dashed fs-7 gy-3 admin-datatable source-scan-logs-table" data-page-length="50">
                        <colgroup>
                            <col class="source-log-col-note">
                            <col class="source-log-col-site">
                            <col class="source-log-col-outcome">
                            <col class="source-log-col-reason">
                            <col class="source-log-col-date">
                            <col class="source-log-col-action">
                        </colgroup>
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-8 text-uppercase">
                                <th>Nota</th>
                                <th>Sitio</th>
                                <th>Decisión</th>
                                <th>Motivo</th>
                                <th>Fecha</th>
                                <th class="text-end no-sort no-search">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @foreach ($logs as $log)
                                @php
                                    $filterMethodLabel = $filterMethodLabels[$log->filter_method] ?? ($log->filter_method ?: 'Sin método');
                                @endphp
                                <tr>
                                    <td data-label="Nota">
                                        <div class="source-log-title fw-bold text-gray-900">{{ $log->title ?: 'Elemento sin título' }}</div>
                                        @if ($log->url)
                                            <span class="visually-hidden">{{ $log->url }}</span>
                                        @endif
                                    </td>
                                    <td data-label="Sitio">
                                        <div class="source-log-site text-gray-800">{{ $log->sourceSite?->name ?: 'Sitio eliminado' }}</div>
                                    </td>
                                    <td data-label="Decisión">
                                        <span
                                            class="badge {{ $outcomeClasses[$log->outcome] ?? 'badge-light' }}"
                                            title="Método: {{ $filterMethodLabel }}"
                                        >
                                            {{ $log->outcomeLabel() }}
                                        </span>
                                    </td>
                                    <td data-label="Motivo">
                                        <div class="source-log-reason" title="{{ $log->reason ?: '' }}">{{ $log->reason ?: '—' }}</div>
                                    </td>
                                    <td class="text-nowrap text-muted" data-label="Fecha" data-order="{{ $log->scanned_at?->timestamp ?: 0 }}">
                                        {{ $log->scanned_at?->format('d/m/y H:i') ?: '—' }}
                                    </td>
                                    <td class="text-end" data-label="Acción">
                                        @if ($log->sourcePost)
                                            <a href="{{ route('admin.news.show', $log->sourcePost) }}" class="btn btn-icon btn-light btn-sm" title="Ver nota" aria-label="Ver nota">
                                                <i class="ki-outline ki-eye fs-3"></i>
                                            </a>
                                        @elseif ($log->url)
                                            <a href="{{ $log->url }}" target="_blank" rel="noopener noreferrer" class="btn btn-icon btn-light btn-sm" title="Abrir fuente" aria-label="Abrir fuente">
                                                <i class="ki-outline ki-exit-up fs-3"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
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
        .source-scan-logs-page {
            min-width: 0;
            padding: 0 20px 8px;
        }

        .source-scan-logs-page .card,
        .source-scan-logs-page .card-body,
        .source-scan-logs-table-wrap,
        .source-scan-logs-page .admin-datatable-wrapper {
            min-width: 0;
            overflow: visible;
        }

        .source-scan-logs-table {
            width: 100% !important;
            table-layout: fixed;
        }

        .source-log-col-note {
            width: 25%;
        }

        .source-log-col-site {
            width: 15%;
        }

        .source-log-col-outcome {
            width: 12%;
        }

        .source-log-col-reason {
            width: 31%;
        }

        .source-log-col-date {
            width: 12%;
        }

        .source-log-col-action {
            width: 5%;
        }

        .source-scan-logs-table th,
        .source-scan-logs-table td {
            min-width: 0 !important;
            padding: .65rem .5rem !important;
            overflow-wrap: anywhere;
        }

        .source-log-title,
        .source-log-site {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .source-log-reason {
            display: -webkit-box;
            overflow: hidden;
            line-height: 1.35;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .source-scan-logs-page .dataTables_filter {
            width: 100%;
            min-width: 0;
        }

        .source-scan-logs-page .dataTables_filter label {
            width: 100%;
            min-width: 0;
            justify-content: flex-end;
        }

        .source-scan-logs-page .dataTables_filter input {
            width: 280px !important;
            max-width: calc(100% - 68px);
            min-width: 0;
        }

        @media (max-width: 767.98px) {
            .source-scan-logs-page {
                padding-right: 16px;
                padding-left: 16px;
            }

            .source-scan-logs-page .dataTables_filter label {
                align-items: stretch;
                flex-direction: column;
            }

            .source-scan-logs-page .dataTables_filter input {
                width: 100% !important;
                max-width: 100%;
            }

            .source-scan-logs-table colgroup,
            .source-scan-logs-table thead {
                display: none;
            }

            .source-scan-logs-table,
            .source-scan-logs-table tbody,
            .source-scan-logs-table tr,
            .source-scan-logs-table td {
                display: block;
                width: 100% !important;
            }

            .source-scan-logs-table tr {
                margin-bottom: .75rem;
                padding: .5rem .75rem;
                border: 1px solid var(--bs-gray-200);
                border-radius: .65rem;
            }

            .source-scan-logs-table td {
                display: grid;
                grid-template-columns: 82px minmax(0, 1fr);
                gap: .75rem;
                align-items: center;
                border: 0 !important;
                padding: .42rem 0 !important;
                text-align: left !important;
            }

            .source-scan-logs-table td::before {
                color: var(--bs-gray-500);
                content: attr(data-label);
                font-size: .75rem;
                font-weight: 700;
                text-transform: uppercase;
            }

            .source-scan-logs-table td:last-child > * {
                justify-self: start;
            }
        }
    </style>
@endpush
