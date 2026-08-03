@extends('layouts.admin')

@section('title', 'Agregar perfil de publicación | '.config('app.name'))

@section('toolbar')
    <div>
        <a href="{{ $returnCompany ? route('admin.companies.edit', ['company' => $returnCompany, 'tab' => 'destinos']) : route('admin.wordpress-sites.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3"><i class="ki-outline ki-left fs-4 me-1"></i>{{ $returnCompany ? $returnCompany->name : 'Perfiles de publicación' }}</a>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Agregar perfil de publicación del post generado</h1>
        <div class="text-muted fw-semibold fs-7 pt-1">Conecta WordPress, Facebook, Instagram o X{{ $returnCompany ? ' para '.$returnCompany->name : '' }}.</div>
    </div>
@endsection

@section('content')
    @include('admin.wordpress-sites._form')
@endsection
