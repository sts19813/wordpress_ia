@php
    $user = Auth::user();
    $menuItems = [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'ki-chart-line'],
        ['label' => 'Noticias', 'route' => 'admin.news.index', 'active' => 'admin.news.*', 'icon' => 'ki-document'],
        ['label' => 'Sitios Fuente', 'route' => 'admin.source-sites.index', 'active' => 'admin.source-sites.*', 'icon' => 'ki-global'],
        ['label' => 'Artículos IA', 'route' => 'admin.ai-articles.index', 'active' => 'admin.ai-articles.*', 'icon' => 'ki-abstract-26'],
        ['label' => 'Imágenes IA', 'route' => 'admin.ai-images.index', 'active' => 'admin.ai-images.*', 'icon' => 'ki-picture'],
        ['label' => 'Publicaciones', 'route' => 'admin.publications.index', 'active' => 'admin.publications.*', 'icon' => 'ki-send'],
        ['label' => 'Programador', 'route' => 'admin.scheduler.index', 'active' => 'admin.scheduler.*', 'icon' => 'ki-calendar-tick'],
        ['label' => 'Logs', 'route' => 'admin.system-logs.index', 'active' => 'admin.system-logs.*', 'icon' => 'ki-code'],
        ['label' => 'Configuración', 'route' => 'admin.settings.index', 'active' => 'admin.settings.*', 'icon' => 'ki-setting-3'],
    ];
@endphp

