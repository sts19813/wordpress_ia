<?php

use App\Models\User;
use App\Support\SystemPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

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

        $roles = DB::table('roles')
            ->whereIn('name', ['Administrador', 'Operador'])
            ->where('guard_name', 'web')
            ->pluck('id', 'name');
        $permissions = DB::table('permissions')
            ->whereIn('name', SystemPermissions::names())
            ->where('guard_name', 'web')
            ->pluck('id', 'name');

        if (isset($roles['Administrador'])) {
            foreach ($permissions as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roles['Administrador'],
                ]);
            }
        }

        if (isset($roles['Operador'], $permissions['post-rapido.gestionar'])) {
            DB::table('role_has_permissions')->where('role_id', $roles['Operador'])->delete();
            DB::table('role_has_permissions')->insert([
                'permission_id' => $permissions['post-rapido.gestionar'],
                'role_id' => $roles['Operador'],
            ]);

            $operatorIds = DB::table('model_has_roles')
                ->where('role_id', $roles['Operador'])
                ->where('model_type', User::class)
                ->pluck('model_id');

            DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->whereIn('model_id', $operatorIds)
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $operatorRoleId = DB::table('roles')
            ->where('name', 'Operador')
            ->where('guard_name', 'web')
            ->value('id');
        $legacyOperatorPermissions = [
            'contenido.gestionar',
            'empresas.gestionar',
            'ia.gestionar',
            'publicaciones.gestionar',
            'logs.ver',
            'configuracion.gestionar',
        ];

        if ($operatorRoleId) {
            DB::table('role_has_permissions')->where('role_id', $operatorRoleId)->delete();
            $permissionIds = DB::table('permissions')
                ->whereIn('name', $legacyOperatorPermissions)
                ->where('guard_name', 'web')
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $operatorRoleId,
                ]);
            }
        }

        $quickPermissionId = DB::table('permissions')
            ->where('name', 'post-rapido.gestionar')
            ->where('guard_name', 'web')
            ->value('id');

        if ($quickPermissionId) {
            DB::table('role_has_permissions')->where('permission_id', $quickPermissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $quickPermissionId)->delete();
            DB::table('permissions')->where('id', $quickPermissionId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
