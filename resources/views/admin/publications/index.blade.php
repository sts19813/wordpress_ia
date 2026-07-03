@extends('layouts.admin')

@section('title', 'Publicaciones | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Publicaciones</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Todas las entradas enviadas a los sitios WordPress.</div>
        </div>
        <div class="d-flex gap-3">
            <a href="{{ route('admin.wordpress-sites.index') }}" class="btn btn-light-primary"><i class="ki-outline ki-setting-2 fs-2"></i>Configurar sitios</a>
            <a href="{{ route('admin.ai-articles.index') }}" class="btn btn-primary"><i class="ki-outline ki-plus fs-2"></i>Elegir artículo</a>
        </div>
    </div>
@endsection

@section('content')
    <div class="row g-5 mb-8">
        <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100"><div class="card-body"><div class="text-muted fw-semibold fs-7">Entradas enviadas</div><div class="fs-2x fw-bold text-gray-900">{{ $publications->count() }}</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100"><div class="card-body"><div class="text-muted fw-semibold fs-7">Publicadas</div><div class="fs-2x fw-bold text-success">{{ $publishedCount }}</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100"><div class="card-body"><div class="text-muted fw-semibold fs-7">Con error</div><div class="fs-2x fw-bold text-danger">{{ $failedCount }}</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100"><div class="card-body"><div class="text-muted fw-semibold fs-7">Sitios configurados</div><div class="fs-2x fw-bold text-primary">{{ $sites->count() }}</div></div></div></div>
    </div>

    @if ($sites->isEmpty())
        <div class="alert alert-primary d-flex align-items-center mb-8">
            <i class="ki-outline ki-information-5 fs-2hx text-primary me-4"></i>
            <div class="flex-grow-1"><div class="fw-bold">Todavía no hay un sitio conectado</div><div>Agrega tu WordPress para habilitar el botón Publicar en los artículos.</div></div>
            <a href="{{ route('admin.wordpress-sites.create') }}" class="btn btn-sm btn-primary">Conectar WordPress</a>
        </div>
    @endif

    <div class="card card-flush">
        <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Historial</h3></div></div>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5 admin-datatable">
                    <thead><tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                        <th>Artículo</th><th>Sitio</th><th>Estado</th><th>Fecha</th><th>Detalle</th><th class="text-end no-sort no-search">Acciones</th>
                    </tr></thead>
                    <tbody class="text-gray-700 fw-semibold">
                        @foreach ($publications as $publication)
                            @php
                                $statusClass = match ($publication->status) {
                                    'published' => 'badge-light-success',
                                    'failed' => 'badge-light-danger',
                                    'scheduled' => 'badge-light-info',
                                    'pending' => 'badge-light-warning',
                                    default => 'badge-light-secondary',
                                };
                            @endphp
                            <tr>
                                <td>
                                    @if ($publication->aiArticle)
                                        <a href="{{ route('admin.ai-articles.show', $publication->aiArticle) }}" class="text-gray-900 text-hover-primary fw-bold">{{ $publication->aiArticle->title }}</a>
                                    @else
                                        <span class="text-muted">Artículo eliminado</span>
                                    @endif
                                    @if ($publication->remote_post_id)<div class="text-muted fs-8">ID WordPress: {{ $publication->remote_post_id }}</div>@endif
                                </td>
                                <td>
                                    <div class="fw-bold text-gray-900">{{ $publication->wordpressSite?->name ?: 'Sitio eliminado' }}</div>
                                    <div class="text-muted fs-8">{{ $publication->wordpressSite?->rest_api_url }}</div>
                                </td>
                                <td><span class="badge {{ $statusClass }}">{{ $publication->statusLabel() }}</span></td>
                                <td data-order="{{ ($publication->published_at ?: $publication->updated_at)->timestamp }}">{{ ($publication->published_at ?: $publication->updated_at)->format('d/m/Y H:i') }}</td>
                                <td class="mw-300px">
                                    @if ($publication->error_message)
                                        <span class="text-danger">{{ $publication->error_message }}</span>
                                    @elseif ($publication->status === 'published')
                                        <span class="text-success">Publicación completada</span>
                                    @else
                                        <span class="text-muted">{{ str($publication->last_action)->replace('_', ' ')->ucfirst() }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($publication->remote_url)
                                        <a href="{{ $publication->remote_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light-primary">Ver entrada <i class="ki-outline ki-exit-up fs-4 ms-1"></i></a>
                                    @elseif ($publication->aiArticle)
                                        <a href="{{ route('admin.ai-articles.show', $publication->aiArticle) }}" class="btn btn-sm btn-light">Revisar</a>
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
