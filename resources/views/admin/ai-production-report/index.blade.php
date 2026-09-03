@extends('layouts.admin')

@section('title', 'Resumen de producción IA | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Resumen de producción IA</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Notas generadas, publicaciones, destinos y fallos por día.</div>
        </div>
        <a href="{{ route('admin.ai-production-report.export', request()->only(['date_from', 'date_to', 'company_id', 'publication_status'])) }}"
            class="btn btn-success text-nowrap">
            <i class="ki-outline ki-file-down fs-2"></i>Exportar Excel
        </a>
    </div>
@endsection

@section('content')
    <div class="production-report-page">
        <div class="card card-flush mb-7">
            <div class="card-body py-5">
                <form method="GET" action="{{ route('admin.ai-production-report.index') }}" class="row g-4 align-items-end">
                    <div class="col-sm-6 col-lg-2">
                        <label for="date_from" class="form-label fw-bold">Desde</label>
                        <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control form-control-solid">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="date_to" class="form-label fw-bold">Hasta</label>
                        <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control form-control-solid">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label for="company_id" class="form-label fw-bold">Empresa</label>
                        <select id="company_id" name="company_id" class="form-select form-select-solid">
                            <option value="">Todas las empresas</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected($filters['company_id'] === $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label for="publication_status" class="form-label fw-bold">Publicación</label>
                        <select id="publication_status" name="publication_status" class="form-select form-select-solid">
                            <option value="all" @selected($filters['publication_status'] === 'all')>Todas las notas</option>
                            <option value="published" @selected($filters['publication_status'] === 'published')>Sólo publicadas</option>
                            <option value="unpublished" @selected($filters['publication_status'] === 'unpublished')>Sólo sin publicar</option>
                        </select>
                    </div>
                    <div class="col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="ki-outline ki-filter fs-3"></i>Aplicar</button>
                        <a href="{{ route('admin.ai-production-report.index') }}" class="btn btn-light btn-icon" title="Restablecer filtros" aria-label="Restablecer filtros">
                            <i class="ki-outline ki-cross fs-3"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if ($summary['latest_failure'])
            <div class="report-alert mb-7">
                <span class="report-alert-icon"><i class="ki-outline ki-information-5"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-bold text-gray-900">{{ number_format($summary['failed_generations']) }} intentos de generación fallidos en el periodo</div>
                    <div class="text-gray-600 fs-7 mt-1 text-truncate">
                        Último fallo {{ $summary['latest_failure']->created_at->diffForHumans() }}: {{ $summary['latest_failure']->generation_error ?: 'Sin detalle registrado.' }}
                    </div>
                </div>
                <a href="#fallos-generacion" class="btn btn-sm btn-light-danger">Ver fallos</a>
            </div>
        @endif

        <div class="row g-5 mb-7">
            <div class="col-sm-6 col-xl-3">
                <div class="card card-flush report-kpi report-kpi-blue h-100">
                    <div class="card-body">
                        <div class="report-kpi-top"><span class="report-kpi-icon"><i class="ki-outline ki-abstract-26"></i></span><span>Periodo</span></div>
                        <div class="report-kpi-value">{{ number_format($summary['generated']) }}</div>
                        <div class="report-kpi-label">Notas generadas con IA</div>
                        <div class="report-kpi-detail">Sólo generaciones exitosas</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-flush report-kpi report-kpi-green h-100">
                    <div class="card-body">
                        <div class="report-kpi-top"><span class="report-kpi-icon"><i class="ki-outline ki-check-circle"></i></span><span>Notas únicas</span></div>
                        <div class="report-kpi-value">{{ number_format($summary['published']) }}</div>
                        <div class="report-kpi-label">Notas publicadas</div>
                        <div class="report-kpi-detail">{{ number_format($summary['publication_sends']) }} envíos exitosos</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-flush report-kpi report-kpi-orange h-100">
                    <div class="card-body">
                        <div class="report-kpi-top"><span class="report-kpi-icon"><i class="ki-outline ki-document"></i></span><span>Pendientes</span></div>
                        <div class="report-kpi-value">{{ number_format($summary['unpublished']) }}</div>
                        <div class="report-kpi-label">Notas sin publicar</div>
                        <div class="report-kpi-detail">Sin ningún envío exitoso</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card card-flush report-kpi report-kpi-purple h-100">
                    <div class="card-body">
                        <div class="report-kpi-top"><span class="report-kpi-icon"><i class="ki-outline ki-chart-line-up-2"></i></span><span>Efectividad</span></div>
                        <div class="report-kpi-value">{{ $summary['publication_rate'] === null ? '—' : number_format($summary['publication_rate'], 1).'%' }}</div>
                        <div class="report-kpi-label">Tasa de publicación</div>
                        <div class="report-kpi-detail">Publicadas / generadas</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 mb-7">
            <div class="col-xl-8">
                <div class="card card-flush h-100">
                    <div class="card-header align-items-center border-0 pt-5">
                        <div class="card-title d-block">
                            <h2 class="fw-bold text-gray-900 fs-3 mb-1">Producción por día</h2>
                            <div class="text-muted fs-7">La publicación se atribuye al día en que se generó la nota.</div>
                        </div>
                    </div>
                    <div class="card-body pt-3">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-7 gy-3 report-daily-table">
                                <thead>
                                    <tr class="text-muted fw-bold fs-8 text-uppercase">
                                        <th>Fecha</th>
                                        <th class="text-end">Generadas</th>
                                        <th class="text-end">Publicadas</th>
                                        <th class="text-end">Sin publicar</th>
                                        <th class="text-end">Envíos</th>
                                        <th class="text-end">Fallos</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-700">
                                    @forelse ($daily as $day)
                                        <tr>
                                            <td class="text-gray-900 fw-bold text-capitalize">{{ $day['date']->locale('es')->isoFormat('ddd D MMM YYYY') }}</td>
                                            <td class="text-end">{{ number_format($day['generated']) }}</td>
                                            <td class="text-end text-success">{{ number_format($day['published']) }}</td>
                                            <td class="text-end {{ $day['unpublished'] ? 'text-warning' : 'text-muted' }}">{{ number_format($day['unpublished']) }}</td>
                                            <td class="text-end">{{ number_format($day['publication_sends']) }}</td>
                                            <td class="text-end {{ $day['failed'] ? 'text-danger' : 'text-muted' }}">{{ number_format($day['failed']) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-10">No hay actividad para los filtros seleccionados.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card card-flush h-100">
                    <div class="card-header align-items-center border-0 pt-5">
                        <div class="card-title d-block">
                            <h2 class="fw-bold text-gray-900 fs-3 mb-1">Cobertura de destinos</h2>
                            <div class="text-muted fs-7">Perfiles que recibieron notas del periodo.</div>
                        </div>
                    </div>
                    <div class="card-body pt-3">
                        @forelse ($destinations->take(8) as $destination)
                            <div class="report-destination-row">
                                <span class="report-destination-icon {{ $destination['historical'] ? 'is-historical' : '' }}">
                                    <i class="ki-outline {{ $destination['type'] === 'facebook_page' ? 'ki-facebook' : ($destination['type'] === 'instagram' ? 'ki-instagram' : 'ki-global') }}"></i>
                                </span>
                                <span class="flex-grow-1 min-w-0">
                                    <span class="d-block fw-bold text-gray-900 text-truncate">{{ $destination['name'] }}</span>
                                    <span class="d-block text-muted fs-8 text-truncate">{{ $destination['company'] ?: $destination['type_label'] }}</span>
                                </span>
                                <span class="text-end">
                                    <strong class="d-block text-gray-900">{{ number_format($destination['article_count']) }}</strong>
                                    <span class="text-muted fs-9">notas</span>
                                </span>
                            </div>
                        @empty
                            <div class="text-center text-muted py-10">No hay destinos en este periodo.</div>
                        @endforelse

                        @if ($summary['historical_destination_sends'] > 0)
                            <div class="alert alert-light-warning fs-8 mt-5 mb-0">
                                {{ number_format($summary['historical_destination_sends']) }} envíos conservan su URL, pero el perfil asociado ya fue eliminado.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-flush mb-7">
            <div class="card-header align-items-center border-0 pt-5">
                <div class="card-title d-block">
                    <h2 class="fw-bold text-gray-900 fs-3 mb-1">Detalle de notas</h2>
                    <div class="text-muted fs-7">Busca por título, empresa, estado o perfil de publicación.</div>
                </div>
            </div>
            <div class="card-body pt-3">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4 admin-datatable report-notes-table" data-page-length="25">
                        <thead>
                            <tr class="text-muted fw-bold fs-8 text-uppercase">
                                <th>Fecha</th>
                                <th>Nota</th>
                                <th>Empresa</th>
                                <th>Estado</th>
                                <th>Publicada en</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @foreach ($articles as $article)
                                <tr>
                                    <td class="text-nowrap" data-order="{{ $article['generated_at']->timestamp }}">
                                        <span class="d-block text-gray-900">{{ $article['generated_at']->format('d/m/Y') }}</span>
                                        <span class="text-muted fs-8">{{ $article['generated_at']->format('H:i') }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.ai-articles.show', $article['id']) }}" class="text-gray-900 text-hover-primary fw-bold">{{ $article['title'] }}</a>
                                        <span class="d-block text-muted fs-9 mt-1">#{{ $article['id'] }}{{ $article['model'] ? ' · '.$article['model'] : '' }}</span>
                                    </td>
                                    <td>{{ $article['company'] ?: 'Sin empresa' }}</td>
                                    <td>
                                        <span class="badge {{ $article['published'] ? 'badge-light-success' : 'badge-light-warning' }}">
                                            {{ $article['published'] ? 'Publicada' : 'Sin publicar' }}
                                        </span>
                                        @if ($article['publication_count'])
                                            <span class="d-block text-muted fs-9 mt-1">{{ $article['publication_count'] }} {{ Illuminate\Support\Str::plural('envío', $article['publication_count']) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            @forelse ($article['destinations'] as $destination)
                                                @if ($destination['url'])
                                                    <a href="{{ $destination['url'] }}" target="_blank" rel="noopener noreferrer"
                                                        class="badge {{ $destination['historical'] ? 'badge-light-warning' : 'badge-light-primary' }} text-hover-primary"
                                                        title="{{ $destination['company'] ?: $destination['type_label'] }}">
                                                        {{ $destination['name'] }} <i class="ki-outline ki-exit-up fs-8 ms-1"></i>
                                                    </a>
                                                @else
                                                    <span class="badge {{ $destination['historical'] ? 'badge-light-warning' : 'badge-light-primary' }}">{{ $destination['name'] }}</span>
                                                @endif
                                            @empty
                                                <span class="text-muted">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="fallos-generacion" class="card card-flush">
            <div class="card-header align-items-center border-0 pt-5">
                <div class="card-title d-block">
                    <h2 class="fw-bold text-gray-900 fs-3 mb-1">Fallos de generación</h2>
                    <div class="text-muted fs-7">Intentos que no produjeron una nota y no se incluyen en el total generado.</div>
                </div>
                <span class="badge badge-light-danger">{{ number_format($summary['failed_generations']) }}</span>
            </div>
            <div class="card-body pt-3">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead><tr class="text-muted fw-bold fs-8 text-uppercase"><th>Fecha</th><th>Nota</th><th>Motivo</th></tr></thead>
                        <tbody class="fw-semibold text-gray-700">
                            @forelse ($failures as $failure)
                                <tr>
                                    <td class="text-nowrap">{{ $failure->created_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $failure->title ?: 'Generación sin título #'.$failure->id }}</td>
                                    <td class="text-danger">{{ $failure->generation_error ?: 'Sin detalle registrado.' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-8">No hubo fallos de generación en este periodo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .production-report-page { min-width: 0; padding-bottom: 10px; }
        .report-alert { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border: 1px solid #ffd5d5; border-radius: .85rem; background: #fff7f7; }
        .report-alert-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 42px; height: 42px; border-radius: .7rem; color: var(--bs-danger); background: var(--bs-danger-light); font-size: 1.5rem; }
        .report-kpi { border: 1px solid var(--bs-gray-200); overflow: hidden; }
        .report-kpi::before { content: ''; display: block; height: 4px; background: var(--report-accent); }
        .report-kpi-blue { --report-accent: #075fd1; --report-soft: #eaf3ff; }
        .report-kpi-green { --report-accent: #12a16b; --report-soft: #e9fbf3; }
        .report-kpi-orange { --report-accent: #e68a00; --report-soft: #fff5df; }
        .report-kpi-purple { --report-accent: #7857d8; --report-soft: #f2edff; }
        .report-kpi-top { display: flex; align-items: center; justify-content: space-between; color: var(--bs-gray-600); font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .report-kpi-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: .7rem; color: var(--report-accent); background: var(--report-soft); font-size: 1.4rem; }
        .report-kpi-value { margin-top: 1.25rem; color: var(--bs-gray-900); font-size: 2rem; line-height: 1; font-weight: 800; }
        .report-kpi-label { margin-top: .55rem; color: var(--bs-gray-800); font-weight: 700; }
        .report-kpi-detail { margin-top: .3rem; color: var(--bs-gray-600); font-size: .75rem; }
        .report-destination-row { display: flex; align-items: center; gap: .85rem; padding: .8rem 0; border-bottom: 1px dashed var(--bs-gray-300); }
        .report-destination-row:last-of-type { border-bottom: 0; }
        .report-destination-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 38px; height: 38px; border-radius: .65rem; color: var(--bs-primary); background: var(--bs-primary-light); font-size: 1.25rem; }
        .report-destination-icon.is-historical { color: var(--bs-warning); background: var(--bs-warning-light); }
        .report-notes-table { width: 100% !important; }
        .report-notes-table th:nth-child(1) { width: 10%; }
        .report-notes-table th:nth-child(2) { width: 31%; }
        .report-notes-table th:nth-child(3) { width: 14%; }
        .report-notes-table th:nth-child(4) { width: 12%; }
        .report-notes-table th:nth-child(5) { width: 33%; }
        .report-notes-table td { min-width: 0; overflow-wrap: anywhere; vertical-align: top; }
        .report-notes-table .badge { white-space: normal; text-align: left; line-height: 1.3; }

        @media (max-width: 767.98px) {
            .report-alert { align-items: flex-start; flex-wrap: wrap; }
            .report-alert .btn { margin-left: 58px; }
            .report-daily-table { min-width: 650px; }
            .report-notes-table, .report-notes-table tbody, .report-notes-table tr, .report-notes-table td { display: block; width: 100% !important; }
            .report-notes-table thead { display: none; }
            .report-notes-table tr { padding: .8rem 0; }
            .report-notes-table td { border: 0 !important; padding: .35rem 0 !important; }
        }
    </style>
@endpush
