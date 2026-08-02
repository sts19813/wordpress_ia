@extends('layouts.admin')

@section('title', 'Editar empresa | '.config('app.name'))

@section('toolbar')
    <div>
        <a href="{{ route('admin.companies.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3"><i class="ki-outline ki-left fs-4 me-1"></i>Empresas</a>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Editar {{ $company->name }}</h1>
    </div>
@endsection

@section('content')
    <div class="row justify-content-center"><div class="col-xl-8">@include('admin.companies._form')</div></div>
@endsection
