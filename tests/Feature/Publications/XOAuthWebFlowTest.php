<?php

namespace Tests\Feature\Publications;

use App\Models\User;
use App\Models\WordPressSite;
use App\Services\PublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XOAuthWebFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_x_profile_starts_web_authorization_without_a_manual_access_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.wordpress-sites.store'), [
            'name' => 'X Noticias',
            'type' => WordPressSite::TYPE_X,
            'x_client_id' => 'client-id-123',
            'x_client_secret' => 'client-secret-456',
            'active' => '1',
        ]);

        $profile = WordPressSite::query()->sole();
        $response->assertRedirect(route('admin.x-oauth.redirect', $profile));
        $this->assertSame('client-id-123', $profile->x_client_id);
        $this->assertSame('client-secret-456', $profile->x_client_secret);
        $this->assertNotSame('client-secret-456', DB::table('wordpress_sites')->value('x_client_secret'));
        $this->assertNull($profile->x_access_token);
    }

    public function test_x_profile_can_be_connected_with_portal_generated_oauth_tokens(): void
    {
        Http::fake([
            'api.x.com/2/users/me' => Http::response([
                'data' => [
                    'id' => '324573630',
                    'name' => 'STS',
                    'username' => 'sts19813',
                ],
            ]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.wordpress-sites.store'), [
            'name' => 'X directo',
            'type' => WordPressSite::TYPE_X,
            'x_client_id' => 'client-id-123',
            'x_client_secret' => 'client-secret-456',
            'x_access_token' => 'portal-access-token',
            'x_refresh_token' => 'portal-refresh-token',
            'active' => '1',
        ])->assertRedirect(route('admin.wordpress-sites.index'));

        $profile = WordPressSite::query()->sole();
        $this->assertSame('portal-access-token', $profile->x_access_token);
        $this->assertSame('portal-refresh-token', $profile->x_refresh_token);
        $this->assertSame('sts19813', $profile->x_username);
        $this->assertSame(WordPressSite::STATUS_ACTIVE, $profile->status);
        $this->assertTrue($profile->x_token_expires_at->isFuture());
    }

    public function test_browser_oauth_callback_stores_tokens_and_verifies_the_x_account(): void
    {
        Http::fake([
            'api.x.com/2/oauth2/token' => Http::response([
                'token_type' => 'bearer',
                'access_token' => 'web-access-token',
                'refresh_token' => 'web-refresh-token',
                'expires_in' => 7200,
                'scope' => 'tweet.read users.read tweet.write media.write offline.access',
            ]),
            'api.x.com/2/users/me' => Http::response([
                'data' => [
                    'id' => '2244994945',
                    'name' => 'Noticias X',
                    'username' => 'noticias_x',
                ],
            ]),
        ]);
        $user = User::factory()->create();
        $profile = $this->xProfile($user);

        $authorization = $this->actingAs($user)->get(route('admin.x-oauth.redirect', $profile));
        $authorization->assertRedirectContains('https://x.com/i/oauth2/authorize?');
        $location = $authorization->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertStringContainsString('media.write', $query['scope']);
        $this->assertSame(route('x-oauth.callback'), $query['redirect_uri']);

        $this->actingAs($user)
            ->get(route('x-oauth.callback', [
                'code' => 'authorization-code',
                'state' => $query['state'],
            ]))
            ->assertRedirect(route('admin.wordpress-sites.index'))
            ->assertSessionHas('status');

        $profile->refresh();
        $this->assertSame('web-access-token', $profile->x_access_token);
        $this->assertSame('web-refresh-token', $profile->x_refresh_token);
        $this->assertSame('2244994945', $profile->x_user_id);
        $this->assertSame('noticias_x', $profile->x_username);
        $this->assertSame(WordPressSite::STATUS_ACTIVE, $profile->status);
        $this->assertTrue($profile->x_token_expires_at->isFuture());
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.x.com/2/oauth2/token'
            && $request['grant_type'] === 'authorization_code');
    }

    public function test_expired_x_access_token_is_refreshed_automatically(): void
    {
        Http::fake([
            'api.x.com/2/oauth2/token' => Http::response([
                'access_token' => 'renewed-access-token',
                'refresh_token' => 'renewed-refresh-token',
                'expires_in' => 7200,
            ]),
            'api.x.com/2/users/me' => Http::response([
                'data' => [
                    'id' => '2244994945',
                    'name' => 'Noticias X',
                    'username' => 'noticias_x',
                ],
            ]),
        ]);
        $profile = $this->xProfile(User::factory()->create(), [
            'x_access_token' => 'expired-token',
            'x_refresh_token' => 'old-refresh-token',
            'x_token_expires_at' => now()->subMinute(),
        ]);

        $connection = app(PublicationService::class)->testConnection($profile);

        $this->assertSame('noticias_x', $connection['x_username']);
        $this->assertSame('renewed-access-token', $profile->fresh()->x_access_token);
        $this->assertSame('renewed-refresh-token', $profile->fresh()->x_refresh_token);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.x.com/2/users/me'
            && $request->hasHeader('Authorization', 'Bearer renewed-access-token'));
    }

    private function xProfile(User $user, array $attributes = []): WordPressSite
    {
        return WordPressSite::query()->create([
            'user_id' => $user->id,
            'type' => WordPressSite::TYPE_X,
            'name' => 'X Noticias',
            'x_client_id' => 'client-id-123',
            'x_client_secret' => 'client-secret-456',
            'status' => WordPressSite::STATUS_PAUSED,
            'active' => true,
            ...$attributes,
        ]);
    }
}
