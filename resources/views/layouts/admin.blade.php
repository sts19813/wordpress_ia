<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8" />
    <title>@yield('title', 'Dashboard | '.config('app.name'))</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Administracion de {{ config('app.name') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="shortcut icon" href="{{ asset('/metronic/assets/media/logos/favicon.ico') }}" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <link href="{{ asset('/metronic/assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" />
    <link href="{{ asset('/metronic/assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" />
    <link href="{{ asset('/metronic/assets/css/style.bundle.css') }}" rel="stylesheet" />

    <style>
        :root {
            --bs-primary: #2f80ed;
            --bs-primary-active: #1b64c7;
            --bs-primary-light: #eaf3ff;
            --admin-app-bg: #f7f8fc;
            --admin-sidebar-bg: #1f2234;
            --admin-sidebar-width: 250px;
            --admin-sidebar-mini-width: 76px;
            --admin-sidebar-text: rgba(210, 220, 243, .82);
            --admin-sidebar-text-active: #ffffff;
            --admin-sidebar-width: 300px !important;
            --admin-sidebar-hover-overlap: 19px;
            --bs-text-white: #fff !important;
        }

        html,
        body {
            min-height: 100%;
            background: var(--admin-app-bg);
        }

        body {
            margin: 0;
            color: #1f2a44;
            overflow-x: hidden;
        }

        .app-root,
        .app-page,
        .app-wrapper,
        .app-main,
        .app-shell,
        .app-content,
        #kt_app_content_container {
            background: var(--admin-app-bg);
        }
        .app-page,
        .app-wrapper {
            min-height: 100vh;
        }

        .app-main{
            width: 100% !important;
        }

        .table-responsive thead{
            background: #f8f8f8;
        }

         .table-responsive thead tr th:first-child {
            padding-left: 10px !important;
        }

        .table-responsive thead tr th:last-child {
            padding-right: 10px !important;
        }

        .admin-datatable-wrapper .dataTables_length label,
        .admin-datatable-wrapper .dataTables_filter label {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 0;
            color: #4b5675;
            font-weight: 600 !important;
        }

        .admin-datatable-wrapper .dataTables_filter input,
        .admin-datatable-wrapper .dataTables_length select {
            min-height: 42px;
            border: 1px solid var(--bs-gray-300);
            border-radius: .475rem;
            background-color: var(--bs-gray-100);
            color: #1f2a44;
            box-shadow: none;
        }

        .admin-datatable-wrapper .dataTables_filter input {
            min-width: min(100%, 260px);
            margin-left: 0 !important;
            padding: .65rem 1rem;
        }

        .admin-datatable-wrapper .dataTables_length select {
            padding: .65rem 2.75rem .65rem 1rem;
        }

        .admin-datatable-wrapper .dataTables_info {
            color: #667085;
            font-weight: 600;
        }

        .admin-datatable-wrapper .pagination {
            gap: .35rem;
        }

        .admin-datatable-wrapper .page-link {
            min-width: 36px;
            border-radius: .475rem !important;
            text-align: center;
        }

        @media (max-width: 575.98px) {
            .admin-datatable-wrapper .dataTables_length label,
            .admin-datatable-wrapper .dataTables_filter label {
                justify-content: center;
                flex-wrap: wrap;
            }

            .admin-datatable-wrapper .dataTables_filter input {
                width: 100% !important;
            }
        }

        .app-sidebar-menu-primary.menu>.menu-item>.menu-link .menu-title {
            color: #d7d7d7 !important;
        }
        .app-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 105;
            width: var(--admin-sidebar-width);
            background: var(--admin-sidebar-bg);
            box-shadow: 12px 0 28px rgba(17, 24, 39, .08);
            transition: width .24s ease;
        }

        .app-sidebar-wrapper {
            height: 100vh;
            background: transparent;
        }

        .sidebar-shell {
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 14px 12px 18px;
        }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 56px;
            margin-bottom: 20px;
            padding: 0 6px;
        }

        .sidebar-brand-toggle {
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 10px;
            background: rgba(255, 255, 255, .08);
            color: #d4ddf8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .sidebar-brand-toggle:hover {
            background: rgba(255, 255, 255, .16);
            color: #fff;
        }

        .swal2-popup.delete-confirm-popup {
            width: min(92vw, 460px) !important;
            padding: 2rem 2rem 1.75rem !important;
            border-radius: 18px !important;
        }

        .swal2-popup.delete-confirm-popup .swal2-icon {
            width: 64px !important;
            height: 64px !important;
            margin: 0 auto 1.25rem !important;
        }

        .swal2-popup.delete-confirm-popup .swal2-icon-content {
            font-size: 3rem !important;
        }

        .swal2-popup.delete-confirm-popup .swal2-title {
            margin: 0 0 .75rem !important;
            padding: 0 !important;
            color: #1f2a44 !important;
            font-size: 1.45rem !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
        }

        .swal2-popup.delete-confirm-popup .swal2-html-container {
            max-width: 360px;
            margin: 0 auto 1.25rem !important;
            color: #4b5675 !important;
            font-size: 1rem !important;
            line-height: 1.5 !important;
        }

        .swal2-popup.delete-confirm-popup .swal2-input.delete-confirm-input {
            width: 100% !important;
            min-width: 0 !important;
            height: 48px !important;
            margin: 0 0 1.5rem !important;
            box-sizing: border-box !important;
            border: 1px solid var(--bs-gray-300) !important;
            border-radius: 10px !important;
            background: var(--bs-gray-100) !important;
            color: #1f2a44 !important;
            font-size: 1rem !important;
            font-weight: 600 !important;
            box-shadow: none !important;
        }

        .swal2-popup.delete-confirm-popup .swal2-input.delete-confirm-input:focus {
            border-color: var(--bs-primary) !important;
            background: #fff !important;
            box-shadow: 0 0 0 .25rem rgba(47, 128, 237, .12) !important;
        }

        .swal2-popup.delete-confirm-popup .swal2-actions {
            gap: .75rem;
            margin: 0 !important;
        }

        .swal2-popup.delete-confirm-popup .swal2-actions .btn {
            min-width: 130px;
            justify-content: center;
        }

        .sidebar-brand-link {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            text-decoration: none;
        }

        .sidebar-brand-mark {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: linear-gradient(135deg, #34a3ff 0%, #2f80ed 100%);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1rem;
            letter-spacing: .02em;
            flex-shrink: 0;
        }

        .sidebar-brand-wordmark {
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .sidebar-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding-right: 2px;
        }

        .sidebar-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .12);
            border-radius: 999px;
        }

        .app-sidebar-menu-primary {
            padding-inline: 0 !important;
            margin-bottom: 0 !important;
        }

        .app-sidebar-menu-primary .menu-heading {
            color: rgba(177, 188, 214, .55);
            letter-spacing: .08em;
        }

        .app-sidebar-menu-primary .menu-item {
            margin-bottom: 8px;
        }

        .app-sidebar-menu-primary > .menu-item {
            position: relative;
        }

        .app-sidebar-menu-primary .menu-link {
            min-height: 46px;
            border-radius: 14px;
            padding: 0 14px;
            color: var(--admin-sidebar-text);
            transition: background-color .2s ease, color .2s ease;
        }

        .app-sidebar-menu-primary .menu-link:hover,
        .app-sidebar-menu-primary .menu-link.active {
            background: rgba(52, 163, 255, .16);
            color: var(--admin-sidebar-text-active);
        }

        .app-sidebar-menu-primary .menu-icon {
            margin-right: 12px !important;
        }

        .app-sidebar-menu-primary .menu-icon i,
        .app-sidebar-menu-primary .menu-title,
        .app-sidebar-menu-primary .menu-arrow {
            color: currentColor;
        }

        .app-sidebar-menu-primary .menu-sub {
            margin-left: 0;
            padding-left: 14px;
        }

        .app-sidebar-menu-primary .menu-sub .menu-link {
            min-height: 40px;
            border-radius: 12px;
            padding-left: 10px;
        }

        .sidebar-hover-card {
            display: none;
        }

        .sidebar-user-hover-card {
            display: none;
        }

        .app-sidebar-footer {
            margin-top: auto;
            padding-top: 16px;
        }

        .sidebar-user-card {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 56px;
            padding: 10px 12px;
            border-radius: 18px;
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .07);
        }

        .sidebar-user-menu-trigger {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(248, 40, 90, .22);
        }

        .sidebar-user-menu-trigger img,
        .sidebar-user-hover-card .symbol img,
        .sidebar-user-card > .menu-sub-dropdown .symbol img {
            display: block;
            width: 100% !important;
            height: 100% !important;
            max-width: 100% !important;
            max-height: 100% !important;
            object-fit: cover !important;
        }

        .sidebar-user-hover-card .symbol,
        .sidebar-user-card > .menu-sub-dropdown .symbol {
            flex: 0 0 auto;
            overflow: hidden;
            border-radius: 50%;
        }

        .sidebar-user-hover-card .symbol.symbol-45px {
            width: 45px !important;
            height: 45px !important;
            min-width: 45px !important;
            max-width: 45px !important;
        }

        .sidebar-user-card > .menu-sub-dropdown .symbol.symbol-50px {
            width: 50px !important;
            height: 50px !important;
            min-width: 50px !important;
            max-width: 50px !important;
        }

        .sidebar-user-details {
            min-width: 0;
        }

        .sidebar-user-name {
            color: #fff;
            font-size: .9rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .sidebar-user-email {
            color: rgba(191, 202, 230, .72);
            font-size: .75rem;
            line-height: 1.2;
        }

        .sidebar-user-action {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            border: 0;
            background: rgba(255, 255, 255, .12);
            color: #d4ddf8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-user-action:hover {
            background: rgba(255, 255, 255, .2);
            color: #fff;
        }

        .sidebar-user-action.is-danger {
            background: rgba(248, 40, 90, .18);
            color: #ffd9e2;
        }

        .sidebar-user-action.is-danger:hover {
            background: rgba(248, 40, 90, .28);
            color: #fff;
        }

        .sidebar-storage-summary {
            margin-bottom: 8px;
            padding: 12px;
            border-radius: 12px;
            background: #f7f9fc;
            border: 1px solid rgba(15, 23, 42, .06);
        }

        .sidebar-storage-title {
            color: #1f2a44;
            font-size: .82rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .sidebar-storage-meta,
        .sidebar-storage-exact {
            color: #667085;
            font-size: .75rem;
            font-weight: 600;
            line-height: 1.35;
        }

        .sidebar-storage-percent {
            color: var(--bs-primary);
            font-size: .82rem;
            font-weight: 800;
            line-height: 1.2;
            white-space: nowrap;
        }

        .sidebar-storage-progress {
            width: 100%;
            height: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: #e6ebf3;
        }

        .sidebar-storage-progress-bar {
            height: 100%;
            border-radius: inherit;
            background: var(--bs-primary);
        }

        .sidebar-storage-exact {
            margin-top: 8px;
            word-break: break-word;
        }

        .app-main {
            min-height: 100vh;
            margin-left: var(--admin-sidebar-width);
            width: auto;
            min-width: 0;
            transition: margin-left .24s ease;
        }

        .app-shell {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            width: 100%;
            min-width: 0;
        }

        .app-toolbar {
            padding: 28px 0 18px !important;
            background: var(--admin-app-bg);
            overflow: visible;
        }

        #kt_app_toolbar,
        #kt_app_toolbar_container {
            background: var(--admin-app-bg);
            overflow: visible;
        }

        #kt_app_toolbar_container {
            align-items: flex-start !important;
        }

        #kt_app_toolbar_container > :first-child {
            flex: 1 1 420px;
            min-width: 0;
        }

        #kt_app_toolbar_container > :last-child {
            flex: 0 0 auto;
        }

        .app-toolbar .page-heading,
        .app-toolbar .breadcrumb,
        .app-toolbar .breadcrumb-item,
        .app-toolbar a,
        .app-toolbar .text-muted {
            white-space: normal;
            word-break: break-word;
        }

        .app-content {
            flex: 1 1 auto;
            padding-top: 0 !important;
            width: 100%;
            min-width: 0;
            overflow-x: auto;
            overflow-y: visible;
        }

        .app-container {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0;
            padding-left: 20px;
            padding-right: 20px;
        }

        #kt_app_content_container {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0;
            overflow-x: auto;
            overflow-y: visible;
            padding-bottom: 8px;
        }

        #kt_app_toolbar_container,
        #kt_app_content_container,
        .app-footer .app-container {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        #kt_app_content_container > .card,
        #kt_app_content_container > form.card {
            width: 100%;
        }

        .card {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .05);
            box-shadow: 0 8px 26px rgba(15, 23, 42, .045);
        }

        .app-footer {
            background: transparent;
        }

        .app-footer .app-container {
            padding-top: 18px;
            padding-bottom: 10px;
        }

        [data-kt-app-sidebar-minimize="on"] .app-sidebar {
            width: var(--admin-sidebar-mini-width);
        }

        [data-kt-app-sidebar-minimize="on"] .app-sidebar,
        [data-kt-app-sidebar-minimize="on"] .app-sidebar-wrapper,
        [data-kt-app-sidebar-minimize="on"] .sidebar-shell,
        [data-kt-app-sidebar-minimize="on"] .sidebar-scroll {
            overflow: visible !important;
        }

        [data-kt-app-sidebar-minimize="on"] .app-main {
            margin-left: var(--admin-sidebar-mini-width);
        }

        [data-kt-app-sidebar-minimize="on"] .sidebar-brand {
            justify-content: center;
            padding-inline: 0;
        }

        [data-kt-app-sidebar-minimize="on"] .sidebar-brand-link,
        [data-kt-app-sidebar-minimize="on"] .app-sidebar-menu-primary .menu-heading,
        [data-kt-app-sidebar-minimize="on"] .app-sidebar-menu-primary .menu-title,
        [data-kt-app-sidebar-minimize="on"] .app-sidebar-menu-primary .menu-arrow,
        [data-kt-app-sidebar-minimize="on"] .app-sidebar-menu-primary .menu-sub,
        [data-kt-app-sidebar-minimize="on"] .sidebar-user-details,
        [data-kt-app-sidebar-minimize="on"] .sidebar-user-actions {
            display: none !important;
        }

        [data-kt-app-sidebar-minimize="on"] .app-sidebar-menu-primary .menu-link {
            justify-content: center;
            padding-inline: 0;
        }

        [data-kt-app-sidebar-minimize="on"] .app-sidebar-menu-primary .menu-icon {
            margin-right: 0 !important;
        }

        [data-kt-app-sidebar-minimize="on"] .sidebar-user-card {
            justify-content: center;
            padding: 0;
            background: transparent;
            border-color: transparent;
            position: relative;
        }

        [data-kt-app-sidebar-minimize="on"] .sidebar-user-menu-trigger {
            width: 44px;
            height: 44px;
        }

        [data-kt-app-sidebar-minimize="on"] .sidebar-user-card > .menu-sub-dropdown {
            display: none !important;
        }

        [data-kt-app-sidebar-minimize="on"] .app-sidebar-menu-primary > .menu-item:hover > .sidebar-hover-card,
        [data-kt-app-sidebar-minimize="on"] .app-sidebar-menu-primary > .menu-item:focus-within > .sidebar-hover-card {
            display: flex;
            position: absolute;
            left: calc(var(--admin-sidebar-mini-width) - var(--admin-sidebar-hover-overlap));
            top: 50%;
            transform: translateY(-50%);
            z-index: 120;
            min-width: 220px;
            max-width: 280px;
            flex-direction: column;
            gap: 6px;
            padding: 10px;
            border-radius: 14px;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .08);
            box-shadow: 0 18px 45px rgba(15, 23, 42, .16);
        }

        [data-kt-app-sidebar-minimize="on"] .sidebar-hover-card::before {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: calc(var(--admin-sidebar-hover-overlap) * -1);
            width: var(--admin-sidebar-hover-overlap);
        }

        [data-kt-app-sidebar-minimize="on"] .sidebar-hover-title,
        [data-kt-app-sidebar-minimize="on"] .sidebar-hover-link {
            display: flex;
            align-items: center;
            min-height: 36px;
            border-radius: 10px;
            padding: 0 12px;
            color: #1f2a44;
            font-weight: 700;
            text-decoration: none;
            white-space: normal;
            line-height: 1.2;
        }

        [data-kt-app-sidebar-minimize="on"] .sidebar-hover-link {
            color: #667085;
            font-size: .9rem;
            font-weight: 600;
        }

        [data-kt-app-sidebar-minimize="on"] a.sidebar-hover-title:hover,
        [data-kt-app-sidebar-minimize="on"] .sidebar-hover-link:hover,
        [data-kt-app-sidebar-minimize="on"] .sidebar-hover-link.active {
            background: var(--bs-primary-light);
            color: var(--bs-primary);
        }

        [data-kt-app-sidebar-minimize="on"] .sidebar-user-card:hover > .sidebar-user-hover-card,
        [data-kt-app-sidebar-minimize="on"] .sidebar-user-card:focus-within > .sidebar-user-hover-card,
        [data-kt-app-sidebar-minimize="on"] .sidebar-user-hover-card:hover {
            display: flex;
            position: absolute;
            left: calc(var(--admin-sidebar-mini-width) - var(--admin-sidebar-hover-overlap));
            bottom: 0;
            z-index: 120;
            width: 310px;
            max-width: min(310px, calc(100vw - var(--admin-sidebar-mini-width) - 24px));
            max-height: 390px;
            overflow: hidden;
            flex-direction: column;
            gap: 6px;
            padding: 14px;
            border-radius: 14px;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .08);
            box-shadow: 0 18px 45px rgba(15, 23, 42, .16);
        }

        [data-kt-app-sidebar-minimize="on"] .sidebar-user-hover-card::before {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: calc(var(--admin-sidebar-hover-overlap) * -1);
            width: var(--admin-sidebar-hover-overlap);
        }

        .sidebar-hover-button {
            width: 100%;
            border: 0;
            background: transparent;
            text-align: left;
        }

        @media (min-width: 992px) {
            [data-kt-app-sidebar-fixed=true] .app-wrapper {
                margin-left: 30px !important;
            }
        }

        @media (max-width: 991.98px) {
            .app-sidebar {
                width: var(--admin-sidebar-width);
            }

            .app-main {
                margin-left: 0;
            }

            .sidebar-brand-link {
                display: flex !important;
            }

            .sidebar-user-details,
            .sidebar-user-actions,
            .app-sidebar-menu-primary .menu-heading,
            .app-sidebar-menu-primary .menu-title,
            .app-sidebar-menu-primary .menu-arrow,
            .app-sidebar-menu-primary .menu-sub {
                display: initial !important;
            }

            .sidebar-hover-card {
                display: none !important;
            }

            .sidebar-user-hover-card {
                display: none !important;
            }

            .app-container {
                padding-left: 16px;
                padding-right: 16px;
            }
        }

        @media (min-width: 992px) {
            .app-sidebar {
                top: 0 !important;
                bottom: 0 !important;
                overflow: hidden !important;
            }

            .app-sidebar-wrapper {
                height: 100vh !important;
            }

            [data-kt-app-sidebar-minimize="on"] .app-sidebar,
            [data-kt-app-sidebar-minimize="on"] .app-sidebar-wrapper,
            [data-kt-app-sidebar-minimize="on"] .sidebar-shell,
            [data-kt-app-sidebar-minimize="on"] .sidebar-scroll {
                overflow: visible !important;
            }
        }
    </style>

    @stack('styles')
