<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard | '.config('app.name'))</title>
    <link rel="shortcut icon" href="{{ asset('/metronic/assets/media/logos/favicon.ico') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700">
    <link href="{{ asset('/metronic/assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet">
    <link href="{{ asset('/metronic/assets/css/style.bundle.css') }}" rel="stylesheet">
    <style>
        :root {
            --bs-primary: #2f80ed;
            --bs-primary-active: #1b64c7;
            --bs-primary-light: #eaf3ff;
            --admin-bg: #f7f8fc;
            --sidebar-bg: #1f2234;
            --sidebar-width: 300px;
            --sidebar-mini-width: 76px;
        }
        html, body, .app-root, .app-page, .app-wrapper, .app-main { min-height: 100%; background: var(--admin-bg); }
        body { margin: 0; color: #1f2a44; overflow-x: hidden; }
        .app-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 105; width: var(--sidebar-width); background: var(--sidebar-bg); box-shadow: 12px 0 28px rgba(17,24,39,.08); transition: width .24s ease; }
        .sidebar-shell { display: flex; flex-direction: column; height: 100vh; padding: 14px 12px 18px; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; min-height: 56px; margin-bottom: 20px; padding: 0 6px; }
        .sidebar-toggle { width: 34px; height: 34px; border: 0; border-radius: 10px; background: rgba(255,255,255,.08); color: #d4ddf8; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .sidebar-toggle:hover { background: rgba(255,255,255,.16); color: #fff; }
        .sidebar-brand-link { display: flex; align-items: center; gap: 12px; min-width: 0; text-decoration: none; }
        .sidebar-brand-mark { width: 36px; height: 36px; border-radius: 12px; background: linear-gradient(135deg,#34a3ff,#2f80ed); color: #fff; display: grid; place-items: center; font-weight: 800; flex-shrink: 0; }
        .sidebar-brand-wordmark { color: #fff; font-size: 1rem; font-weight: 700; white-space: nowrap; }
        .sidebar-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding-right: 2px; }
        .sidebar-scroll::-webkit-scrollbar { width: 6px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.12); border-radius: 999px; }
        .sidebar-menu .menu-heading { color: rgba(177,188,214,.55); letter-spacing: .08em; }
        .sidebar-menu .menu-item { margin-bottom: 8px; }
        .sidebar-menu .menu-link { min-height: 46px; border-radius: 14px; padding: 0 14px; color: rgba(210,220,243,.82); transition: .2s ease; }
        .sidebar-menu .menu-link:hover, .sidebar-menu .menu-link.active { background: rgba(52,163,255,.16); color: #fff; }
        .sidebar-menu .menu-icon { margin-right: 12px !important; }
        .sidebar-menu .menu-icon i, .sidebar-menu .menu-title { color: currentColor !important; }
        .sidebar-footer { margin-top: auto; padding-top: 16px; }
        .sidebar-user { display: flex; align-items: center; gap: 12px; min-height: 62px; padding: 10px 12px; border-radius: 18px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.07); }
        .sidebar-avatar { width: 42px; height: 42px; flex: 0 0 42px; overflow: hidden; border-radius: 50%; }
        .sidebar-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .sidebar-user-name { color: #fff; font-size: .9rem; font-weight: 700; }
        .sidebar-user-email { color: rgba(191,202,230,.72); font-size: .75rem; }
        .sidebar-action { width: 32px; height: 32px; border: 0; border-radius: 10px; background: rgba(248,40,90,.18); color: #ffd9e2; display: grid; place-items: center; }
        .app-main { min-height: 100vh; margin-left: var(--sidebar-width); transition: margin-left .24s ease; }
        .app-shell { display: flex; flex-direction: column; min-height: 100vh; }
        .app-toolbar { padding: 28px 0 18px; }
        .app-container { width: 100%; max-width: 100%; padding-left: 24px; padding-right: 24px; }
        .app-content { flex: 1 1 auto; min-width: 0; }
        .card { border: 1px solid rgba(15,23,42,.05); box-shadow: 0 8px 26px rgba(15,23,42,.045); }
        [data-kt-app-sidebar-minimize="on"] .app-sidebar { width: var(--sidebar-mini-width); }
        [data-kt-app-sidebar-minimize="on"] .app-main { margin-left: var(--sidebar-mini-width); }
        [data-kt-app-sidebar-minimize="on"] .sidebar-brand { justify-content: center; padding: 0; }
        [data-kt-app-sidebar-minimize="on"] .sidebar-brand-link,
        [data-kt-app-sidebar-minimize="on"] .menu-heading,
        [data-kt-app-sidebar-minimize="on"] .menu-title,
        [data-kt-app-sidebar-minimize="on"] .sidebar-user-details,
        [data-kt-app-sidebar-minimize="on"] .sidebar-action { display: none !important; }
        [data-kt-app-sidebar-minimize="on"] .sidebar-menu .menu-link { justify-content: center; padding-inline: 0; }
        [data-kt-app-sidebar-minimize="on"] .sidebar-menu .menu-icon { margin-right: 0 !important; }
        [data-kt-app-sidebar-minimize="on"] .sidebar-user { justify-content: center; padding: 0; background: transparent; border-color: transparent; }
        @media (max-width: 991.98px) {
            .app-main { margin-left: 0 !important; }
            .app-sidebar { width: var(--sidebar-width) !important; }
            .sidebar-brand-link, .menu-heading, .menu-title, .sidebar-user-details, .sidebar-action { display: initial !important; }
            .app-container { padding-left: 16px; padding-right: 16px; }
        }
    </style>
    @stack('styles')
</head>
@php($sidebarMinimized = request()->cookie('sidebar_minimize_state', 'on') === 'on')
<body id="kt_app_body" data-kt-app-sidebar-enabled="true" data-kt-app-sidebar-fixed="true" @if ($sidebarMinimized) data-kt-app-sidebar-minimize="on" @endif class="app-default">
    <script>
        var mode = localStorage.getItem('data-bs-theme') || 'light';
        if (mode === 'system') mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', mode);
    </script>
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <div class="app-page flex-column flex-column-fluid">
            <div class="app-wrapper">
                @include('partials.sidebar')
                <main class="app-main">
                    <div class="app-shell">
                        <div class="app-toolbar">
                            <div class="app-container container-fluid d-flex flex-stack flex-wrap gap-3">
                                @yield('toolbar')
                                <button type="button" class="btn btn-sm btn-light-primary" data-theme-toggle>
                                    <i class="ki-outline ki-night-day fs-2"></i><span class="d-none d-sm-inline">Cambiar tema</span>
                                </button>
                            </div>
                        </div>
                        <div class="app-content">
                            <div class="app-container container-fluid">
                                @if (session('status'))
                                    <div class="alert alert-success d-flex align-items-center mb-6"><i class="ki-outline ki-check-circle fs-2hx text-success me-4"></i><div class="fw-semibold">{{ session('status') }}</div></div>
                                @endif
                                @yield('content')
                            </div>
                        </div>
                        @include('partials.footer')
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script>var hostUrl = "{{ asset('/metronic/assets') }}/";</script>
    <script src="{{ asset('/metronic/assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('/metronic/assets/js/scripts.bundle.js') }}"></script>
    <script src="{{ asset('/metronic/assets/js/widgets.bundle.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var current = document.documentElement.getAttribute('data-bs-theme') || 'light';
                    var next = current === 'dark' ? 'light' : 'dark';
                    localStorage.setItem('data-bs-theme', next);
                    document.documentElement.setAttribute('data-bs-theme', next);
                });
            });
            var sidebarToggle = document.getElementById('kt_app_sidebar_toggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function () {
                    setTimeout(function () {
                        var minimized = document.body.getAttribute('data-kt-app-sidebar-minimize') === 'on';
                        document.cookie = 'sidebar_minimize_state=' + (minimized ? 'on' : 'off') + '; path=/; max-age=31536000; SameSite=Lax';
                    }, 20);
                });
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
