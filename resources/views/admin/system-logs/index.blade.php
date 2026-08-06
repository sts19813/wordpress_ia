@extends('layouts.admin')

@section('title', 'Logs | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Logs</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Errores del sistema y publicaciones confirmadas en destinos externos.</div>
        </div>

        <form
            method="POST"
            action="{{ route('admin.system-logs.destroy') }}"
            data-confirm-delete
            data-confirm-title="Borrar log del sistema"
            data-confirm-text="Se eliminarán permanentemente los {{ $logCount }} registros del log del sistema. Esta acción no se puede deshacer."
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-light-danger text-nowrap" @disabled($logCount === 0)>
                <i class="ki-outline ki-trash fs-2"></i>
                Borrar log
            </button>
        </form>
    </div>
@endsection

@section('content')
    <div class="system-logs-page">
        <div class="card card-flush">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="fw-bold mb-0">Historial</h3>
                </div>
            </div>
            <div class="card-body pt-0">
                <table class="table align-middle table-row-dashed fs-7 gy-3 admin-datatable system-logs-table" data-page-length="50">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                            <th>Evento</th>
                            <th>Origen</th>
                            <th>Mensaje</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 fw-semibold">
                        @foreach ($logs as $log)
                            <tr>
                                <td data-label="Evento">
                                    <span class="badge {{ $log->level === \App\Models\SystemLog::LEVEL_SUCCESS ? 'badge-light-success' : 'badge-light-danger' }}">
                                        {{ $log->levelLabel() }}
                                    </span>
                                </td>
                                <td data-label="Origen">
                                    <span class="fw-bold text-gray-900">{{ $log->source ?: 'Sistema' }}</span>
                                </td>
                                <td data-label="Mensaje">
                                    <span>{{ $log->message }}</span>
                                    @if (data_get($log->context, 'remote_url'))
                                        <a href="{{ data_get($log->context, 'remote_url') }}" target="_blank" rel="noopener noreferrer" class="ms-2 text-primary text-nowrap">
                                            Ver publicación <i class="ki-outline ki-exit-up fs-7"></i>
                                        </a>
                                    @endif
                                </td>
                                <td data-label="Fecha" data-order="{{ $log->occurred_at?->timestamp ?: 0 }}">
                                    <span class="system-log-date text-muted">{{ $log->occurred_at?->format('d/m/y H:i') ?: '-' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .system-logs-table {
            width: 100% !important;
            table-layout: fixed;
        }

        .system-logs-table th:nth-child(1) { width: 12%; }
        .system-logs-table th:nth-child(2) { width: 16%; }
        .system-logs-table th:nth-child(3) { width: 57%; }
        .system-logs-table th:nth-child(4) { width: 15%; }

        .system-logs-table td {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .system-log-date {
            font-size: .75rem;
            white-space: nowrap;
        }

        .system-logs-page .admin-datatable-wrapper,
        .system-logs-page .admin-datatable-wrapper > .row,
        .system-logs-page .admin-datatable-wrapper > .row > [class*="col-"] {
            min-width: 0;
            max-width: 100%;
        }

        .system-logs-page .dataTables_filter {
            width: 100%;
            max-width: 340px;
        }

        .system-logs-page .dataTables_filter label {
            width: 100%;
            min-width: 0;
        }

        .system-logs-page .dataTables_filter input {
            width: 100% !important;
            min-width: 0 !important;
            max-width: 260px;
            flex: 1 1 auto;
        }

        @media (max-width: 767.98px) {
            .system-logs-page .dataTables_filter {
                max-width: none;
            }

            .system-logs-page .dataTables_filter input {
                max-width: none;
            }

            .system-logs-table,
            .system-logs-table tbody,
            .system-logs-table tr,
            .system-logs-table td {
                display: block;
                width: 100% !important;
            }

            .system-logs-table thead {
                display: none;
            }

            .system-logs-table tbody tr {
                padding: .75rem 0;
            }

            .system-logs-table tbody td {
                display: grid;
                grid-template-columns: minmax(5rem, 25%) minmax(0, 1fr);
                gap: .65rem;
                padding: .25rem 0 !important;
                border: 0;
            }

            .system-logs-table tbody td::before {
                content: attr(data-label);
                color: var(--bs-gray-600);
                font-size: .7rem;
                font-weight: 700;
                text-transform: uppercase;
            }
        }
    </style>
@endpush
