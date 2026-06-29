<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <base href="{{ asset('/') }}">
    <title>@yield('title', config('app.name').' | Auth')</title>

    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="{{ config('app.name') }} - Administración" />

    <link rel="shortcut icon" href="{{ asset('/metronic/assets/media/logos/favicon.ico') }}" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <link href="{{ asset('/metronic/assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" />
    <link href="{{ asset('/metronic/assets/css/style.bundle.css') }}" rel="stylesheet" />

    @stack('styles')
</head>

<body id="kt_body" class="app-blank bgi-size-cover bgi-attachment-fixed bgi-position-center bgi-no-repeat">
    <script>
        var defaultThemeMode = "light";
        var themeMode;
        if (document.documentElement) {
            if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
                themeMode = document.documentElement.getAttribute("data-bs-theme-mode");
            } else {
                if (localStorage.getItem("data-bs-theme") !== null) {
                    themeMode = localStorage.getItem("data-bs-theme");
                } else {
                    themeMode = defaultThemeMode;
                }
            }
            if (themeMode === "system") {
                themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            }
            document.documentElement.setAttribute("data-bs-theme", themeMode);
        }
    </script>

    <div class="d-flex flex-column flex-root" id="kt_app_root">
        <style>
            body {
                background-image: url('/metronic/assets/media/auth/bg2.jpg');
            }

            [data-bs-theme="dark"] body {
                background-image: url('/metronic/assets/media/auth/bg2-dark.jpg');
            }
        </style>

        <div class="d-flex flex-column flex-column-fluid flex-lg-row">
            <div class="d-flex flex-center w-lg-50 pt-15 pt-lg-0 px-10">
                <div class="d-flex flex-center flex-lg-start flex-column">
                    <a href="{{ url('/') }}" class="mb-7">
                        <img width="400px" alt="{{ config('app.name') }}" src="{{ asset('assets/img/wordpress-ia-logo.svg') }}" />
                    </a>
                    <h2 class="text-white fw-normal m-0"></h2>
                </div>
            </div>

            @yield('content')
        </div>
    </div>

    <script>var hostUrl = "/metronic/assets/";</script>
    <script src="/metronic/assets/plugins/global/plugins.bundle.js"></script>
    <script src="/metronic/assets/js/scripts.bundle.js"></script>
    @stack('scripts')
</body>

</html>
