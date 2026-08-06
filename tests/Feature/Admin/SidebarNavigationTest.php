<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_uses_clear_grouped_navigation_labels_in_workflow_order(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSeeInOrder([
                'General',
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
            ->assertSee(route('admin.quick-posts.index'), false)
            ->assertSee(route('admin.ai-articles.index'), false)
            ->assertSee(route('admin.publications.index'), false)
            ->assertSee(route('admin.scheduler.index'), false);
    }
}
