<?php

namespace Tests\Feature\News;

use App\Models\SourcePost;
use App\Models\SourceSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_a_source_post_from_admin_news(): void
    {
        $user = User::factory()->create();
        $sourceSite = SourceSite::query()->create([
            'name' => 'Fuente',
            'url' => 'https://example.com',
            'type' => SourceSite::TYPE_WORDPRESS_REST,
            'status' => SourceSite::STATUS_ACTIVE,
            'frequency_minutes' => 60,
            'language' => 'es',
            'priority' => 5,
            'auth_method' => SourceSite::AUTH_NONE,
            'active' => true,
        ]);
        $sourcePost = SourcePost::query()->create([
            'source_site_id' => $sourceSite->id,
            'title' => 'Nota para eliminar',
            'content' => 'Contenido',
            'content_html' => '<p>Contenido</p>',
            'url' => 'https://example.com/nota-para-eliminar',
            'hash' => hash('sha256', 'nota-para-eliminar'),
            'status' => SourcePost::STATUS_FETCHED,
            'language' => 'es',
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('admin.news.destroy', $sourcePost));

        $response
            ->assertRedirect(route('admin.news.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('source_posts', [
            'id' => $sourcePost->id,
        ]);
    }
}
