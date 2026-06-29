@extends('layouts.admin')

@section('title', $sourceSite->name.' | '.config('app.name'))

@section('toolbar')
    <div>
        <a href="{{ route('admin.source-sites.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3">
            <i class="ki-outline ki-left fs-4 me-1"></i>
            Sitios Fuente
        </a>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">{{ $sourceSite->name }}</h1>
        <div class="text-muted fw-semibold fs-7 pt-1">{{ $sourceSite->url }}</div>
    </div>
@endsection

@section('content')
    @include('admin.source-sites._form')
@endsection
