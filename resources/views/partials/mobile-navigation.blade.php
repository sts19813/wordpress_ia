@php
    $mobileNavigation = [
        ['label' => 'Inicio', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'ki-home-2'],
        ['label' => 'Noticias', 'route' => 'admin.news.index', 'active' => 'admin.news.*', 'icon' => 'ki-document'],
        ['label' => 'Crear', 'route' => 'admin.quick-posts.create', 'active' => 'admin.quick-posts.*', 'icon' => 'ki-flash-circle'],
        ['label' => 'IA', 'route' => 'admin.ai-articles.index', 'active' => 'admin.ai-articles.*', 'icon' => 'ki-abstract-26'],
    ];
@endphp

<header class="mobile-app-header d-flex d-lg-none">
    <button type="button" class="mobile-app-icon-button kt-app-sidebar-mobile-toggle" aria-label="Abrir menú principal">
        <i class="ki-outline ki-menu fs-2"></i>
    </button>

    <a href="{{ route('admin.dashboard') }}" class="mobile-app-brand text-decoration-none">
        <span class="mobile-app-brand-mark">WI</span>
        <span class="mobile-app-brand-name">{{ config('app.name') }}</span>
    </a>

    <a href="{{ route('admin.account.edit') }}" class="mobile-app-icon-button" aria-label="Mi perfil">
        <i class="ki-outline ki-profile-circle fs-2"></i>
    </a>
</header>

<nav class="mobile-app-nav d-flex d-lg-none" aria-label="Navegación principal móvil">
    @foreach ($mobileNavigation as $item)
        <a href="{{ route($item['route']) }}"
            class="mobile-app-nav-item {{ request()->routeIs($item['active']) ? 'active' : '' }}">
            <i class="ki-outline {{ $item['icon'] }}"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach

    <button type="button" class="mobile-app-nav-item kt-app-sidebar-mobile-toggle" aria-label="Abrir todos los módulos">
        <i class="ki-outline ki-category"></i>
        <span>Menú</span>
    </button>
</nav>
