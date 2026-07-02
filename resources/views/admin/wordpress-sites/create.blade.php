@extends('layouts.admin')

@section('title', 'Agregar WordPress | '.config('app.name'))

@section('toolbar')
    <div>
        <a href="{{ route('admin.publications.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3"><i class="ki-outline ki-left fs-4 me-1"></i>Publicaciones</a>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Agregar sitio WordPress</h1>
        <div class="text-muted fw-semibold fs-7 pt-1">Solo necesitas el dominio, usuario y una contraseña de aplicación.</div>
    </div>
@endsection

@section('content')
    @include('admin.wordpress-sites._form')
@endsection
