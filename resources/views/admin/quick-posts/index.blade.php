@extends('layouts.admin')

@section('title', 'Historial de posts | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div><h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Historial de posts</h1><div class="text-muted fw-semibold fs-7 pt-1">Notas creadas desde texto, URL o voz.</div></div>
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
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <article class="card card-flush h-100">
                    <div class="card-body p-5 d-flex flex-column">
                        @if ($image?->file_path && $image->status === 'generated')
                            <img class="history-post-image rounded mb-4" src="{{ route('admin.ai-images.file', $image) }}" alt="">
                        @endif
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3"><span class="badge {{ $task->status === 'completed' ? 'badge-light-success' : ($task->status === 'failed' ? 'badge-light-danger' : 'badge-light-warning') }}">{{ $task->statusLabel() }}</span><span class="text-muted fs-8">{{ $task->created_at->format('d/m/y H:i') }}</span></div>
                        <h2 class="fs-5 fw-bold text-gray-900 mb-2">{{ $article?->title ?: $task->sourcePost?->title ?: 'Generando post' }}</h2>
                        <div class="text-muted fs-8 mb-5">{{ data_get($task->payload, 'origin') === 'manual_post' ? 'Texto o voz' : 'Desde URL' }} · {{ $destinationCount }} destino(s)</div>
                        <div class="mt-auto">
                            @if ($article && $isReview)
                                <a href="{{ route('admin.manual-posts.create', ['review_task' => $task->id]) }}" class="btn btn-light-primary w-100"><i class="ki-outline ki-eye fs-3"></i>Revisar borrador</a>
                            @elseif ($article)
                                <a href="{{ route('admin.ai-articles.show', $article) }}" class="btn btn-light-primary w-100"><i class="ki-outline ki-eye fs-3"></i>Ver borrador</a>
                            @else
                                <a href="{{ route('admin.scheduler.index', ['task' => $task->id]) }}" class="btn btn-light w-100">Ver progreso</a>
                            @endif
                        </div>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12"><div class="card card-flush"><div class="card-body text-center py-15"><i class="ki-outline ki-document fs-3x text-muted mb-4"></i><div class="fw-bold text-gray-900">Aún no hay posts generados</div></div></div></div>
        @endforelse
    </div>
@endsection

@push('styles')
<style>.history-post-image { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; }</style>
@endpush
