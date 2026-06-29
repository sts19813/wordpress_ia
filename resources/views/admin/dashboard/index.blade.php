@extends('layouts.admin')

@section('title', 'Dashboard | '.config('app.name'))

@section('toolbar')
    <div>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Dashboard</h1>
        <div class="text-muted fw-semibold fs-7 pt-1">Bienvenido, {{ auth()->user()->name }}</div>
    </div>
@endsection

@section('content')
    <div class="row g-5 g-xl-10">
        <div class="col-md-4">
            <div class="card card-flush h-100"><div class="card-body d-flex align-items-center justify-content-between">
                <div><span class="text-gray-500 fw-semibold fs-6">Usuarios registrados</span><div class="fs-2hx fw-bold text-gray-900">{{ $usersCount }}</div></div>
                <i class="ki-outline ki-people fs-3x text-primary"></i>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card card-flush h-100"><div class="card-body d-flex align-items-center justify-content-between">
                <div><span class="text-gray-500 fw-semibold fs-6">Accesos con Google</span><div class="fs-2hx fw-bold text-gray-900">{{ $googleUsersCount }}</div></div>
                <i class="ki-outline ki-google fs-3x text-danger"></i>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card card-flush h-100"><div class="card-body d-flex align-items-center justify-content-between">
                <div><span class="text-gray-500 fw-semibold fs-6">Nuevos este mes</span><div class="fs-2hx fw-bold text-gray-900">{{ $recentUsersCount }}</div></div>
                <i class="ki-outline ki-calendar-add fs-3x text-success"></i>
            </div></div>
        </div>
    </div>

    <div class="card card-flush mt-10">
        <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Panel administrativo</h3></div></div>
        <div class="card-body">
            <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-6">
                <i class="ki-outline ki-information-5 fs-2tx text-primary me-4"></i>
                <div class="fw-semibold text-gray-700">El layout Metronic está listo para recibir los módulos de administración de {{ config('app.name') }}.</div>
            </div>
        </div>
    </div>
@endsection