</head>

@php
    $isSidebarMinimized = false;
@endphp

<body id="kt_app_body"
    data-kt-app-sidebar-enabled="true"
    data-kt-app-sidebar-fixed="true"
    data-kt-app-sidebar-hoverable="false"
    @if ($isSidebarMinimized)
        data-kt-app-sidebar-minimize="on"
    @endif
    class="app-default">

    <script>
        var defaultThemeMode = "light";
        var themeMode;
        document.cookie = 'sidebar_minimize_state=off; path=/; max-age=31536000; SameSite=Lax';
        if (document.documentElement) {
            themeMode = localStorage.getItem("data-bs-theme") || defaultThemeMode;
            if (themeMode === "system") {
                themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            }
            document.documentElement.setAttribute("data-bs-theme", themeMode);
        }
    </script>

    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <div class="app-page flex-column flex-column-fluid" id="kt_app_page">
            <div class="app-wrapper" id="kt_app_wrapper">
                @include('partials.sidebar')

                <main class="app-main" id="kt_app_main">
                    <div class="app-shell">
                        @hasSection('toolbar')
                            <div id="kt_app_toolbar" class="app-toolbar">
                                <div id="kt_app_toolbar_container"
                                    class="app-container container-fluid d-flex flex-stack flex-wrap gap-3">
                                    @yield('toolbar')
                                </div>
                            </div>
                        @endif

                           
                                @if (session('status'))
                                    <div class="alert alert-success d-flex align-items-center mb-6">
                                        <i class="ki-outline ki-check-circle fs-2hx text-success me-4"></i>
                                        <div class="fw-semibold">{{ session('status') }}</div>
                                    </div>
                                @endif

                                @if ($errors->any())
                                    <div class="alert alert-danger mb-6">
                                        <div class="fw-bold mb-1">Revisa la informacion capturada.</div>
                                        <ul class="mb-0 ps-4">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @yield('content')

                        @include('partials.footer')
                    </div>
                </main>
            </div>
        </div>
    </div>

    <script>var hostUrl = "{{ asset('/metronic/assets') }}/";</script>
    <script src="{{ asset('/metronic/assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('/metronic/assets/js/scripts.bundle.js') }}"></script>
    <script src="{{ asset('/metronic/assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
    <script src="{{ asset('/metronic/assets/js/widgets.bundle.js') }}"></script>
    <script src="{{ asset('/metronic/assets/js/custom/widgets.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-sidebar-theme-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var currentMode = document.documentElement.getAttribute('data-bs-theme') || 'light';
                    var nextMode = currentMode === 'dark' ? 'light' : 'dark';

                    localStorage.setItem('data-bs-theme', nextMode);
                    document.documentElement.setAttribute('data-bs-theme', nextMode);
                });
            });

            document.querySelectorAll('[data-confirm-delete]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    var requiredText = 'eliminar';
                    var title = form.dataset.confirmTitle || 'Eliminar registro';
                    var text = form.dataset.confirmText || 'Esta accion no se puede deshacer.';

                    event.preventDefault();

                    if (!window.Swal) {
                        if ((window.prompt(text + ' Escribe "' + requiredText + '" para confirmar.') || '').trim().toLowerCase() === requiredText) {
                            form.submit();
                        }

                        return;
                    }

                    Swal.fire({
                        title: title,
                        text: text,
                        icon: 'warning',
                        width: 460,
                        input: 'text',
                        inputPlaceholder: requiredText,
                        inputAttributes: {
                            autocapitalize: 'off',
                            autocomplete: 'off',
                        },
                        showCancelButton: true,
                        buttonsStyling: false,
                        confirmButtonText: 'Eliminar',
                        cancelButtonText: 'Cancelar',
                        customClass: {
                            popup: 'delete-confirm-popup',
                            confirmButton: 'btn btn-danger',
                            cancelButton: 'btn btn-light',
                            input: 'delete-confirm-input',
                        },
                        preConfirm: function (value) {
                            if ((value || '').trim().toLowerCase() !== requiredText) {
                                Swal.showValidationMessage('Escribe eliminar para confirmar.');
                                return false;
                            }

                            return true;
                        },
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            if (window.jQuery && jQuery.fn.DataTable) {
                jQuery('.admin-datatable').each(function () {
                    var table = jQuery(this);

                    if (jQuery.fn.DataTable.isDataTable(this)) {
                        return;
                    }

                    table.DataTable({
                        pageLength: 25,
                        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'Todos']],
                        order: [],
                        autoWidth: false,
                        columnDefs: [
                            {
                                targets: 'no-sort',
                                orderable: false,
                            },
                            {
                                targets: 'no-search',
                                searchable: false,
                            },
                        ],
                        dom: "<'admin-datatable-wrapper'<'row align-items-center g-3 mb-4'<'col-sm-6 d-flex align-items-center'l><'col-sm-6 d-flex justify-content-sm-end'f>>" +
                            "<'row dt-row'<'col-sm-12'tr>>" +
                            "<'row align-items-center g-3 mt-4'<'col-sm-5'i><'col-sm-7'p>>>",
                        language: {
                            search: 'Buscar:',
                            lengthMenu: 'Mostrar _MENU_ registros',
                            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                            infoEmpty: 'Mostrando 0 registros',
                            infoFiltered: '(filtrado de _MAX_ registros)',
                            zeroRecords: 'No se encontraron registros',
                            emptyTable: 'No hay registros disponibles',
                            paginate: {
                                first: 'Primero',
                                previous: 'Anterior',
                                next: 'Siguiente',
                                last: 'Último',
                            },
                            aria: {
                                sortAscending: ': activar para ordenar ascendente',
                                sortDescending: ': activar para ordenar descendente',
                            },
                        },
                    });
                });
            }
        });
    </script>
    @stack('scripts')
</body>

</html>
