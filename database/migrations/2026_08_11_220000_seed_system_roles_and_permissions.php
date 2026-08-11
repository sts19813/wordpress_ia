<?php

use App\Models\User;
use App\Support\SystemPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (SystemPermissions::names() as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach (['Administrador', 'Operador'] as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        $roles = DB::table('roles')->whereIn('name', ['Administrador', 'Operador'])->pluck('id', 'name');
        $permissions = DB::table('permissions')->whereIn('name', SystemPermissions::names())->pluck('id', 'name');

        foreach ($permissions as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $roles['Administrador'],
            ]);
        }

        foreach (SystemPermissions::operatorDefaults() as $permission) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissions[$permission],
                'role_id' => $roles['Operador'],
            ]);
        }

        DB::table('users')->orderBy('id')->each(function (object $user) use ($roles): void {
            $role = $user->is_admin ? 'Administrador' : 'Operador';

            DB::table('model_has_roles')->updateOrInsert([
                'role_id' => $roles[$role],
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);
        });
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('name', ['Administrador', 'Operador'])->delete();
        DB::table('permissions')->whereIn('name', SystemPermissions::names())->delete();
    }
};
