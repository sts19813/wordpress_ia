@extends('layouts.auth')

@section('title', 'Recuperar contraseña | '.config('app.name'))

@section('content')
    <div class="d-flex flex-column-fluid flex-lg-row-auto justify-content-center justify-content-lg-end p-12 p-lg-20">
        <div class="bg-body d-flex flex-column align-items-stretch flex-center rounded-4 w-md-600px p-20">
            <div class="d-flex flex-center flex-column flex-column-fluid px-lg-10 pb-15 pb-lg-20">
    <form method="POST" action="{{ route('password.email') }}" class="form w-100" novalidate>
        @csrf
        <div class="text-center mb-11">
            <h1 class="text-gray-900 fw-bolder mb-3">¿Olvidaste tu contraseña?</h1>
            <div class="text-gray-500 fw-semibold fs-6">Te enviaremos un enlace para que puedas restablecerla.</div>
        </div>
        @if (session('status'))
            <div class="alert alert-success d-flex align-items-center mb-6"><i class="ki-outline ki-check-circle fs-2 me-3"></i><div>{{ session('status') }}</div></div>
        @endif
        @error('email')<div class="alert alert-danger mb-6">{{ $message }}</div>@enderror
        <div class="fv-row mb-8">
            <input type="email" name="email" value="{{ old('email') }}" placeholder="Correo electrónico" autocomplete="username" class="form-control form-control-lg bg-transparent @error('email') is-invalid @enderror" required autofocus>
        </div>
        <div class="d-grid mb-10"><button type="submit" class="btn btn-primary">Enviar enlace de recuperación</button></div>
        <div class="text-gray-500 text-center fw-semibold fs-6">¿Recordaste tu contraseña? <a href="{{ route('login') }}" class="link-primary">Inicia sesión</a></div>
    </form>
            </div>
        </div>
    </div>
@endsection
