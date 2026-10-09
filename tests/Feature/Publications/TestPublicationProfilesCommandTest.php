<?php

namespace Tests\Feature\Publications;

use App\Models\WordPressSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TestPublicationProfilesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_checks_active_publication_profiles_without_exposing_tokens(): void
    {
        Http::fake([
            'wp.test/wp-json/wp/v2/users/me*' => Http::response([
                'id' => 7,
                'name' => 'Editor WP',
                'roles' => ['editor'],
            ]),
            'graph.facebook.com/v24.0/me*' => Http::response([
                'id' => '123',
                'name' => 'Pagina Demo',
                'category' => 'Media/news company',
            ]),
            'graph.facebook.com/v24.0/123*' => Http::response([
                'id' => '123',
                'name' => 'Pagina Demo',
                'link' => 'https://www.facebook.com/123',
            ]),
            'graph.facebook.com/v24.0/178*' => Http::response([
                'id' => '178',
                'username' => 'demoig',
            ]),
        ]);

        WordPressSite::query()->create([
            'type' => WordPressSite::TYPE_WORDPRESS,
            'name' => 'WP',
            'rest_api_url' => 'https://wp.test',
            'username' => 'editor',
            'application_password' => 'wp-secret',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);

        WordPressSite::query()->create([
            'type' => WordPressSite::TYPE_FACEBOOK_PAGE,
            'name' => 'Facebook',
            'facebook_page_id' => '123',
            'facebook_access_token' => 'facebook-secret',
            'facebook_api_version' => 'v24.0',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);

        WordPressSite::query()->create([
            'type' => WordPressSite::TYPE_INSTAGRAM,
            'name' => 'Instagram',
            'instagram_account_id' => '178',
            'instagram_access_token' => 'instagram-secret',
            'instagram_api_version' => 'v24.0',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);

        $this->artisan('publication-profiles:test')
            ->expectsOutputToContain('Todos los perfiles probados respondieron correctamente.')
            ->assertSuccessful();
    }

    public function test_it_returns_failure_when_a_profile_fails(): void
    {
        Http::fake([
            'wp.test/wp-json/wp/v2/users/me*' => Http::response([
                'message' => 'Unauthorized',
            ], 401),
        ]);

        WordPressSite::query()->create([
            'type' => WordPressSite::TYPE_WORDPRESS,
            'name' => 'WP',
            'rest_api_url' => 'https://wp.test',
            'username' => 'editor',
            'application_password' => 'wp-secret',
            'status' => WordPressSite::STATUS_ACTIVE,
            'active' => true,
        ]);

        $this->artisan('publication-profiles:test')
            ->expectsOutputToContain('1 perfil(es) fallaron.')
            ->assertFailed();
    }
}
