@extends('layouts.admin')

@section('title', 'Sitios WordPress | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 w-100">
        <div>
            <a href="{{ route('admin.publications.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3"><i class="ki-outline ki-left fs-4 me-1"></i>Publicaciones</a>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Sitios WordPress</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Administra los destinos donde publicarás tus artículos.</div>
        </div>
        <a href="{{ route('admin.wordpress-sites.create') }}" class="btn btn-primary"><i class="ki-outline ki-plus fs-2"></i>Agregar sitio</a>
    </div>
@endsection

@section('content')
    @if ($sites->isEmpty())
        <div class="card card-flush"><div class="card-body text-center py-15">
            <div class="symbol symbol-80px mb-6"><div class="symbol-label bg-light-primary"><i class="ki-outline ki-wordpress fs-3x text-primary"></i></div></div>
            <h2 class="fw-bold text-gray-900">Conecta tu primer WordPress</h2>
            <p class="text-muted fw-semibold mb-7">Después podrás publicar cualquier borrador con un solo clic.</p>
            <a href="{{ route('admin.wordpress-sites.create') }}" class="btn btn-primary">Agregar sitio WordPress</a>
        </div></div>
    @else
        <div class="row g-7">
            @foreach ($sites as $site)
                @php($ready = $site->active && $site->status === App\Models\WordPressSite::STATUS_ACTIVE)
                <div class="col-xl-6">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex flex-column gap-5">
                            <div class="d-flex justify-content-between align-items-start gap-4">
                                <div class="d-flex align-items-center min-w-0">
                                    <div class="symbol symbol-50px me-4"><div class="symbol-label bg-light-primary"><i class="ki-outline ki-wordpress fs-2x text-primary"></i></div></div>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.wordpress-sites.edit', $site) }}" class="fs-4 fw-bold text-gray-900 text-hover-primary">{{ $site->name }}</a>
                                        <div class="text-muted text-truncate">{{ $site->rest_api_url }}</div>
                                    </div>
                                </div>
                                <span class="badge {{ $ready ? 'badge-light-success' : ($site->status === 'error' ? 'badge-light-danger' : 'badge-light-warning') }}">{{ $ready ? 'Listo' : $site->statusLabel() }}</span>
                            </div>
                            <div class="d-flex gap-8 text-gray-700">
                                <div><span class="text-muted fs-8 d-block">Usuario</span><span class="fw-bold">{{ $site->username }}</span></div>
                                <div><span class="text-muted fs-8 d-block">Publicaciones</span><span class="fw-bold">{{ $site->publications_count }}</span></div>
                                <div><span class="text-muted fs-8 d-block">Última prueba</span><span class="fw-bold">{{ $site->last_tested_at?->format('d/m/Y H:i') ?: 'Sin probar' }}</span></div>
                            </div>
                            @if ($site->connection_error)<div class="text-danger fs-7">{{ $site->connection_error }}</div>@endif
                            <div class="d-flex justify-content-end gap-2 mt-auto">
                                <form method="POST" action="{{ route('admin.wordpress-sites.test', $site) }}">@csrf<button class="btn btn-sm btn-light-primary" type="submit">Probar</button></form>
                                <a href="{{ route('admin.wordpress-sites.edit', $site) }}" class="btn btn-sm btn-light">Editar</a>
                                <form method="POST" action="{{ route('admin.wordpress-sites.destroy', $site) }}" data-confirm-delete data-confirm-title="Eliminar sitio WordPress" data-confirm-text="Se quitará {{ $site->name }}, pero se conservará su historial de publicaciones.">@csrf @method('DELETE')<button class="btn btn-sm btn-light-danger" type="submit">Eliminar</button></form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
