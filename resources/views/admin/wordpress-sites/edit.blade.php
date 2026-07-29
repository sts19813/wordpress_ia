@extends('layouts.admin')

@section('title', 'Editar '.$site->name.' | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 w-100">
        <div>
            <a href="{{ route('admin.wordpress-sites.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3"><i class="ki-outline ki-left fs-4 me-1"></i>Perfiles de publicación</a>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Editar {{ $site->name }}</h1>
        </div>
        <form method="POST" action="{{ route('admin.wordpress-sites.test', $site) }}">
            @csrf
            <button class="btn btn-light-primary" type="submit"><i class="ki-outline ki-arrows-circle fs-2"></i>Probar conexión</button>
        </form>
    </div>
@endsection

@section('content')
    @include('admin.wordpress-sites._form')
@endsection
