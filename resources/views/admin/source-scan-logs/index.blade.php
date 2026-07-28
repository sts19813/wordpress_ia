@extends('layouts.admin')

@section('title', 'Bitácora de fuentes | '.config('app.name'))

@php
    $outcomeClasses = [
        'accepted' => 'badge-light-success',
        'discarded' => 'badge-light-danger',
        'duplicate' => 'badge-light-warning',
        'invalid' => 'badge-light-dark',
    ];
@endphp

@section('toolbar')
    <div>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Bitácora de fuentes</h1>
        <div class="text-muted fw-semibold fs-7 pt-1">Historial de cada nota escaneada y la razón por la que aplicó, se descartó o ya era conocida.</div>
    </div>
@endsection

@section('content')
    <div class="card card-flush">
        <div class="card-header align-items-center gap-4 py-5">
            <div class="card-title w-100">
                <form method="GET" class="d-flex flex-column flex-xl-row align-items-xl-center gap-3 w-100">
                    <div class="position-relative">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4 top-50 translate-middle-y text-gray-500"></i>
                        <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-solid ps-12 w-275px" placeholder="Título, URL o motivo…">
                    </div>
                    <select name="source_site_id" class="form-select form-select-solid w-225px">
                        <option value="">Todos los sitios</option>
                        @foreach ($sourceSites as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('source_site_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <select name="outcome" class="form-select form-select-solid w-175px">
                        <option value="">Todas las decisiones</option>
                        @foreach ($outcomeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('outcome') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-solid w-150px" aria-label="Desde">
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-solid w-150px" aria-label="Hasta">
                    <button class="btn btn-light-primary" type="submit"><i class="ki-outline ki-filter fs-2"></i>Filtrar</button>
                    <a href="{{ route('admin.source-scan-logs.index') }}" class="btn btn-light">Limpiar</a>
                </form>
            </div>
        </div>

        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5 admin-datatable">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-300px">Nota escaneada</th>
                            <th class="min-w-170px">Sitio</th>
                            <th class="min-w-130px">Decisión</th>
                            <th class="min-w-300px">Motivo</th>
                            <th class="min-w-160px">Método</th>
                            <th class="min-w-160px">Fecha</th>
                            <th class="text-end min-w-90px no-sort no-search">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @foreach ($logs as $log)
                            <tr>
                                <td>
                                    <div class="fw-bold text-gray-900">{{ $log->title ?: 'Elemento sin título' }}</div>
                                    @if ($log->url)<div class="text-muted text-truncate mw-400px">{{ $log->url }}</div>@endif
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        @if (data_get($log->metadata, 'connection_type') === \App\Models\SourceSite::TYPE_AI_WEB)
                                            <span class="badge badge-light-warning" title="{{ data_get($log->metadata, 'structure_summary') }}">
                                                <i class="ki-outline ki-sparkles fs-7 me-1"></i>Conexión IA
                                            </span>
                                        @endif
                                        @foreach ($log->matched_topics ?: [] as $topic)
                                            <span class="badge badge-light-primary">{{ $topic }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>{{ $log->sourceSite?->name ?: 'Sitio eliminado' }}</td>
                                <td><span class="badge {{ $outcomeClasses[$log->outcome] ?? 'badge-light' }}">{{ $log->outcomeLabel() }}</span></td>
                                <td>{{ $log->reason ?: '-' }}</td>
                                <td>
                                    <span class="badge badge-light-info">
                                        {{ match($log->filter_method) {
                                            'ai' => 'Inteligencia artificial',
                                            'keyword_fallback' => 'Respaldo por palabras',
                                            'no_filter' => 'Sin filtro',
                                            'validation' => 'Validación',
                                            default => $log->filter_method ?: '-',
                                        } }}
                                    </span>
                                </td>
                                <td data-order="{{ $log->scanned_at?->timestamp ?: 0 }}">{{ $log->scanned_at?->format('d/m/Y H:i:s') ?: '-' }}</td>
                                <td class="text-end">
                                    @if ($log->sourcePost)
                                        <a href="{{ route('admin.news.show', $log->sourcePost) }}" class="btn btn-icon btn-light btn-sm" title="Ver nota"><i class="ki-outline ki-eye fs-3"></i></a>
                                    @elseif ($log->url)
                                        <a href="{{ $log->url }}" target="_blank" rel="noopener" class="btn btn-icon btn-light btn-sm" title="Abrir URL"><i class="ki-outline ki-exit-up fs-3"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
