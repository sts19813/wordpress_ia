@extends('layouts.auth')

@section('title', 'Iniciar sesión | '.config('app.name'))

@section('content')
    <div class="d-flex flex-column-fluid flex-lg-row-auto justify-content-center justify-content-lg-end p-12 p-lg-20">
        <div class="bg-body d-flex flex-column align-items-stretch flex-center rounded-4 w-md-600px p-20">
            <div class="d-flex flex-center flex-column flex-column-fluid px-lg-10 pb-15 pb-lg-20">
                <form method="POST" action="{{ route('login') }}" class="form w-100" novalidate>
                    @csrf

                    <div class="text-center mb-11">
                        <h1 class="text-gray-900 fw-bolder mb-3">
                            Iniciar sesión
                        </h1>
                        <div class="text-gray-500 fw-semibold fs-6">
                            Ingresa con tu correo electrónico
                        </div>
                    </div>

                    <div class="d-grid mb-8">
                        <a href="{{ route('google.redirect') }}" class="btn btn-flex btn-light btn-lg w-100">
                            <img alt="Google" src="{{ asset('/metronic/assets/media/svg/brand-logos/google-icon.svg') }}"
                                class="h-20px me-3" />
                            Continuar con Google
                        </a>
                    </div>

                    <div class="separator separator-content my-10">
                        <span class="w-125px text-gray-500 fw-semibold fs-7">O con correo</span>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success d-flex align-items-center mb-5">
                            <i class="ki-outline ki-check-circle fs-2 me-3"></i>
                            <div>{{ session('status') }}</div>
                        </div>
                    @endif

                    <div class="fv-row mb-8">
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="Correo electrónico"
                            autocomplete="username" class="form-control bg-transparent @error('email') is-invalid @enderror"
                            required autofocus />
                        @error('email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="fv-row mb-3">
                        <input type="password" name="password" placeholder="Contraseña"
                            autocomplete="current-password"
                            class="form-control bg-transparent @error('password') is-invalid @enderror" required />
                        @error('password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
                        <div class="form-check form-check-sm form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember_me">
                            <label class="form-check-label" for="remember_me">
                                Recuérdame
                            </label>
                        </div>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="link-primary">
                                ¿Olvidaste tu contraseña?
                            </a>
                        @endif
                    </div>

                    <div class="d-grid mb-10">
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Iniciar sesión</span>
                        </button>
                    </div>

                    @if (Route::has('register'))
                        <div class="text-gray-500 text-center fw-semibold fs-6">
                            ¿No tienes una cuenta?
                            <a href="{{ route('register') }}" class="link-primary">Regístrate</a>
                        </div>
                    @endif
                </form>
            </div>

            <div class="d-flex flex-stack px-lg-10">
                <div class="me-0">
                    <button class="btn btn-flex btn-link btn-color-gray-700 btn-active-color-primary rotate fs-base"
                        data-kt-menu-trigger="click" data-kt-menu-placement="bottom-start" data-kt-menu-offset="0px, 0px">
                        <img data-kt-element="current-lang-flag" class="w-20px h-20px rounded me-3"
                            src="/metronic/assets/media/flags/mexico.svg" alt="MX" />
                        <span data-kt-element="current-lang-name" class="me-1">Español (MX)</span>
                        <i class="ki-outline ki-down fs-5 text-muted rotate-180 m-0"></i>
                    </button>
                </div>
                <div class="d-flex fw-semibold text-primary fs-base gap-5"></div>
            </div>
        </div>
    </div>
@endsection
