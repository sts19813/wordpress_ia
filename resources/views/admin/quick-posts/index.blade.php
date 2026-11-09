@extends('layouts.admin')

@section('title', 'Bitácora de publicación | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Bitácora de publicación</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">{{ number_format($tasks->total()) }} publicaciones · {{ $tasks->perPage() }} por página</div>
        </div>
        <a href="{{ route('admin.manual-posts.create') }}" class="btn btn-primary"><i class="ki-outline ki-sparkles fs-2"></i>Generar post</a>
    </div>
@endsection

@section('content')
    <div class="row g-5">
        @forelse ($tasks as $task)
            @php
                $article = $task->article;
                $image = $article?->mainImage();
                $hasImage = $image?->file_path && $image->status === 'generated';
                $isReview = (bool) data_get($task->payload, 'requires_review');
                $destinationCount = count(data_get($task->payload, 'publication_profile_ids', []));
                $publications = $article?->publications ?? collect();
                $publishedCount = $publications->where('status', 'published')->count();
                $failedCount = $publications->where('status', 'failed')->count();
                $canReview = auth()->user()->isAdmin() || $task->user_id === auth()->id();
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <article class="quick-history-card card card-flush h-100">
                    <div class="quick-history-media {{ $hasImage ? '' : 'is-missing' }}" data-history-image-shell>
                        @if ($hasImage)
                            <img loading="lazy" decoding="async" src="{{ route('admin.ai-images.file', $image) }}" alt="" onerror="this.remove(); this.parentElement.classList.add('is-missing');">
                        @endif
                        <div class="quick-history-media-placeholder"><i class="ki-outline ki-document fs-2x"></i></div>
                        <div class="quick-history-media-top">
                            <span class="badge {{ $task->status === 'completed' ? 'badge-light-success' : ($task->status === 'failed' ? 'badge-light-danger' : 'badge-light-warning') }}">{{ $task->statusLabel() }}</span>
                            <span>{{ $task->created_at->format('d/m/y H:i') }}</span>
                        </div>
                    </div>

                    <div class="card-body p-5 d-flex flex-column">
                        <h2 class="quick-history-title">{{ $article?->title ?: $task->sourcePost?->title ?: 'Generando post' }}</h2>
                        <div class="d-flex flex-wrap gap-x-4 gap-y-1 text-muted fs-8 mb-4">
                            <span><i class="ki-outline ki-user fs-7 me-1"></i>{{ $task->user?->name ?: 'Usuario no disponible' }}</span>
                            <span><i class="ki-outline ki-send fs-7 me-1"></i>{{ $destinationCount }} destino(s)</span>
                        </div>

                        @if ($task->status === 'failed')
                            <div class="quick-history-alert mb-4"><i class="ki-outline ki-information-5 fs-5"></i><span>{{ $task->last_error ?: 'No se pudo completar este proceso.' }}</span></div>
                        @elseif ($task->status !== 'completed')
                            <div class="mb-4">
                                <div class="d-flex justify-content-between text-muted fs-8 mb-2"><span class="text-truncate pe-3">{{ $task->step ?: 'Procesando' }}</span><span>{{ $task->progress }}%</span></div>
                                <div class="progress h-6px"><div class="progress-bar" style="width: {{ max(3, $task->progress) }}%"></div></div>
                            </div>
                        @elseif ($destinationCount > 0)
                            <div class="quick-history-result mb-4">
                                <span><strong>{{ $publishedCount }}</strong> publicados</span>
                                @if ($failedCount > 0)<span class="text-danger"><strong>{{ $failedCount }}</strong> con error</span>@endif
                                @if ($publishedCount === 0 && $failedCount === 0)<span>Sin destinos confirmados</span>@endif
                            </div>
                        @endif

                        @if ($publications->isNotEmpty())
                            <details class="quick-history-destinations mb-4">
                                <summary>Ver destinos ({{ $publications->count() }}) <i class="ki-outline ki-down fs-6"></i></summary>
                                <div class="quick-history-destination-list">
                                    @foreach ($publications as $publication)
                                        <div class="quick-history-destination">
                                            <span class="text-truncate">{{ $publication->wordpressSite?->name ?: 'Destino eliminado' }}</span>
                                            @if ($publication->status === 'published' && $publication->remote_url)
                                                <a href="{{ $publication->remote_url }}" target="_blank" rel="noopener noreferrer" class="badge badge-light-success">Publicado <i class="ki-outline ki-exit-up fs-8 ms-1"></i></a>
                                            @else
                                                <span class="badge {{ $publication->status === 'failed' ? 'badge-light-danger' : 'badge-light-warning' }}">{{ $publication->statusLabel() }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        <div class="mt-auto pt-1">
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

    <div class="quick-history-pagination d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mt-8">
        <div class="text-muted fs-7">
            @if ($tasks->total())
                Mostrando {{ number_format($tasks->firstItem()) }} a {{ number_format($tasks->lastItem()) }} de {{ number_format($tasks->total()) }} publicaciones
            @else
                No hay publicaciones para mostrar
            @endif
        </div>
        @if ($tasks->hasPages())
            <nav class="d-flex align-items-center gap-2" aria-label="Paginación de la bitácora">
                @if ($tasks->onFirstPage())
                    <span class="btn btn-sm btn-light disabled">Anterior</span>
                @else
                    <a href="{{ $tasks->previousPageUrl() }}" class="btn btn-sm btn-light">Anterior</a>
                @endif
                <span class="quick-history-page-number">Página {{ $tasks->currentPage() }} de {{ $tasks->lastPage() }}</span>
                @if ($tasks->hasMorePages())
                    <a href="{{ $tasks->nextPageUrl() }}" class="btn btn-sm btn-light-primary">Siguiente</a>
                @else
                    <span class="btn btn-sm btn-light disabled">Siguiente</span>
                @endif
            </nav>
        @endif
    </div>
@endsection

@push('styles')
<style>
.quick-history-card { overflow: hidden; border: 1px solid var(--bs-gray-200); box-shadow: 0 5px 20px rgba(15, 23, 42, .05); transition: transform .18s ease, box-shadow .18s ease; }
.quick-history-card:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(15, 23, 42, .1); }
.quick-history-media { position: relative; aspect-ratio: 16 / 8.3; overflow: hidden; background: linear-gradient(135deg, #eaf3ff, #f7f9fc); }
.quick-history-media img { position: relative; z-index: 1; display: block; width: 100%; height: 100%; object-fit: cover; }
.quick-history-media-placeholder { position: absolute; inset: 0; display: grid; place-items: center; color: var(--bs-primary); opacity: .55; }
.quick-history-media:not(.is-missing) .quick-history-media-placeholder { display: none; }
.quick-history-media-top { position: absolute; z-index: 2; inset: .85rem .85rem auto; display: flex; align-items: center; justify-content: space-between; gap: .5rem; color: #fff; font-size: .72rem; font-weight: 700; }
.quick-history-media-top::before { content: ''; position: absolute; inset: -.85rem; z-index: -1; }
.quick-history-media.is-missing .quick-history-media-top { color: var(--bs-gray-700); text-shadow: none; }.quick-history-media.is-missing .quick-history-media-top::before { background: transparent; }
.quick-history-title { display: -webkit-box; overflow: hidden; min-height: 3.1rem; margin: 0 0 .55rem; color: var(--bs-gray-900); font-size: 1.08rem; font-weight: 700; line-height: 1.42; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.quick-history-result { display: flex; flex-wrap: wrap; gap: .5rem 1rem; padding: .7rem .8rem; border-radius: .5rem; background: var(--bs-light-success); color: var(--bs-success); font-size: .78rem; }.quick-history-result strong { font-size: .95rem; }
.quick-history-alert { display: flex; gap: .5rem; align-items: flex-start; padding: .7rem .8rem; border-radius: .5rem; background: var(--bs-light-danger); color: var(--bs-danger); font-size: .78rem; line-height: 1.4; }
.quick-history-destinations { border-top: 1px solid var(--bs-gray-200); }.quick-history-destinations summary { display: flex; justify-content: space-between; align-items: center; padding: .85rem 0 .15rem; cursor: pointer; color: var(--bs-gray-700); font-size: .78rem; font-weight: 700; list-style: none; }.quick-history-destinations summary::-webkit-details-marker { display: none; }.quick-history-destinations[open] summary i { transform: rotate(180deg); }
.quick-history-destination-list { display: flex; flex-direction: column; gap: .35rem; padding-top: .65rem; }.quick-history-destination { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .45rem .55rem; border-radius: .4rem; background: var(--bs-gray-100); color: var(--bs-gray-700); font-size: .72rem; }.quick-history-destination .badge { flex: 0 0 auto; }
.quick-history-pagination { padding: 1rem 1.25rem; border: 1px solid var(--bs-gray-200); border-radius: .65rem; background: #fff; }.quick-history-page-number { color: var(--bs-gray-600); font-size: .78rem; font-weight: 600; white-space: nowrap; }
@media (max-width: 767.98px) { .quick-history-media { aspect-ratio: 16 / 9; }.quick-history-title { min-height: 0; }.quick-history-card .card-body { padding: 1rem !important; } }
</style>
@endpush
