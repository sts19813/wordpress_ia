<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_uses_collapsible_modules_in_workflow_order(): void
    {
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSeeInOrder([
                'Dashboard',
                'Post rápido',
                'Generar post',
                'Bitácora de publicación',
                'Contenido',
                'Notas obtenidas',
                'Empresas y fuentes',
                'Empresas',
                'Sitios fuente',
                'Bitácora de fuentes',
                'IA y publicación',
                'Notas generadas por IA',
                'Imágenes generadas por IA',
                'Resumen de producción',
                'Notas publicadas',
                'Programación de eventos',
                'Sistema',
                'Logs del sistema',
                'Configuración',
            ])
            ->assertSee('data-kt-menu-trigger="click"', false)
            ->assertSee('menu-sub-accordion', false)
            ->assertSee('--admin-sidebar-submenu-text: #f1f5ff;', false)
            ->assertSee('--admin-sidebar-selected: #075fd1;', false)
            ->assertSee(route('admin.manual-posts.create'), false)
            ->assertSee(route('admin.quick-posts.index'), false)
            ->assertSee(route('admin.ai-articles.index'), false)
            ->assertSee(route('admin.ai-production-report.index'), false)
            ->assertSee(route('admin.publications.index'), false)
            ->assertSee(route('admin.scheduler.index'), false);
    }

    public function test_mobile_navigation_has_visible_app_shortcuts_and_a_shared_drawer_toggle(): void
    {
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('mobile-app-header', false)
            ->assertSee('mobile-app-nav', false)
            ->assertSee('data-kt-drawer-toggle=".kt-app-sidebar-mobile-toggle"', false)
            ->assertSee('data-kt-drawer-width="92%"', false)
            ->assertSee('Abrir menú principal')
            ->assertSee('Abrir todos los módulos')
            ->assertSee(route('admin.manual-posts.create'), false);
    }

    public function test_operator_navigation_only_shows_quick_post_and_account_access(): void
    {
        $operator = User::factory()->create(['is_admin' => false]);

        $this->actingAs($operator)
            ->get(route('admin.manual-posts.create'))
            ->assertOk()
            ->assertSee('Post rápido')
            ->assertSee('Generar post')
            ->assertSee('Bitácora de publicación')
            ->assertSee('Mi perfil')
            ->assertDontSee('Dashboard')
            ->assertDontSee(route('admin.news.index'), false)
            ->assertDontSee(route('admin.companies.index'), false)
            ->assertDontSee(route('admin.ai-articles.index'), false)
            ->assertDontSee(route('admin.settings.index'), false)
            ->assertDontSee('Ayuda de esta página')
            ->assertDontSee('Perfiles</a>', false);
    }
}
