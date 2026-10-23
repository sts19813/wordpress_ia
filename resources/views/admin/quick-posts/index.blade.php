@extends('layouts.admin')

@section('title', 'Bitácora de publicación | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div><h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Bitácora de publicación</h1><div class="text-muted fw-semibold fs-7 pt-1">Actividad de Post rápido de todos los usuarios.</div></div>
        <a href="{{ route('admin.manual-posts.create') }}" class="btn btn-primary"><i class="ki-outline ki-sparkles fs-2"></i>Generar post</a>
    </div>
@endsection

@section('content')
    <div class="row g-5">
        @forelse ($tasks as $task)
            @php
                $article = $task->article;
                $image = $article?->mainImage();
                $isReview = (bool) data_get($task->payload, 'requires_review');
                $destinationCount = count(data_get($task->payload, 'publication_profile_ids', []));
                $publications = $article?->publications ?? collect();
                $canReview = auth()->user()->isAdmin() || $task->user_id === auth()->id();
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <article class="card card-flush h-100">
                    <div class="card-body p-5 d-flex flex-column">
                        @if ($image?->file_path && $image->status === 'generated')
                            <img class="history-post-image rounded mb-4" src="{{ route('admin.ai-images.file', $image) }}" alt="">
                        @endif
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3"><span class="badge {{ $task->status === 'completed' ? 'badge-light-success' : ($task->status === 'failed' ? 'badge-light-danger' : 'badge-light-warning') }}">{{ $task->statusLabel() }}</span><span class="text-muted fs-8">{{ $task->created_at->format('d/m/y H:i') }}</span></div>
                        <h2 class="fs-5 fw-bold text-gray-900 mb-2">{{ $article?->title ?: $task->sourcePost?->title ?: 'Generando post' }}</h2>
                        <div class="text-muted fs-8 mb-2"><i class="ki-outline ki-user fs-7 me-1"></i>{{ $task->user?->name ?: 'Usuario no disponible' }}</div>
                        <div class="text-muted fs-8 mb-4">{{ data_get($task->payload, 'origin') === 'manual_post' ? 'Texto o voz' : 'Desde URL' }} · {{ $destinationCount }} destino(s)</div>

                        @if ($task->status === 'failed')
                            <div class="notice bg-light-danger border border-danger border-dashed rounded p-3 fs-8 text-danger mb-4">No se pudo completar este proceso.</div>
                        @elseif ($task->status !== 'completed')
                            <div class="mb-4">
                                <div class="d-flex justify-content-between text-muted fs-8 mb-2"><span>{{ $task->step ?: 'Procesando' }}</span><span>{{ $task->progress }}%</span></div>
                                <div class="progress h-6px"><div class="progress-bar" style="width: {{ max(3, $task->progress) }}%"></div></div>
                            </div>
                        @endif

                        @if ($publications->isNotEmpty())
                            <div class="d-flex flex-column gap-2 mb-4">
                                @foreach ($publications as $publication)
                                    <div class="d-flex align-items-center justify-content-between gap-2 rounded bg-light px-3 py-2 fs-8">
                                        <span class="text-gray-700 text-truncate">{{ $publication->wordpressSite?->name ?: 'Destino eliminado' }}</span>
                                        @if ($publication->status === 'published' && $publication->remote_url)
                                            <a href="{{ $publication->remote_url }}" target="_blank" rel="noopener noreferrer" class="badge badge-light-success">Publicado <i class="ki-outline ki-exit-up fs-8 ms-1"></i></a>
                                        @else
                                            <span class="badge {{ $publication->status === 'failed' ? 'badge-light-danger' : 'badge-light-warning' }}">{{ $publication->statusLabel() }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="mt-auto">
                            @if ($article && $isReview && $canReview)
                                <a href="{{ route('admin.manual-posts.create', ['review_task' => $task->id]) }}" class="btn btn-light-primary w-100"><i class="ki-outline ki-eye fs-3"></i>Revisar borrador</a>
                            @elseif ($article && $isReview)
                                <span class="btn btn-light w-100 disabled"><i class="ki-outline ki-lock fs-3"></i>Pendiente de revisión</span>
                            @elseif ($task->status === 'completed')
                                <span class="btn btn-light-success w-100 disabled"><i class="ki-outline ki-check fs-3"></i>Proceso finalizado</span>
                            @elseif ($task->status === 'failed')
                                <span class="btn btn-light-danger w-100 disabled"><i class="ki-outline ki-cross-circle fs-3"></i>No se pudo completar</span>
                            @else
                                <span class="btn btn-light w-100 disabled">En proceso</span>
                            @endif
                        </div>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12"><div class="card card-flush"><div class="card-body text-center py-15"><i class="ki-outline ki-document fs-3x text-muted mb-4"></i><div class="fw-bold text-gray-900">Aún no hay actividad en Post rápido</div></div></div></div>
        @endforelse
    </div>
@endsection

@push('styles')
<style>.history-post-image { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; }</style>
@endpush
