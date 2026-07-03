@extends('layouts.admin')

@section('title', 'Programador y colas | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Programador y colas</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Seguimiento de procesos en segundo plano, reintentos y errores.</div>
        </div>
        <a href="{{ route('admin.ai-articles.create') }}" class="btn btn-primary"><i class="ki-outline ki-plus fs-2"></i>Nueva nota</a>
    </div>
@endsection

@section('content')
    @if ($workerMayBeStopped)
        <div class="alert alert-warning d-flex align-items-start mb-7">
            <i class="ki-outline ki-information-5 fs-2hx text-warning me-4"></i>
            <div>
                <div class="fw-bold mb-1">Hay trabajos esperando desde hace más de 2 minutos</div>
                <div>El procesador de cola podría estar detenido. En Hostinger configura un cron cada minuto para ejecutar <code>php artisan schedule:run</code>.</div>
            </div>
        </div>
    @endif

    <div class="row g-5 mb-7">
        @php
            $summary = [
                'queued' => ['En cola', 'ki-time', 'warning'],
                'running' => ['Procesando', 'ki-arrows-circle', 'primary'],
                'completed' => ['Completados', 'ki-check-circle', 'success'],
                'failed' => ['Con error', 'ki-cross-circle', 'danger'],
            ];
        @endphp
        @foreach ($summary as $status => [$label, $icon, $color])
            <div class="col-6 col-xl-3">
                <div class="card card-flush h-100">
                    <div class="card-body d-flex align-items-center gap-4 py-6">
                        <span class="symbol symbol-45px"><span class="symbol-label bg-light-{{ $color }}"><i class="ki-outline {{ $icon }} fs-2x text-{{ $color }}"></i></span></span>
                        <div><div class="fs-2 fw-bold text-gray-900">{{ $counts[$status] ?? 0 }}</div><div class="text-muted fw-semibold fs-7">{{ $label }}</div></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card card-flush mb-7">
        <div class="card-header align-items-center">
            <div class="card-title"><h3 class="fw-bold mb-0">Estado del procesador</h3></div>
            <div class="card-toolbar d-flex gap-2">
                <span class="badge badge-light-primary">{{ $databaseQueueSize }} en la cola técnica</span>
                @if ($failedQueueSize)<span class="badge badge-light-danger">{{ $failedQueueSize }} fallo(s) técnico(s)</span>@endif
            </div>
        </div>
        <div class="card-body pt-0 text-muted fs-7">
            La página se actualiza mientras hay actividad. Puedes cerrarla: los trabajos continúan en el servidor y conservan su bitácora.
        </div>
    </div>

    <div class="d-flex flex-column gap-5" id="queue-task-list">
        @forelse ($tasks as $task)
            @php
                $badge = match ($task->status) {
                    'completed' => 'badge-light-success',
                    'failed' => 'badge-light-danger',
                    'running' => 'badge-light-primary',
                    default => 'badge-light-warning',
                };
                $isActive = in_array($task->status, ['queued', 'running'], true);
                $mainImage = $task->article?->images?->firstWhere('type', 'main');
            @endphp
            <div class="card card-flush queue-task {{ $selectedTaskId === $task->id ? 'border border-primary' : '' }}"
                 id="task-{{ $task->id }}"
                 data-active="{{ $isActive ? '1' : '0' }}"
                 data-status-url="{{ route('admin.scheduler.status', $task) }}">
                <div class="card-body p-6">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-5">
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center flex-wrap gap-3 mb-2">
                                <span class="fw-bold fs-5 text-gray-900">{{ $task->name }} #{{ $task->id }}</span>
                                <span class="badge task-status {{ $badge }}">{{ $task->statusLabel() }}</span>
                                @if ($mainImage?->status === 'failed')<span class="badge badge-light-warning">Texto listo · imagen fallida</span>@endif
                            </div>
                            <div class="task-step text-muted fw-semibold mb-4">{{ $task->step }}</div>
                            <div class="progress h-8px bg-light mb-2">
                                <div class="progress-bar bg-{{ $task->status === 'failed' ? 'danger' : ($task->status === 'completed' ? 'success' : 'primary') }} task-progress"
                                     role="progressbar" style="width: {{ $task->progress }}%" aria-valuenow="{{ $task->progress }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="d-flex flex-wrap gap-4 text-muted fs-8">
                                <span class="task-progress-label">{{ $task->progress }}%</span>
                                <span class="task-attempts">Intentos: {{ $task->attempts }}/{{ $task->max_attempts }}</span>
                                <span>Creado: {{ $task->created_at->format('d/m/Y H:i:s') }}</span>
                                @if ($task->finished_at)<span>Finalizado: {{ $task->finished_at->format('d/m/Y H:i:s') }}</span>@endif
                            </div>
                            @if ($task->last_error)
                                <div class="task-error alert alert-danger py-3 px-4 mt-4 mb-0 fs-7">{{ $task->last_error }}</div>
                            @else
                                <div class="task-error d-none alert alert-danger py-3 px-4 mt-4 mb-0 fs-7"></div>
                            @endif
                        </div>
                        <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                            @if ($task->status === 'queued')
                                <form method="POST" action="{{ route('admin.scheduler.execute', $task) }}" data-manual-execute-form>
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="ki-outline ki-play fs-3"></i>Ejecutar ahora</button>
                                </form>
                            @endif
                            @if ($task->article)
                                <a href="{{ route('admin.ai-articles.show', $task->article) }}" class="btn btn-sm btn-light-primary task-article-link"><i class="ki-outline ki-eye fs-3"></i>Ver borrador</a>
                            @else
                                <a href="#" class="btn btn-sm btn-light-primary task-article-link d-none"><i class="ki-outline ki-eye fs-3"></i>Ver borrador</a>
                            @endif
                            @if ($task->status === 'failed')
                                <form method="POST" action="{{ route('admin.scheduler.retry', $task) }}" class="task-retry-form">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light-warning"><i class="ki-outline ki-arrows-circle fs-3"></i>Reintentar</button>
                                </form>
                            @endif
                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#task-log-{{ $task->id }}" aria-expanded="{{ $selectedTaskId === $task->id ? 'true' : 'false' }}">
                                <i class="ki-outline ki-code fs-3"></i>Bitácora
                            </button>
                        </div>
                    </div>

                    <div class="collapse {{ $selectedTaskId === $task->id ? 'show' : '' }} mt-5" id="task-log-{{ $task->id }}">
                        <div class="rounded bg-light p-4 task-events">
                            @forelse (array_reverse($task->events ?: []) as $event)
                                @php($eventColor = ['success' => 'success', 'warning' => 'warning', 'error' => 'danger'][$event['level'] ?? 'info'] ?? 'primary')
                                <div class="d-flex gap-3 {{ $loop->last ? '' : 'mb-3 pb-3 border-bottom' }}">
                                    <span class="bullet bullet-dot bg-{{ $eventColor }} mt-2"></span>
                                    <div><div class="text-gray-800 fs-7">{{ $event['message'] ?? '' }}</div><div class="text-muted fs-9">{{ isset($event['at']) ? \Illuminate\Support\Carbon::parse($event['at'])->format('d/m/Y H:i:s') : '' }}</div></div>
                                </div>
                            @empty
                                <div class="text-muted fs-7">Todavía no hay eventos.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="card card-flush"><div class="card-body text-center py-15"><i class="ki-outline ki-calendar-tick fs-3x text-muted mb-4"></i><div class="fw-bold text-gray-800 mb-2">Aún no hay procesos</div><div class="text-muted">Al generar una nota, su progreso aparecerá aquí.</div></div></div>
        @endforelse
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-manual-execute-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Ejecutando...';
        });
    });

    const tasks = Array.from(document.querySelectorAll('.queue-task[data-active="1"]'));
    const selected = document.getElementById('task-{{ $selectedTaskId }}');
    selected?.scrollIntoView({ behavior: 'smooth', block: 'center' });

    if (!tasks.length) return;

    const poll = async function () {
        let finished = false;
        await Promise.all(tasks.map(async function (card) {
            try {
                const response = await fetch(card.dataset.statusUrl, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) return;
                const task = await response.json();
                const statusBadge = card.querySelector('.task-status');
                const statusClasses = {
                    queued: 'badge-light-warning',
                    running: 'badge-light-primary',
                    completed: 'badge-light-success',
                    failed: 'badge-light-danger',
                };
                statusBadge.textContent = task.status_label;
                statusBadge.classList.remove('badge-light-warning', 'badge-light-primary', 'badge-light-success', 'badge-light-danger');
                statusBadge.classList.add(statusClasses[task.status] || 'badge-light');
                card.querySelector('.task-step').textContent = task.step || '';
                card.querySelector('.task-progress').style.width = task.progress + '%';
                card.querySelector('.task-progress').setAttribute('aria-valuenow', task.progress);
                card.querySelector('.task-progress-label').textContent = task.progress + '%';
                card.querySelector('.task-attempts').textContent = 'Intentos: ' + task.attempts + '/' + task.max_attempts;
                const eventList = card.querySelector('.task-events');
                eventList.replaceChildren(...task.events.slice().reverse().map(function (event) {
                    const row = document.createElement('div');
                    row.className = 'd-flex gap-3 mb-3 pb-3 border-bottom';
                    const dot = document.createElement('span');
                    const colors = { success: 'success', warning: 'warning', error: 'danger', info: 'primary' };
                    dot.className = 'bullet bullet-dot bg-' + (colors[event.level] || 'primary') + ' mt-2';
                    const content = document.createElement('div');
                    const message = document.createElement('div');
                    message.className = 'text-gray-800 fs-7';
                    message.textContent = event.message || '';
                    const time = document.createElement('div');
                    time.className = 'text-muted fs-9';
                    time.textContent = event.at ? new Date(event.at).toLocaleString('es-MX') : '';
                    content.append(message, time);
                    row.append(dot, content);
                    return row;
                }));
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
                if (task.status === 'completed' || task.status === 'failed') finished = true;
            } catch (error) {
                // Un fallo temporal de red no afecta al trabajo del servidor.
            }
        }));

        if (finished) window.location.reload();
    };

    window.setInterval(poll, 4000);
});
</script>
@endpush
