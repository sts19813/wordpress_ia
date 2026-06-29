@extends('layouts.admin')

@section('title', 'Nuevo sitio fuente | '.config('app.name'))

@section('toolbar')
    <div>
        <a href="{{ route('admin.source-sites.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3">
            <i class="ki-outline ki-left fs-4 me-1"></i>
            Sitios Fuente
        </a>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Nuevo sitio fuente</h1>
    </div>
@endsection

@section('content')
    @include('admin.source-sites._form')
@endsection
