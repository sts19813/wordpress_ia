@extends('layouts.admin')

@section('title', 'Post rápido | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Post rápido</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Originales sociales archivados y listos para reutilizar en el flujo de IA.</div>
        </div>
        <a href="{{ route('admin.quick-posts.create') }}" class="btn btn-primary">
            <i class="ki-outline ki-plus fs-2"></i>Nuevo desde URL
        </a>
    </div>
@endsection

@section('content')
    <div class="card card-flush">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5 admin-datatable">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-340px">Original</th>
                            <th class="min-w-130px">Red</th>
                            <th class="min-w-150px">Autor</th>
                            <th class="min-w-130px">Imágenes</th>
                            <th class="min-w-160px">Capturado</th>
                            <th class="text-end min-w-180px no-sort no-search">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @foreach ($posts as $post)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-4">
                                        @if ($post->media->first()?->file_path)
                                            <img src="{{ route('admin.source-post-media.file', $post->media->first()) }}" alt="" class="rounded" width="72" height="56" style="object-fit: cover;">
                                        @else
                                            <div class="symbol symbol-55px"><div class="symbol-label bg-light-primary"><i class="ki-outline ki-picture fs-2 text-primary"></i></div></div>
                                        @endif
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.news.show', $post) }}" class="text-gray-900 text-hover-primary fw-bold d-block">{{ $post->title }}</a>
                                            <div class="text-muted text-truncate mw-400px fs-8">{{ $post->canonical_url ?: $post->url }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge badge-light-primary">{{ $post->originLabel() }}</span></td>
                                <td>{{ $post->author ?: '-' }}</td>
                                <td><span class="badge badge-light-info">{{ $post->media->count() }} archivadas</span></td>
                                <td data-order="{{ $post->captured_at?->timestamp }}">{{ $post->captured_at?->format('d/m/Y H:i') ?: '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.news.show', $post) }}" class="btn btn-sm btn-light-info">Ver original</a>
                                    <a href="{{ route('admin.ai-articles.create', ['source_post_ids' => [$post->id]]) }}" class="btn btn-sm btn-light-primary">Generar</a>
                                    <form method="POST" action="{{ route('admin.quick-posts.destroy', $post) }}" class="d-inline" data-confirm-delete data-confirm-title="Eliminar post original" data-confirm-text="También se borrarán sus imágenes archivadas.">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-icon btn-light-danger" aria-label="Eliminar"><i class="ki-outline ki-trash fs-4"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
