@extends('layouts.admin')

@section('title', 'Editar borrador | '.config('app.name'))

@section('toolbar')
    <div><a href="{{ route('admin.ai-articles.show', $article) }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3"><i class="ki-outline ki-left fs-4 me-1"></i>Vista previa</a><h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Editar borrador</h1></div>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.ai-articles.update', $article) }}">
        @csrf @method('PUT')
        <div class="row g-7">
            <div class="col-xl-8">
                <div class="card card-flush">
                    <div class="card-body">
                        <div class="mb-6"><label class="form-label required">Título</label><input name="title" class="form-control form-control-solid" value="{{ old('title', $article->title) }}" required></div>
                        <div class="mb-6"><label class="form-label required">Contenido HTML</label><textarea name="content" rows="24" class="form-control form-control-solid font-monospace" required>{{ old('content', $article->content) }}</textarea><div class="form-text">Se permiten párrafos, subtítulos, listas y énfasis; otros elementos se limpiarán al guardar.</div></div>
                        <div><label class="form-label">Conclusión</label><textarea name="conclusion" rows="4" class="form-control form-control-solid">{{ old('conclusion', $article->conclusion) }}</textarea></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card card-flush mb-7"><div class="card-body">
                    <div class="mb-5"><label class="form-label">Extracto</label><textarea name="excerpt" rows="4" class="form-control form-control-solid">{{ old('excerpt', $article->excerpt) }}</textarea></div>
                    <div class="mb-5"><label class="form-label">Meta descripción</label><textarea name="meta_description" rows="3" class="form-control form-control-solid">{{ old('meta_description', $article->meta_description) }}</textarea></div>
                    <div><label class="form-label">Slug</label><input name="slug" class="form-control form-control-solid" value="{{ old('slug', $article->slug) }}"></div>
                </div></div>
                <div class="card card-flush mb-7"><div class="card-body">
                    <div class="mb-5"><label class="form-label">Categorías</label><input name="categories" class="form-control form-control-solid" value="{{ old('categories', implode(', ', $article->categories ?: [])) }}"><div class="form-text">Separadas por comas</div></div>
                    <div class="mb-5"><label class="form-label">Tags</label><input name="tags" class="form-control form-control-solid" value="{{ old('tags', implode(', ', $article->tags ?: [])) }}"></div>
                    <div><label class="form-label">Keywords SEO</label><input name="seo_keywords" class="form-control form-control-solid" value="{{ old('seo_keywords', implode(', ', $article->seo_keywords ?: [])) }}"></div>
                </div></div>
                <button class="btn btn-primary w-100" type="submit"><i class="ki-outline ki-check fs-2"></i>Guardar cambios</button>
            </div>
        </div>
    </form>
@endsection
