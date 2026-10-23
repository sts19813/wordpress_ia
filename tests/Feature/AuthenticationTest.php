<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_uses_the_compact_mobile_layout(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('auth-login-shell', false)
            ->assertSee('auth-login-card', false)
            ->assertSee('auth-login-brand', false)
            ->assertSee('assets/img/pulsosur-logo.png', false)
            ->assertDontSee('Regístrate')
            ->assertDontSee('¿No tienes una cuenta?');
    }

    public function test_public_registration_is_not_available(): void
    {
        $response = $this->post('/register', [
            'name' => 'Usuario Prueba',
            'email' => 'usuario@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'usuario@example.com']);
    }

    public function test_a_user_can_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password'), 'is_admin' => false]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.manual-posts.create'));

        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guests_cannot_open_the_admin_area(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.account.edit'))->assertRedirect(route('login'));
    }

    public function test_a_user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)->put(route('admin.account.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_google_login_explains_when_credentials_are_missing(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
        ]);

        $this->get(route('google.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }
}