<aside id="kt_app_sidebar" class="app-sidebar"
    data-kt-drawer="true"
    data-kt-drawer-name="app-sidebar"
    data-kt-drawer-activate="{default: true, lg: false}"
    data-kt-drawer-overlay="true"
    data-kt-drawer-width="250px"
    data-kt-drawer-direction="start"
    data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">

    <div id="kt_app_sidebar_wrapper" class="app-sidebar-wrapper">
        <div class="sidebar-shell">
            <div class="sidebar-brand">
                <button id="kt_app_sidebar_toggle"
                    type="button"
                    class="sidebar-brand-toggle app-sidebar-toggle d-none d-lg-inline-flex"
                    data-kt-toggle="true"
                    data-kt-toggle-state="active"
                    data-kt-toggle-target="body"
                    data-kt-toggle-name="app-sidebar-minimize"
                    aria-label="Contraer menu">
                    <i class="ki-outline ki-menu fs-3"></i>
                </button>

                <button type="button"
                    class="sidebar-brand-toggle d-inline-flex d-lg-none"
                    id="kt_app_sidebar_mobile_toggle"
                    aria-label="Abrir menu">
                    <i class="ki-outline ki-menu fs-3"></i>
                </button>

                <a href="{{ route('admin.dashboard') }}" class="sidebar-brand-link text-decoration-none">
                    <span class="sidebar-brand-mark">WI</span>
                    <span class="sidebar-brand-wordmark">{{ config('app.name') }}</span>
                </a>
            </div>

            <div class="sidebar-scroll">
                <div id="kt_app_sidebar_menu" data-kt-menu="true" data-kt-menu-expand="false"
                    class="app-sidebar-menu-primary menu menu-column">

                    <div class="menu-item pt-3">
                        <div class="menu-content">
                            <span class="menu-heading fw-bold text-uppercase fs-8">Administracion</span>
                        </div>
                    </div>

                    @foreach ($menuItems as $item)
                        <div class="menu-item">
                            <a class="menu-link {{ request()->routeIs($item['active']) ? 'active' : '' }}"
                                href="{{ route($item['route']) }}">
                                <span class="menu-icon"><i class="ki-outline {{ $item['icon'] }} fs-2"></i></span>
                                <span class="menu-title">{{ $item['label'] }}</span>
                            </a>
                            <div class="sidebar-hover-card">
                                <a href="{{ route($item['route']) }}" class="sidebar-hover-title">{{ $item['label'] }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div id="kt_app_sidebar_footer" class="app-sidebar-footer">
                <div class="sidebar-user-card">
                    <div class="sidebar-user-menu-trigger symbol symbol-circle"
                        data-kt-menu-trigger="{default: 'click', lg: 'click'}"
                        data-kt-menu-attach="body"
                        data-kt-menu-placement="right-end"
                        data-kt-menu-offset="18px, 0">
                        @if ($user->profile_photo_path || $user->google_avatar_url)
                            <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="w-100 h-100 rounded-circle" style="object-fit: cover;">
                        @else
                            <div class="symbol-label bg-primary text-white fw-bold w-100 h-100">{{ $user->initials() }}</div>
                        @endif
                    </div>

                    <div class="sidebar-user-details flex-grow-1">
                        <div class="sidebar-user-name text-truncate">{{ $user->name }}</div>
                        <div class="sidebar-user-email text-truncate">{{ $user->email }}</div>
                    </div>

                    <div class="sidebar-user-actions d-flex align-items-center gap-2">
                        <a href="{{ route('admin.account.edit') }}"
                            class="sidebar-user-action"
                            aria-label="Mi perfil">
                            <i class="ki-outline ki-setting-4 fs-5"></i>
                        </a>
                        <a href="#"
                            class="sidebar-user-action is-danger"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                            aria-label="Cerrar sesion">
                            <i class="ki-outline ki-exit-right fs-5"></i>
                        </a>
                    </div>

                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px"
                        data-kt-menu="true">
                        <div class="menu-item px-3">
                            <div class="menu-content d-flex align-items-center px-3">
                                <div class="symbol symbol-50px me-5">
                                    @if ($user->profile_photo_path || $user->google_avatar_url)
                                        <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="w-100 h-100 rounded-circle" style="object-fit: cover;">
                                    @else
                                        <div class="symbol-label bg-primary text-white fw-bold fs-5">{{ $user->initials() }}</div>
                                    @endif
                                </div>

                                <div class="d-flex flex-column min-w-0">
                                    <div class="fw-bold fs-5 text-truncate">{{ $user->name }}</div>
                                    <a href="mailto:{{ $user->email }}" class="fw-semibold text-muted text-hover-primary fs-7 text-truncate">
                                        {{ $user->email }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="separator my-2"></div>

                        <div class="menu-item px-5">
                            <a href="{{ route('admin.account.edit') }}" class="menu-link px-5">Mi perfil</a>
                        </div>

                        <div class="menu-item px-5"
                            data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                            data-kt-menu-placement="right-start"
                            data-kt-menu-offset="8px, 0">
                            <a href="#" class="menu-link px-5">
                                <span class="menu-title position-relative">Modo
                                    <span class="ms-5 position-absolute translate-middle-y top-50 end-0">
                                        <i class="ki-outline ki-night-day theme-light-show fs-2"></i>
                                        <i class="ki-outline ki-moon theme-dark-show fs-2"></i>
                                    </span>
                                </span>
                            </a>
                            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px"
                                data-kt-menu="true" data-kt-element="theme-mode-menu">
                                <div class="menu-item px-3 my-0">
                                    <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="light">
                                        <span class="menu-icon" data-kt-element="icon"><i class="ki-outline ki-night-day fs-2"></i></span>
                                        <span class="menu-title">Claro</span>
                                    </a>
                                </div>
                                <div class="menu-item px-3 my-0">
                                    <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="dark">
                                        <span class="menu-icon" data-kt-element="icon"><i class="ki-outline ki-moon fs-2"></i></span>
                                        <span class="menu-title">Oscuro</span>
                                    </a>
                                </div>
                                <div class="menu-item px-3 my-0">
                                    <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="system">
                                        <span class="menu-icon" data-kt-element="icon"><i class="ki-outline ki-screen fs-2"></i></span>
                                        <span class="menu-title">Sistema</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="separator my-2"></div>

                        <div class="menu-item px-5">
                            <a href="#" class="menu-link px-5"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                Cerrar sesion
                            </a>
                        </div>
                    </div>

                    <div class="sidebar-user-hover-card">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="symbol symbol-45px">
                                @if ($user->profile_photo_path || $user->google_avatar_url)
                                    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="w-100 h-100 rounded-circle" style="object-fit: cover;">
                                @else
                                    <div class="symbol-label bg-primary text-white fw-bold fs-5">{{ $user->initials() }}</div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold text-gray-900 text-truncate">{{ $user->name }}</div>
                                <div class="text-muted fs-8 text-truncate">{{ $user->email }}</div>
                            </div>
                        </div>
                        <a href="{{ route('admin.account.edit') }}" class="sidebar-hover-link">Mi perfil</a>
                        <button type="button" class="sidebar-hover-link sidebar-hover-button" data-sidebar-theme-toggle>
                            Modo
                        </button>
                        <button type="button" class="sidebar-hover-link sidebar-hover-button text-danger"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            Cerrar sesion
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</aside>

<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
    @csrf
</form>
