<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_key_admin_views_render_inside_the_responsive_content_container(): void
    {
        $this->actingAs(User::factory()->create());

        $routes = [
            'admin.quick-posts.create',
            'admin.dashboard',
            'admin.source-sites.index',
            'admin.ai-images.index',
            'admin.scheduler.index',
            'admin.settings.index',
        ];

        foreach ($routes as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee('id="kt_app_content"', false)
                ->assertSee('id="kt_app_content_container"', false);
        }
    }
}
