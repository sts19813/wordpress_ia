@extends('layouts.admin')

@section('title', 'Programador | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Programador</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Consultas, publicaciones en proceso y resultados confirmados.</div>
        </div>
        <a href="{{ route('admin.ai-articles.create') }}" class="btn btn-primary">
            <i class="ki-outline ki-plus fs-2"></i>Nueva nota
        </a>
    </div>
@endsection

@section('content')
    @if ($workerMayBeStopped)
        <div class="alert alert-warning d-flex align-items-center py-4 mb-6">
            <i class="ki-outline ki-information-5 fs-2x text-warning me-3"></i>
            <div>
                <div class="fw-bold">Hay trabajos esperando desde hace más de 2 minutos.</div>
                <div class="fs-8">El procesador de cola podría estar detenido.</div>
            </div>
        </div>
    @endif

    <div class="card card-flush mb-7 scheduler-sources-card">
        <div class="card-header min-h-60px align-items-center">
            <div class="card-title">
                <h3 class="fw-bold mb-0">Próximas consultas de sitios fuente</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <table class="table align-middle table-row-dashed fs-7 gy-3 scheduler-sources-table">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-8 text-uppercase">
                        <th>Sitio fuente</th>
                        <th>Frecuencia</th>
                        <th>Próxima consulta</th>
                        <th>Automatización</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody class="fw-semibold text-gray-700">
                    @forelse ($sourceSites as $sourceSite)
                        @php
                            $activeTask = $activeSourceTasks->get($sourceSite->id);
                            $isDue = $sourceSite->active && $sourceSite->next_scan_at?->lte(now());
                            $frequencyHours = max(1, (int) ceil($sourceSite->frequency_minutes / 60));
                            $automation = ! $sourceSite->auto_generate
                                ? 'Solo obtener notas'
                                : ($sourceSite->auto_publish && $sourceSite->wordpressSite
                                    ? 'IA · Publica en '.$sourceSite->wordpressSite->name
                                    : 'IA · Guarda borrador');
                        @endphp
                        <tr>
                            <td data-label="Sitio fuente">
                                <a href="{{ route('admin.source-sites.edit', $sourceSite) }}" class="d-block text-gray-900 text-hover-primary fw-bold text-truncate">
                                    {{ $sourceSite->name }}
                                </a>
                            </td>
                            <td data-label="Frecuencia" class="text-nowrap">
                                {{ $frequencyHours }}h / {{ $sourceSite->max_posts_per_scan }} por consulta
                            </td>
                            <td data-label="Próxima consulta" class="text-nowrap">
                                @if (! $sourceSite->active)
                                    <span class="badge badge-light-secondary">Inactivo</span>
                                @elseif ($isDue)
                                    <span class="badge badge-light-warning">Vencida · ejecutar ahora</span>
                                @else
                                    <span class="fw-bold text-gray-900">
                                        {{ $sourceSite->next_scan_at?->format('d/m/y H:i') ?: 'Ahora' }}
                                    </span>
                                @endif
                            </td>
                            <td data-label="Automatización">
                                <span class="d-block text-truncate">{{ $automation }}</span>
                            </td>
                            <td data-label="Acción" class="text-end">
                                @if ($activeTask)
                                    <a href="{{ route('admin.scheduler.index', ['task' => $activeTask->id]) }}" class="badge badge-light-{{ $activeTask->status === \App\Models\Scheduler::STATUS_RUNNING ? 'primary' : 'warning' }}">
                                        {{ $activeTask->statusLabel() }}
                                    </a>
                                @else
                                    <form method="POST" action="{{ route('admin.scheduler.sources.run', $sourceSite) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-light-primary" @disabled(! $sourceSite->active)>
                                            <i class="ki-outline ki-play fs-4"></i>Consultar
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-8">No hay sitios fuente configurados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="fw-bold fs-4 mb-1">Ejecuciones</h2>
            <div class="text-muted fs-7">Sólo se muestran procesos activos, fallidos y publicaciones confirmadas.</div>
        </div>
        <div class="nav nav-pills">
            <a href="{{ route('admin.scheduler.index', ['tab' => 'active']) }}"
               class="nav-link {{ $activeTab === 'active' ? 'active' : '' }}">
                En proceso
                <span class="badge ms-2 {{ $activeTab === 'active' ? 'badge-light' : 'badge-light-primary' }}">{{ $activeTasks->count() }}</span>
            </a>
            <a href="{{ route('admin.scheduler.index', ['tab' => 'completed']) }}"
               class="nav-link {{ $activeTab === 'completed' ? 'active' : '' }}">
                Completados
                <span class="badge ms-2 {{ $activeTab === 'completed' ? 'badge-light' : 'badge-light-success' }}">{{ $completedTasks->count() }}</span>
            </a>
        </div>
    </div>

    @if ($activeTab === 'active')
        <div class="d-flex flex-column gap-3" id="queue-task-list">
            @forelse ($activeTasks as $task)
                @include('admin.scheduler._task', ['task' => $task, 'completed' => false])
            @empty
                <div class="card card-flush">
                    <div class="card-body text-center py-10">
                        <i class="ki-outline ki-check-circle fs-2x text-success mb-3"></i>
                        <div class="fw-bold text-gray-800">No hay procesos pendientes</div>
                    </div>
                </div>
            @endforelse
        </div>
    @else
        <div class="d-flex flex-column gap-3" id="queue-task-list">
            @forelse ($completedTasks as $task)
                @include('admin.scheduler._task', ['task' => $task, 'completed' => true])
            @empty
                <div class="card card-flush">
                    <div class="card-body text-center py-10">
                        <i class="ki-outline ki-send fs-2x text-muted mb-3"></i>
                        <div class="fw-bold text-gray-800">Aún no hay publicaciones completadas</div>
                    </div>
                </div>
            @endforelse
        </div>
    @endif
@endsection

@push('styles')
    <style>
        .scheduler-sources-table {
            width: 100%;
            table-layout: fixed;
        }

        .scheduler-sources-table th:nth-child(1) { width: 20%; }
        .scheduler-sources-table th:nth-child(2) { width: 19%; }
        .scheduler-sources-table th:nth-child(3) { width: 20%; }
        .scheduler-sources-table th:nth-child(4) { width: 25%; }
        .scheduler-sources-table th:nth-child(5) { width: 16%; }

        .scheduler-sources-table td {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .scheduler-task-card {
            box-shadow: 0 4px 16px rgba(15, 23, 42, .035);
        }

        .scheduler-task-title {
            font-size: .95rem;
        }

        .task-progress-wrap {
            width: min(100%, 520px);
        }

        .task-progress-wrap .progress {
            height: 4px;
        }

        .scheduler-task-actions .btn {
            white-space: nowrap;
        }

        @media (max-width: 991.98px) {
            .scheduler-sources-table,
            .scheduler-sources-table tbody,
            .scheduler-sources-table tr,
            .scheduler-sources-table td {
                display: block;
                width: 100% !important;
            }

            .scheduler-sources-table thead {
                display: none;
            }

            .scheduler-sources-table tbody tr {
                padding: .75rem 0;
            }

            .scheduler-sources-table tbody td {
                display: grid;
                grid-template-columns: minmax(7.5rem, 32%) minmax(0, 1fr);
                gap: .75rem;
                padding: .3rem 0 !important;
                text-align: left !important;
                border: 0;
            }

            .scheduler-sources-table tbody td::before {
                content: attr(data-label);
                color: var(--bs-gray-600);
                font-size: .7rem;
                font-weight: 700;
                text-transform: uppercase;
            }

            .scheduler-sources-table tbody td:last-child form,
            .scheduler-sources-table tbody td:last-child a {
                justify-self: start;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-manual-execute-form]').forEach(function (form) {
                form.addEventListener('submit', function () {
                    const button = form.querySelector('button[type="submit"]');
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Ejecutando';
                });
            });

            const tasks = Array.from(document.querySelectorAll('.queue-task[data-active="1"]'));
            const selected = document.getElementById('task-{{ $selectedTaskId }}');
            selected?.scrollIntoView({ behavior: 'smooth', block: 'center' });

            if (!tasks.length) {
                return;
            }

            const poll = async function () {
                let finished = false;

                await Promise.all(tasks.map(async function (card) {
                    try {
                        const response = await fetch(card.dataset.statusUrl, {
                            headers: { 'Accept': 'application/json' },
                        });

                        if (!response.ok) {
                            return;
                        }

                        const task = await response.json();
                        const badge = card.querySelector('.task-status');
                        const statusClasses = {
                            queued: 'badge-light-warning',
                            running: 'badge-light-primary',
                            failed: 'badge-light-danger',
                        };

                        badge.textContent = task.status_label;
                        badge.classList.remove('badge-light-warning', 'badge-light-primary', 'badge-light-danger');
                        badge.classList.add(statusClasses[task.status] || 'badge-light');
                        card.querySelector('.task-step').textContent = task.step || '';

                        const progressWrap = card.querySelector('.task-progress-wrap');
                        if (progressWrap) {
                            if (task.progress >= 100) {
                                progressWrap.classList.add('d-none');
                            } else {
                                const progress = progressWrap.querySelector('.task-progress');
                                progress.style.width = task.progress + '%';
                                progress.setAttribute('aria-valuenow', task.progress);
                                progressWrap.querySelector('.task-progress-label').textContent = task.progress + '%';
                            }
                        }

                        if (task.article_url) {
                            const link = card.querySelector('.task-article-link');
                            link.href = task.article_url;
                            link.classList.remove('d-none');
                        }

                        if (task.last_error) {
                            const error = card.querySelector('.task-error');
                            error.textContent = task.last_error;
                            error.classList.remove('d-none');
                        }

                        if (task.status === 'completed' || task.status === 'failed') {
                            finished = true;
                        }
                    } catch (error) {
                        // Un fallo temporal de red no afecta al trabajo del servidor.
                    }
                }));

                if (finished) {
                    window.location.reload();
                }
            };

            window.setInterval(poll, 4000);
        });
    </script>
@endpush
