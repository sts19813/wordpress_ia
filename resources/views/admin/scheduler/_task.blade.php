@php
    $isPolling = in_array($task->status, [\App\Models\Scheduler::STATUS_QUEUED, \App\Models\Scheduler::STATUS_RUNNING], true);
    $publishedUrl = $task->publication?->status === \App\Models\Publication::STATUS_PUBLISHED
        ? $task->publication->remote_url
        : $task->article?->publications?->first()?->remote_url;
    $badgeClass = match ($task->status) {
        \App\Models\Scheduler::STATUS_FAILED => 'badge-light-danger',
        \App\Models\Scheduler::STATUS_RUNNING => 'badge-light-primary',
        \App\Models\Scheduler::STATUS_COMPLETED => 'badge-light-success',
        default => 'badge-light-warning',
    };
@endphp

<div class="card card-flush scheduler-task-card queue-task {{ $selectedTaskId === $task->id ? 'border border-primary' : '' }}"
     id="task-{{ $task->id }}"
     data-active="{{ $isPolling ? '1' : '0' }}"
     data-status-url="{{ route('admin.scheduler.status', $task) }}">
    <div class="card-body px-5 py-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-4">
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="fw-bold text-gray-900 scheduler-task-title">
                        {{ $completed && $task->article?->title ? $task->article->title : $task->name }}
                    </span>
                    <span class="badge task-status {{ $badgeClass }}">
                        {{ $completed ? 'Publicado' : $task->statusLabel() }}
                    </span>
                    @if ($task->sourceSite)
                        <span class="text-muted fs-8">{{ $task->sourceSite->name }}</span>
                    @endif
                </div>

                @unless ($completed)
                    <div class="task-step text-muted fs-8 mt-1">{{ $task->step }}</div>

                    @if ($task->progress < 100)
                        <div class="task-progress-wrap d-flex align-items-center gap-3 mt-3">
                            <div class="progress bg-light flex-grow-1">
                                <div class="progress-bar task-progress bg-{{ $task->status === \App\Models\Scheduler::STATUS_FAILED ? 'danger' : 'primary' }}"
                                     role="progressbar"
                                     style="width: {{ $task->progress }}%"
                                     aria-valuenow="{{ $task->progress }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100"></div>
                            </div>
                            <span class="task-progress-label text-muted fs-9">{{ $task->progress }}%</span>
                        </div>
                    @endif

                    @if ($task->last_error)
                        <div class="task-error text-danger fs-7 mt-3">{{ $task->last_error }}</div>
                    @else
                        <div class="task-error d-none text-danger fs-7 mt-3"></div>
                    @endif
                @endunless
            </div>

            <div class="scheduler-task-actions d-flex flex-wrap gap-2 flex-shrink-0">
                @if (! $completed && $task->status === \App\Models\Scheduler::STATUS_QUEUED)
                    <form method="POST" action="{{ route('admin.scheduler.execute', $task) }}" data-manual-execute-form>
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="ki-outline ki-play fs-4"></i>Ejecutar
                        </button>
                    </form>
                @endif

                @if ($task->article)
                    <a href="{{ route('admin.ai-articles.show', $task->article) }}" class="btn btn-sm btn-light-primary task-article-link">
                        <i class="ki-outline ki-eye fs-4"></i>Ver borrador
                    </a>
                @elseif (! $completed)
                    <a href="#" class="btn btn-sm btn-light-primary task-article-link d-none">
                        <i class="ki-outline ki-eye fs-4"></i>Ver borrador
                    </a>
                @endif

                @if ($task->sourcePost)
                    <a href="{{ route('admin.news.show', $task->sourcePost) }}" class="btn btn-sm btn-light-info">
                        <i class="ki-outline ki-document fs-4"></i>Ver fuente
                    </a>
                @endif

                @if ($completed && $publishedUrl)
                    <a href="{{ $publishedUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light-success">
                        <i class="ki-outline ki-exit-up fs-4"></i>Ver publicado
                    </a>
                @endif

                @if (! $completed && $task->status === \App\Models\Scheduler::STATUS_FAILED)
                    <form method="POST" action="{{ route('admin.scheduler.retry', $task) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-light-warning">
                            <i class="ki-outline ki-arrows-circle fs-4"></i>Reintentar
                        </button>
                    </form>
                @endif

                @if ($completed || $task->status === \App\Models\Scheduler::STATUS_FAILED)
                    <form method="POST"
                          action="{{ route('admin.scheduler.destroy', $task) }}"
                          data-confirm-delete
                          data-confirm-title="Eliminar ejecución"
                          data-confirm-text="Se quitará esta ejecución del programador. El contenido relacionado se conservará.">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="return_tab" value="{{ $completed ? 'completed' : 'active' }}">
                        <button type="submit" class="btn btn-sm btn-icon btn-light-danger" title="Eliminar ejecución" aria-label="Eliminar ejecución">
                            <i class="ki-outline ki-trash fs-4"></i>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
