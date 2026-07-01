@extends('layouts.admin')

@section('title', 'Artículos IA | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Artículos IA</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Borradores generados; ninguno se publica automáticamente.</div>
        </div>
        <a href="{{ route('admin.ai-articles.create') }}" class="btn btn-primary">
            <i class="ki-outline ki-plus fs-2"></i>Nueva nota con IA
        </a>
    </div>
@endsection

@section('content')
    <div class="card card-flush">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5 admin-datatable">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase">
                            <th class="min-w-300px">Borrador</th>
                            <th>Perfil</th>
                            <th>Modelo</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th class="text-end no-sort no-search">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @foreach ($articles as $article)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.ai-articles.show', $article) }}" class="text-gray-900 text-hover-primary fw-bold">
                                        {{ $article->title ?: 'Generación sin título #'.$article->id }}
                                    </a>
                                    <div class="text-muted text-truncate mw-500px">{{ $article->excerpt ?: $article->generation_error }}</div>
                                </td>
                                <td>{{ $article->promptProfile?->name ?: '-' }}</td>
                                <td><code>{{ $article->model ?: '-' }}</code></td>
                                <td>
                                    <span class="badge {{ $article->status === 'draft' ? 'badge-light-success' : ($article->status === 'failed' ? 'badge-light-danger' : 'badge-light-warning') }}">
                                        {{ $article->statusLabel() }}
                                    </span>
                                </td>
                                <td data-order="{{ $article->created_at->timestamp }}">{{ $article->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.ai-articles.show', $article) }}" class="btn btn-icon btn-light btn-sm me-2" aria-label="Vista previa"><i class="ki-outline ki-eye fs-3"></i></a>
                                    @if ($article->status === 'draft')
                                        <a href="{{ route('admin.ai-articles.edit', $article) }}" class="btn btn-icon btn-light-primary btn-sm" aria-label="Editar"><i class="ki-outline ki-pencil fs-3"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
