@extends('layouts.auth')

@section('title', 'Restablecer contraseña | '.config('app.name'))

@section('content')
    <div class="d-flex flex-column-fluid flex-lg-row-auto justify-content-center justify-content-lg-end p-12 p-lg-20">
        <div class="bg-body d-flex flex-column align-items-stretch flex-center rounded-4 w-md-600px p-20">
            <div class="d-flex flex-center flex-column flex-column-fluid px-lg-10 pb-15 pb-lg-20">
    <form method="POST" action="{{ route('password.store') }}" class="form w-100" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="text-center mb-11">
            <h1 class="text-gray-900 fw-bolder mb-3">Restablecer contraseña</h1>
            <div class="text-gray-500 fw-semibold fs-6">Ingresa tu nueva contraseña para continuar.</div>
        </div>
        @if ($errors->any())<div class="alert alert-danger mb-6">{{ $errors->first() }}</div>@endif
        <div class="fv-row mb-7">
            <input type="email" name="email" value="{{ old('email', $request->email) }}" placeholder="Correo electrónico" autocomplete="username" class="form-control form-control-lg bg-transparent @error('email') is-invalid @enderror" required autofocus>
        </div>
        <div class="fv-row mb-7">
            <input type="password" name="password" placeholder="Nueva contraseña" autocomplete="new-password" class="form-control form-control-lg bg-transparent @error('password') is-invalid @enderror" required>
        </div>
        <div class="fv-row mb-9">
            <input type="password" name="password_confirmation" placeholder="Confirmar nueva contraseña" autocomplete="new-password" class="form-control form-control-lg bg-transparent" required>
        </div>
        <div class="d-grid mb-10"><button type="submit" class="btn btn-primary">Restablecer contraseña</button></div>
        <div class="text-gray-500 text-center fw-semibold fs-6"><a href="{{ route('login') }}" class="link-primary">Volver a iniciar sesión</a></div>
    </form>
            </div>
        </div>
    </div>
@endsection
