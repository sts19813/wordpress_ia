@extends('layouts.admin')

@section('title', $sourcePost->title.' | '.config('app.name'))

@php
    $statusClasses = [
        'fetched' => 'badge-light-success',
        'duplicate' => 'badge-light-warning',
        'discarded' => 'badge-light-danger',
    ];
@endphp

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div>
            <a href="{{ route('admin.news.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3">
                <i class="ki-outline ki-left fs-4 me-1"></i>
                Noticias Obtenidas
            </a>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">{{ $sourcePost->title }}</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">{{ $sourcePost->url }}</div>
        </div>

        <form method="POST" action="{{ route('admin.news.destroy', $sourcePost) }}" data-confirm-delete data-confirm-title="Eliminar noticia" data-confirm-text="Se eliminará {{ $sourcePost->title }}. Esta acción no se puede deshacer.">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-light-danger">
                <i class="ki-outline ki-trash fs-2"></i>
                Eliminar
            </button>
        </form>
    </div>
@endsection

@section('content')
    <div class="row g-7">
        <div class="col-xl-8">
            <div class="card card-flush mb-7">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">Contenido</h3>
                    </div>
                </div>
                <div class="card-body">
                    @if ($sourcePost->image_url)
                        <div class="mb-7">
                            <img src="{{ $sourcePost->image_url }}" alt="{{ $sourcePost->title }}" class="rounded w-100" style="max-height: 360px; object-fit: cover;">
                        </div>
                    @endif

                    <h2 class="fw-bold text-gray-900 mb-4">{{ $sourcePost->title }}</h2>

                    @if ($sourcePost->summary)
                        <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-6 mb-7">
                            <i class="ki-outline ki-information-5 fs-2tx text-primary me-4"></i>
                            <div class="fw-semibold text-gray-700">{{ $sourcePost->summary }}</div>
                        </div>
                    @endif

                    <div class="text-gray-800 fs-6 lh-lg" style="white-space: pre-wrap;">{{ $sourcePost->content }}</div>
                </div>
            </div>

            <div class="card card-flush mb-7">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">HTML original</h3>
                    </div>
                </div>
                <div class="card-body">
                    <textarea readonly rows="16" class="form-control form-control-solid font-monospace">{{ $sourcePost->content_html }}</textarea>
                </div>
            </div>

            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">JSON original</h3>
                    </div>
                </div>
                <div class="card-body">
                    <textarea readonly rows="18" class="form-control form-control-solid font-monospace">{{ json_encode($sourcePost->original_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</textarea>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush mb-7">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">Metadatos</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-5">
                        <div>
                            <div class="text-muted fw-semibold fs-7">Estado</div>
                            <span class="badge {{ $statusClasses[$sourcePost->status] ?? 'badge-light' }}">{{ $sourcePost->statusLabel() }}</span>
                        </div>
                        <div>
                            <div class="text-muted fw-semibold fs-7">Fuente</div>
                            <div class="fw-bold text-gray-900">{{ $sourcePost->sourceSite?->name ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fw-semibold fs-7">Autor</div>
                            <div class="fw-bold text-gray-900">{{ $sourcePost->author ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fw-semibold fs-7">Fecha</div>
                            <div class="fw-bold text-gray-900">{{ $sourcePost->published_at?->format('d/m/Y H:i') ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fw-semibold fs-7">Idioma</div>
                            <div class="fw-bold text-gray-900">{{ $sourcePost->language ? strtoupper($sourcePost->language) : '-' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fw-semibold fs-7">URL</div>
                            <a href="{{ $sourcePost->url }}" target="_blank" rel="noopener" class="fw-bold text-break">{{ $sourcePost->url }}</a>
                        </div>
                        <div>
                            <div class="text-muted fw-semibold fs-7">Hash SHA256</div>
                            <code class="text-break">{{ $sourcePost->hash }}</code>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">Clasificación</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-7">
                        <div class="text-muted fw-semibold fs-7 mb-2">Categorías</div>
                        <div class="d-flex flex-wrap gap-2">
                            @forelse ($sourcePost->categories ?: [] as $category)
                                <span class="badge badge-light-primary">{{ $category }}</span>
                            @empty
                                <span class="text-muted">Sin categorías</span>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <div class="text-muted fw-semibold fs-7 mb-2">Tags</div>
                        <div class="d-flex flex-wrap gap-2">
                            @forelse ($sourcePost->tags ?: [] as $tag)
                                <span class="badge badge-light-info">{{ $tag }}</span>
                            @empty
                                <span class="text-muted">Sin tags</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
