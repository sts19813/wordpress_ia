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
        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSeeInOrder([
                'Dashboard',
                'Contenido',
                'Notas obtenidas',
                'Generar post rápido',
                'Empresas y fuentes',
                'Empresas',
                'Sitios fuente',
                'Bitácora de fuentes',
                'IA y publicación',
                'Notas generadas por IA',
                'Imágenes generadas por IA',
                'Notas publicadas',
                'Programación de eventos',
                'Sistema',
                'Logs del sistema',
                'Configuración',
            ])
            ->assertSee('data-kt-menu-trigger="click"', false)
            ->assertSee('menu-sub-accordion', false)
            ->assertSee(route('admin.quick-posts.index'), false)
            ->assertSee(route('admin.ai-articles.index'), false)
            ->assertSee(route('admin.publications.index'), false)
            ->assertSee(route('admin.scheduler.index'), false);
    }

    public function test_mobile_navigation_has_visible_app_shortcuts_and_a_shared_drawer_toggle(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('mobile-app-header', false)
            ->assertSee('mobile-app-nav', false)
            ->assertSee('data-kt-drawer-toggle=".kt-app-sidebar-mobile-toggle"', false)
            ->assertSee('data-kt-drawer-width="88%"', false)
            ->assertSee('Abrir menú principal')
            ->assertSee('Abrir todos los módulos')
            ->assertSee(route('admin.quick-posts.create'), false);
    }
}
