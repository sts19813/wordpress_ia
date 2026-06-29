@extends('layouts.auth')

@section('title', 'Crear cuenta | '.config('app.name'))

@section('content')
    <form method="POST" action="{{ route('register') }}" class="form w-100 px-lg-5" enctype="multipart/form-data" novalidate>
        @csrf
        <div class="text-center mb-10">
            <h1 class="text-gray-900 fw-bolder mb-3">Crear cuenta</h1>
            <div class="text-gray-500 fw-semibold fs-6">Empieza a administrar tu proyecto</div>
        </div>
        <div class="d-grid mb-8">
            <a href="{{ route('google.redirect') }}" class="btn btn-flex btn-light btn-lg w-100 justify-content-center">
                <img alt="Google" src="{{ asset('/metronic/assets/media/svg/brand-logos/google-icon.svg') }}" class="h-20px me-3">Continuar con Google
            </a>
        </div>
        <div class="separator separator-content my-8"><span class="w-125px text-gray-500 fw-semibold fs-7">O con correo</span></div>
        @if ($errors->any())
            <div class="alert alert-danger mb-7"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="fv-row mb-7">
            <input type="text" name="name" value="{{ old('name') }}" class="form-control form-control-lg bg-transparent @error('name') is-invalid @enderror" placeholder="Nombre completo" required autofocus>
        </div>
        <div class="fv-row mb-7">
            <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg bg-transparent @error('email') is-invalid @enderror" placeholder="Correo electrónico" required>
        </div>
        <div class="fv-row mb-7">
            <label class="form-label text-gray-600">Foto de perfil <span class="text-muted">(opcional)</span></label>
            <input type="file" name="profile_photo" class="form-control form-control-lg bg-transparent @error('profile_photo') is-invalid @enderror" accept="image/*">
        </div>
        <div class="fv-row mb-7">
            <input type="password" name="password" class="form-control form-control-lg bg-transparent @error('password') is-invalid @enderror" placeholder="Contraseña" autocomplete="new-password" required>
        </div>
        <div class="fv-row mb-9">
            <input type="password" name="password_confirmation" class="form-control form-control-lg bg-transparent" placeholder="Confirmar contraseña" autocomplete="new-password" required>
        </div>
        <div class="d-grid mb-10"><button type="submit" class="btn btn-primary btn-lg">Crear cuenta</button></div>
        <div class="text-gray-500 text-center fw-semibold fs-6">¿Ya tienes cuenta? <a href="{{ route('login') }}" class="link-primary">Inicia sesión</a></div>
    </form>
@endsection
