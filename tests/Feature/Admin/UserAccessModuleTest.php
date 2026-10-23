<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserAccessModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_the_users_roles_and_permissions_module(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Usuarios, roles y permisos')
            ->assertSee('Administrador')
            ->assertSee('Operador')
            ->assertSee('Gestionar empresas y fuentes');
    }

    public function test_operator_cannot_manage_users_without_the_permission(): void
    {
        $operator = User::factory()->create(['is_admin' => false]);

        $this->actingAs($operator)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_operator_profiles_cannot_receive_direct_permissions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Permission::findOrCreate('reportes.exportar');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Editora Demo',
                'email' => 'editora@example.com',
                'password' => 'password-seguro',
                'password_confirmation' => 'password-seguro',
                'is_active' => '1',
                'role_names' => ['Operador'],
                'permission_names' => ['reportes.exportar'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user = User::query()->where('email', 'editora@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password-seguro', $user->password));
        $this->assertTrue($user->hasRole('Operador'));
        $this->assertFalse($user->hasDirectPermission('reportes.exportar'));
        $this->assertTrue($user->can('post-rapido.gestionar'));
    }

    public function test_administrator_can_delete_another_user_but_not_their_own_account(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $managedUser = User::factory()->create(['name' => 'Usuario para eliminar', 'is_admin' => false]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(route('admin.users.destroy', $managedUser), false)
            ->assertSee('aria-label="Eliminar usuario Usuario para eliminar"', false);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $managedUser))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('users', ['id' => $managedUser->id]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertRedirect()
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_administrator_cannot_delete_users_even_with_user_management_permission(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $nonAdmin->givePermissionTo('usuarios.gestionar');
        $managedUser = User::factory()->create(['is_admin' => false]);

        $this->actingAs($nonAdmin)
            ->delete(route('admin.users.destroy', $managedUser))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $managedUser->id]);
    }

    public function test_operator_is_restricted_to_quick_post_routes(): void
    {
        $operator = User::factory()->create(['is_admin' => false]);

        $this->actingAs($operator)->get(route('admin.manual-posts.create'))->assertOk();
        $this->actingAs($operator)->get(route('admin.quick-posts.index'))->assertOk();
        $this->actingAs($operator)->get(route('admin.account.edit'))->assertOk();
        $this->actingAs($operator)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.news.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.companies.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.source-sites.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.ai-articles.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.scheduler.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.system-logs.index'))->assertForbidden();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'inactivo@example.com',
            'password' => Hash::make('password'),
            'is_active' => false,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
