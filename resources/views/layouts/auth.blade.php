<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="Acceso a {{ config('app.name') }}">
    <link rel="shortcut icon" href="{{ asset('/metronic/assets/media/logos/favicon.ico') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700">
    <link href="{{ asset('/metronic/assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet">
    <link href="{{ asset('/metronic/assets/css/style.bundle.css') }}" rel="stylesheet">
    <style>
        body { background-image: url('{{ asset('/metronic/assets/media/auth/bg2.jpg') }}'); }
        [data-bs-theme="dark"] body { background-image: url('{{ asset('/metronic/assets/media/auth/bg2-dark.jpg') }}'); }
        .auth-brand-mark { width: 76px; height: 76px; border-radius: 24px; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.28); color: #fff; display: grid; place-items: center; font-size: 1.65rem; font-weight: 800; backdrop-filter: blur(12px); box-shadow: 0 20px 50px rgba(15,23,42,.22); }
        .auth-card { width: min(600px, 100%); }
        @media (max-width: 991.98px) { .auth-hero { min-height: 220px; } .auth-panel { padding: 24px !important; } }
    </style>
    @stack('styles')
</head>
<body id="kt_body" class="app-blank bgi-size-cover bgi-attachment-fixed bgi-position-center bgi-no-repeat">
    <script>
        var mode = localStorage.getItem('data-bs-theme') || 'light';
        if (mode === 'system') mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', mode);
    </script>
    <div class="d-flex flex-column flex-root min-vh-100">
        <div class="d-flex flex-column flex-column-fluid flex-lg-row">
            <div class="auth-hero d-flex flex-center w-lg-50 px-10 py-15">
                <a href="{{ url('/') }}" class="d-flex flex-column align-items-center text-decoration-none">
                    <span class="auth-brand-mark mb-7">WI</span>
                    <span class="text-white fw-bolder fs-2x">{{ config('app.name') }}</span>
                    <span class="text-white opacity-75 fw-semibold mt-2">Tu espacio de administración inteligente</span>
                </a>
            </div>
            <div class="auth-panel d-flex flex-column-fluid flex-lg-row-auto justify-content-center justify-content-lg-end p-12 p-lg-20">
                <div class="auth-card bg-body d-flex flex-column align-items-stretch flex-center rounded-4 p-10 p-lg-15 shadow-sm">
                    @yield('content')
                    <div class="d-flex justify-content-between align-items-center mt-10 px-lg-5 text-gray-500 fw-semibold fs-7">
                        <span>Español (MX)</span>
                        <span>{{ date('Y') }} &copy; {{ config('app.name') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>var hostUrl = "{{ asset('/metronic/assets') }}/";</script>
    <script src="{{ asset('/metronic/assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('/metronic/assets/js/scripts.bundle.js') }}"></script>
    @stack('scripts')
</body>
</html>
