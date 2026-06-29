@extends('layouts.admin')

@section('title', $title.' | '.config('app.name'))

@section('toolbar')
    <div>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">{{ $title }}</h1>
    </div>
@endsection

@section('content')
    <div class="card card-flush">
        <div class="card-body min-h-200px"></div>
    </div>
@endsection
