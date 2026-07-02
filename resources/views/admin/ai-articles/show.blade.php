@extends('layouts.admin')

@section('title', ($article->title ?: 'Borrador').' | '.config('app.name'))

@php
    $mainImage = $article->mainImage();
    $publishedDestinations = $article->publications->where('status', App\Models\Publication::STATUS_PUBLISHED)->count();
@endphp

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div>
            <a href="{{ route('admin.ai-articles.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3"><i class="ki-outline ki-left fs-4 me-1"></i>Artículos IA</a>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">{{ $article->title ?: 'Generación #'.$article->id }}</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Vista previa del contenido{{ $publishedDestinations ? ' · publicado en '.$publishedDestinations.' sitio(s)' : ' · aún no publicado' }}</div>
        </div>
        <div class="d-flex gap-3">
            @if ($article->status === 'draft')
                <a href="{{ route('admin.ai-articles.edit', $article) }}" class="btn btn-primary"><i class="ki-outline ki-pencil fs-2"></i>Editar borrador</a>
            @endif
            @if ($article->status !== 'failed')
                @if ($wordpressSites->isEmpty())
                    <a href="{{ route('admin.wordpress-sites.create') }}" class="btn btn-success"><i class="ki-outline ki-send fs-2"></i>Configurar y publicar</a>
                @elseif ($wordpressSites->count() === 1)
                    <form method="POST" action="{{ route('admin.publications.publish', $article) }}">
                        @csrf
                        <button class="btn btn-success" type="submit"><i class="ki-outline ki-send fs-2"></i>Publicar</button>
                    </form>
                @else
                    <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#publish-sites-modal"><i class="ki-outline ki-send fs-2"></i>Publicar</button>
                @endif
            @endif
            <form method="POST" action="{{ route('admin.ai-articles.destroy', $article) }}" data-confirm-delete data-confirm-title="Eliminar borrador" data-confirm-text="Se eliminarán también sus imágenes. Esta acción no se puede deshacer.">
                @csrf @method('DELETE')
                <button class="btn btn-light-danger" type="submit"><i class="ki-outline ki-trash fs-2"></i>Eliminar</button>
            </form>
        </div>
    </div>
@endsection

