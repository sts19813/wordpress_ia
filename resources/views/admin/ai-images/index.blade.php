@extends('layouts.admin')

@section('title', 'Imágenes IA | '.config('app.name'))

@section('toolbar')
    <div><h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Imágenes IA</h1><div class="text-muted fw-semibold fs-7 pt-1">Recursos generados y asociados a borradores privados.</div></div>
@endsection

@section('content')
    <div class="row g-7">
        @forelse ($images as $image)
            <div class="col-md-6 col-xl-4">
                <div class="card card-flush h-100">
                    @if ($image->status === 'generated' && $image->file_path)
                        <img src="{{ route('admin.ai-images.file', $image) }}" class="card-img-top" alt="{{ $image->title }}" style="height: 240px; object-fit: cover;">
                    @else
                        <div class="d-flex align-items-center justify-content-center bg-light h-250px"><i class="ki-outline ki-picture fs-3x text-muted"></i></div>
                    @endif
                    <div class="card-body">
                        <a href="{{ $image->article ? route('admin.ai-articles.show', $image->article) : '#' }}" class="fw-bold text-gray-900 text-hover-primary">{{ $image->title ?: 'Imagen #'.$image->id }}</a>
                        <div class="d-flex gap-2 mt-3"><span class="badge {{ $image->status === 'generated' ? 'badge-light-success' : 'badge-light-danger' }}">{{ $image->statusLabel() }}</span><span class="badge badge-light">{{ $image->resolution }}</span><span class="badge badge-light">{{ $image->quality }}</span></div>
                        @if ($image->generation_error)<div class="text-danger fs-7 mt-3">{{ $image->generation_error }}</div>@endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="card"><div class="card-body text-center py-15 text-muted">Las imágenes aparecerán aquí al generar borradores.</div></div></div>
        @endforelse
    </div>
@endsection
