@php($user = auth()->user())
<aside id="kt_app_sidebar" class="app-sidebar" data-kt-drawer="true" data-kt-drawer-name="app-sidebar" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="300px" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">
    <div class="sidebar-shell">
        <div class="sidebar-brand">
            <button id="kt_app_sidebar_toggle" type="button" class="sidebar-toggle d-none d-lg-inline-flex" data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body" data-kt-toggle-name="app-sidebar-minimize" aria-label="Contraer menú">
                <i class="ki-outline ki-menu fs-3"></i>
            </button>
            <button type="button" class="sidebar-toggle d-inline-flex d-lg-none" id="kt_app_sidebar_mobile_toggle" aria-label="Abrir menú"><i class="ki-outline ki-menu fs-3"></i></button>
            <a href="{{ route('admin.dashboard') }}" class="sidebar-brand-link">
                <span class="sidebar-brand-mark">WI</span>
                <span class="sidebar-brand-wordmark">{{ config('app.name') }}</span>
            </a>
        </div>

        <div class="sidebar-scroll">
            <div class="sidebar-menu menu menu-column" data-kt-menu="true">
                <div class="menu-item pt-3"><div class="menu-content"><span class="menu-heading fw-bold text-uppercase fs-8">Administración</span></div></div>
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                        <span class="menu-icon"><i class="ki-outline ki-chart-line fs-2"></i></span><span class="menu-title">Dashboard</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a class="menu-link {{ request()->routeIs('admin.account.*') ? 'active' : '' }}" href="{{ route('admin.account.edit') }}">
                        <span class="menu-icon"><i class="ki-outline ki-profile-user fs-2"></i></span><span class="menu-title">Mi cuenta</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <a href="{{ route('admin.account.edit') }}" class="sidebar-avatar symbol">
                    @if ($user->profile_photo_path || $user->google_avatar_url)
                        <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}">
                    @else
                        <span class="symbol-label bg-primary text-white fw-bold w-100 h-100">{{ $user->initials() }}</span>
                    @endif
                </a>
                <div class="sidebar-user-details min-w-0 flex-grow-1">
                    <div class="sidebar-user-name text-truncate">{{ $user->name }}</div>
                    <div class="sidebar-user-email text-truncate">{{ $user->email }}</div>
                </div>
                <button type="submit" form="logout-form" class="sidebar-action" aria-label="Cerrar sesión"><i class="ki-outline ki-exit-right fs-5"></i></button>
            </div>
        </div>
    </div>
</aside>
<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
