@extends('layouts.admin')

@section('title', 'Empresas | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Empresas</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Cada empresa reúne sus WordPress y perfiles de Facebook, Instagram y X.</div>
        </div>
        <a href="{{ route('admin.companies.create') }}" class="btn btn-primary"><i class="ki-outline ki-plus fs-2"></i>Nueva empresa</a>
    </div>
@endsection

@section('content')
    @if ($companies->isEmpty())
        <div class="card card-flush"><div class="card-body text-center py-15">
            <div class="symbol symbol-80px mb-6"><div class="symbol-label bg-light-primary"><i class="ki-outline ki-briefcase fs-3x text-primary"></i></div></div>
            <h2 class="fw-bold text-gray-900">Crea tu primera empresa</h2>
            <p class="text-muted fw-semibold mb-7">Después podrás guardar dentro de ella todos sus destinos de publicación.</p>
            <a href="{{ route('admin.companies.create') }}" class="btn btn-primary">Crear empresa</a>
        </div></div>
    @else
        <div class="row g-7">
            @foreach ($companies as $company)
                <div class="col-xl-6">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex flex-column gap-5">
                            <div class="d-flex justify-content-between align-items-start gap-4">
                                <div>
                                    <a href="{{ route('admin.companies.edit', $company) }}" class="fs-3 fw-bold text-gray-900 text-hover-primary">{{ $company->name }}</a>
                                    @if ($company->description)<div class="text-muted mt-1">{{ $company->description }}</div>@endif
                                </div>
                                <span class="badge {{ $company->active ? 'badge-light-success' : 'badge-light-warning' }}">{{ $company->active ? 'Activa' : 'Pausada' }}</span>
                            </div>
                            <div class="d-flex flex-wrap gap-8">
                                <div><span class="text-muted fs-8 d-block">Destinos</span><span class="fw-bold fs-5">{{ $company->publication_profiles_count }}</span></div>
                                <div><span class="text-muted fs-8 d-block">Sitios fuente</span><span class="fw-bold fs-5">{{ $company->source_sites_count }}</span></div>
                            </div>
                            @if ($company->publicationProfiles->isNotEmpty())
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($company->publicationProfiles as $profile)
                                        <span class="badge badge-light-primary">{{ $profile->typeLabel() }} · {{ $profile->name }}</span>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted fs-7">Aún no tiene destinos configurados.</div>
                            @endif
                            <div class="d-flex justify-content-end gap-2 mt-auto">
                                <a href="{{ route('admin.wordpress-sites.create', ['company' => $company->id]) }}" class="btn btn-sm btn-light-primary">Agregar destino</a>
                                <a href="{{ route('admin.companies.edit', $company) }}" class="btn btn-sm btn-light">Editar</a>
                                <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" data-confirm-delete data-confirm-title="Eliminar empresa" data-confirm-text="Solo se puede eliminar si no tiene destinos ni sitios fuente.">@csrf @method('DELETE')<button class="btn btn-sm btn-light-danger" type="submit">Eliminar</button></form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
