@extends('layouts.admin')

@section('title', 'Perfiles de publicación | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 w-100">
        <div>
            <a href="{{ route('admin.publications.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3"><i class="ki-outline ki-left fs-4 me-1"></i>Publicaciones</a>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Perfiles de publicación</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Administra los sitios WordPress y páginas de Facebook donde se publicarán los posts generados.</div>
        </div>
        <a href="{{ route('admin.wordpress-sites.create') }}" class="btn btn-primary"><i class="ki-outline ki-plus fs-2"></i>Agregar perfil de publicación</a>
    </div>
@endsection

@section('content')
    @if ($sites->isEmpty())
        <div class="card card-flush"><div class="card-body text-center py-15">
            <div class="symbol symbol-80px mb-6"><div class="symbol-label bg-light-primary"><i class="ki-outline ki-send fs-3x text-primary"></i></div></div>
            <h2 class="fw-bold text-gray-900">Conecta tu primer destino</h2>
            <p class="text-muted fw-semibold mb-7">Después podrás publicar cada post generado en WordPress o en una página de Facebook.</p>
            <a href="{{ route('admin.wordpress-sites.create') }}" class="btn btn-primary">Agregar perfil de publicación del post generado</a>
        </div></div>
    @else
        <div class="row g-7">
            @foreach ($sites as $site)
                @php
                    $ready = $site->active && $site->status === App\Models\WordPressSite::STATUS_ACTIVE;
                    $isFacebook = $site->isFacebookPage();
                @endphp
                <div class="col-xl-6">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex flex-column gap-5">
                            <div class="d-flex justify-content-between align-items-start gap-4">
                                <div class="d-flex align-items-center min-w-0">
                                    <div class="symbol symbol-50px me-4"><div class="symbol-label bg-light-primary"><i class="ki-outline {{ $isFacebook ? 'ki-facebook' : 'ki-wordpress' }} fs-2x text-primary"></i></div></div>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.wordpress-sites.edit', $site) }}" class="fs-4 fw-bold text-gray-900 text-hover-primary">{{ $site->name }}</a>
                                        <div class="text-muted text-truncate">{{ $site->destinationLabel() }}</div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge badge-light-primary mb-2">{{ $site->typeLabel() }}</span>
                                    <div><span class="badge {{ $ready ? 'badge-light-success' : ($site->status === 'error' ? 'badge-light-danger' : 'badge-light-warning') }}">{{ $ready ? 'Listo' : $site->statusLabel() }}</span></div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-8 text-gray-700">
                                <div>
                                    <span class="text-muted fs-8 d-block">{{ $isFacebook ? 'Página' : 'Usuario' }}</span>
                                    <span class="fw-bold">{{ $isFacebook ? $site->facebook_page_id : $site->username }}</span>
                                </div>
                                <div><span class="text-muted fs-8 d-block">Publicaciones</span><span class="fw-bold">{{ $site->publications_count }}</span></div>
                                <div><span class="text-muted fs-8 d-block">Última prueba</span><span class="fw-bold">{{ $site->last_tested_at?->format('d/m/Y H:i') ?: 'Sin probar' }}</span></div>
                            </div>
                            @if ($site->connection_error)<div class="text-danger fs-7">{{ $site->connection_error }}</div>@endif
                            <div class="d-flex justify-content-end gap-2 mt-auto">
                                <form method="POST" action="{{ route('admin.wordpress-sites.test', $site) }}">@csrf<button class="btn btn-sm btn-light-primary" type="submit">Probar</button></form>
                                <a href="{{ route('admin.wordpress-sites.edit', $site) }}" class="btn btn-sm btn-light">Editar</a>
                                <form method="POST" action="{{ route('admin.wordpress-sites.destroy', $site) }}" data-confirm-delete data-confirm-title="Eliminar perfil de publicación" data-confirm-text="Se quitará {{ $site->name }}, pero se conservará su historial de publicaciones.">@csrf @method('DELETE')<button class="btn btn-sm btn-light-danger" type="submit">Eliminar</button></form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