@section('content')
    @if ($article->status === 'failed')
        <div class="alert alert-danger d-flex align-items-start mb-7">
            <i class="ki-outline ki-cross-circle fs-2hx text-danger me-4"></i>
            <div><div class="fw-bold mb-1">No se pudo generar el borrador</div><div>{{ $article->generation_error }}</div></div>
        </div>
    @endif

    <div class="row g-7">
        <div class="col-xl-8">
            <article class="card card-flush">
                <div class="card-body p-lg-10">
                    @if ($mainImage?->status === 'generated' && $mainImage->file_path)
                        <img src="{{ route('admin.ai-images.file', $mainImage) }}" alt="{{ $article->title }}" class="rounded w-100 mb-8" style="max-height: 480px; object-fit: cover;">
                    @elseif ($mainImage?->status === 'failed')
                        <div class="alert alert-warning">El texto quedó listo, pero la imagen no pudo generarse: {{ $mainImage->generation_error }}</div>
                    @endif

                    @if ($article->status !== 'failed')
                        <h1 class="fw-bolder text-gray-900 mb-4">{{ $article->title }}</h1>
                        @if ($article->excerpt)<p class="fs-4 text-gray-600 fw-semibold mb-8">{{ $article->excerpt }}</p>@endif
                        <div class="ai-article-preview text-gray-800 fs-6 lh-lg">{!! $article->content !!}</div>
                        @if ($article->conclusion)
                            <div class="border-top mt-8 pt-6"><h2 class="fs-3 fw-bold">Conclusión</h2><p>{{ $article->conclusion }}</p></div>
                        @endif
                    @endif
                </div>
            </article>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush mb-7">
                <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Estado del borrador</h3></div></div>
                <div class="card-body d-flex flex-column gap-5">
                    <div><div class="text-muted fs-7">Estado</div><span class="badge {{ $article->status === 'draft' ? 'badge-light-success' : 'badge-light-danger' }}">{{ $article->statusLabel() }}</span></div>
                    <div><div class="text-muted fs-7">Perfil</div><div class="fw-bold">{{ $article->promptProfile?->name ?: '-' }}</div></div>
                    <div><div class="text-muted fs-7">Modelo</div><code>{{ $article->model }}</code></div>
                    <div><div class="text-muted fs-7">Temperatura configurada</div><div class="fw-bold">{{ $article->temperature }}</div></div>
                    <div><div class="text-muted fs-7">Extensión</div><div class="fw-bold">{{ App\Models\AiPromptProfile::lengthOptions()[$article->content_length] ?? $article->content_length }}</div></div>
                    <div><div class="text-muted fs-7">Generado</div><div class="fw-bold">{{ $article->generated_at?->format('d/m/Y H:i') ?: '-' }}</div></div>
                    <div><div class="text-muted fs-7">Tokens</div><div class="fw-bold">{{ number_format($article->tokens['total'] ?? 0) }}</div></div>
                </div>
            </div>

            @if ($article->publications->isNotEmpty())
                <div class="card card-flush mb-7">
                    <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Publicaciones</h3></div></div>
                    <div class="card-body">
                        @foreach ($article->publications as $publication)
                            <div class="d-flex justify-content-between align-items-center gap-3 {{ $loop->last ? '' : 'border-bottom pb-4 mb-4' }}">
                                <div>
                                    <div class="fw-bold text-gray-900">{{ $publication->wordpressSite?->name ?: 'Sitio eliminado' }}</div>
                                    <div class="text-muted fs-8">{{ $publication->updated_at->format('d/m/Y H:i') }}</div>
                                </div>
                                <div class="text-end">
                                    <span class="badge {{ $publication->status === 'published' ? 'badge-light-success' : ($publication->status === 'failed' ? 'badge-light-danger' : 'badge-light-warning') }}">{{ $publication->statusLabel() }}</span>
                                    @if ($publication->remote_url)<div class="mt-2"><a href="{{ $publication->remote_url }}" target="_blank" rel="noopener noreferrer" class="fs-8 fw-bold">Ver entrada</a></div>@endif
                                </div>
                            </div>
                        @endforeach
                        <a href="{{ route('admin.publications.index') }}" class="btn btn-sm btn-light-primary w-100 mt-5">Ver todo el historial</a>
                    </div>
                </div>
            @endif

            <div class="card card-flush mb-7">
                <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">SEO y clasificación</h3></div></div>
                <div class="card-body">
                    <div class="mb-5"><div class="text-muted fs-7 mb-1">Slug</div><code>{{ $article->slug ?: '-' }}</code></div>
                    <div class="mb-5"><div class="text-muted fs-7 mb-1">Meta descripción</div><div>{{ $article->meta_description ?: '-' }}</div></div>
                    @foreach (['categories' => 'Categorías', 'tags' => 'Tags', 'seo_keywords' => 'Keywords'] as $field => $label)
                        <div class="mb-5"><div class="text-muted fs-7 mb-2">{{ $label }}</div><div class="d-flex flex-wrap gap-2">@forelse ($article->{$field} ?: [] as $item)<span class="badge badge-light-primary">{{ $item }}</span>@empty<span>-</span>@endforelse</div></div>
                    @endforeach
                </div>
            </div>

            <div class="card card-flush">
                <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Fuentes utilizadas</h3></div></div>
                <div class="card-body">
                    @foreach ($sourcePosts as $sourcePost)
                        <div class="pb-4 mb-4 border-bottom">
                            <a href="{{ route('admin.news.show', $sourcePost) }}" class="fw-bold text-gray-900 text-hover-primary">{{ $sourcePost->title }}</a>
                            <div class="text-muted fs-8 mt-1">{{ $sourcePost->sourceSite?->name }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if ($wordpressSites->count() > 1)
        <div class="modal fade" id="publish-sites-modal" tabindex="-1" aria-labelledby="publish-sites-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.publications.publish', $article) }}">
                        @csrf
                        <div class="modal-header">
                            <div><h2 class="fw-bold mb-1" id="publish-sites-title">¿Dónde quieres publicar?</h2><div class="text-muted fs-7">Puedes enviar el artículo a uno o varios sitios.</div></div>
                            <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal" aria-label="Cerrar"><i class="ki-outline ki-cross fs-2"></i></button>
                        </div>
                        <div class="modal-body py-7">
                            <div class="d-flex flex-column gap-4">
                                @foreach ($wordpressSites as $site)
                                    @php($existing = $article->publications->firstWhere('wordpress_site_id', $site->id))
                                    <label class="border rounded p-4 d-flex align-items-center gap-4 cursor-pointer">
                                        <input class="form-check-input" type="checkbox" name="site_ids[]" value="{{ $site->id }}" @checked(in_array($site->id, old('site_ids', [])))>
                                        <span class="flex-grow-1"><span class="fw-bold text-gray-900 d-block">{{ $site->name }}</span><span class="text-muted fs-8">{{ $site->rest_api_url }}</span></span>
                                        @if ($existing)<span class="badge badge-light-info">Se actualizará</span>@endif
                                    </label>
                                @endforeach
                            </div>
                            @error('site_ids')<div class="text-danger fs-7 mt-4">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success"><i class="ki-outline ki-send fs-2"></i>Publicar ahora</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@if ($errors->has('site_ids'))
    @push('scripts')
        <script>document.addEventListener('DOMContentLoaded', function () { var element = document.getElementById('publish-sites-modal'); if (element && window.bootstrap) { bootstrap.Modal.getOrCreateInstance(element).show(); } });</script>
    @endpush
@endif

@push('styles')
<style>
.ai-article-preview h2 { margin: 2rem 0 .75rem; font-weight: 700; }
.ai-article-preview h3 { margin: 1.5rem 0 .65rem; font-weight: 700; }
.ai-article-preview p, .ai-article-preview ul, .ai-article-preview ol { margin-bottom: 1.15rem; }
</style>
@endpush
