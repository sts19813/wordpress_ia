@extends('layouts.auth')

@section('title', 'Iniciar sesión | '.config('app.name'))

@section('content')
    <form method="POST" action="{{ route('login') }}" class="form w-100 px-lg-5" novalidate>
        @csrf
        <div class="text-center mb-11">
            <h1 class="text-gray-900 fw-bolder mb-3">Iniciar sesión</h1>
            <div class="text-gray-500 fw-semibold fs-6">Ingresa con tu correo electrónico</div>
        </div>

        <div class="d-grid mb-8">
            <a href="{{ route('google.redirect') }}" class="btn btn-flex btn-light btn-lg w-100 justify-content-center">
                <img alt="Google" src="{{ asset('/metronic/assets/media/svg/brand-logos/google-icon.svg') }}" class="h-20px me-3">
                Continuar con Google
            </a>
        </div>

        <div class="separator separator-content my-10"><span class="w-125px text-gray-500 fw-semibold fs-7">O con correo</span></div>

        @if (session('status'))
            <div class="alert alert-success d-flex align-items-center mb-6"><i class="ki-outline ki-check-circle fs-2 me-3"></i><div>{{ session('status') }}</div></div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger d-flex align-items-center mb-6"><i class="ki-outline ki-information-5 fs-2 me-3"></i><div>{{ $errors->first() }}</div></div>
        @endif

        <div class="fv-row mb-8">
            <input type="email" name="email" value="{{ old('email') }}" placeholder="Correo electrónico" autocomplete="username" class="form-control form-control-lg bg-transparent @error('email') is-invalid @enderror" required autofocus>
        </div>
        <div class="fv-row mb-3">
            <input type="password" name="password" placeholder="Contraseña" autocomplete="current-password" class="form-control form-control-lg bg-transparent @error('password') is-invalid @enderror" required>
        </div>
        <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
            <label class="form-check form-check-sm form-check-custom form-check-solid">
                <input class="form-check-input" type="checkbox" name="remember">
                <span class="form-check-label">Recuérdame</span>
            </label>
            <a href="{{ route('password.request') }}" class="link-primary">¿Olvidaste tu contraseña?</a>
        </div>
        <div class="d-grid mb-10"><button type="submit" class="btn btn-primary btn-lg">Iniciar sesión</button></div>
        <div class="text-gray-500 text-center fw-semibold fs-6">¿No tienes una cuenta? <a href="{{ route('register') }}" class="link-primary">Regístrate</a></div>
    </form>
@endsection
