<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SystemPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserAccessController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['roles:id,name', 'permissions:id,name'])
            ->orderBy('name')
            ->get();
        $roles = Role::query()->with('permissions:id,name')->withCount('users')->orderBy('name')->get();
        $permissions = Permission::query()->withCount(['roles', 'users'])->orderBy('name')->get();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
            'permissionLabels' => SystemPermissions::labels(),
            'protectedPermissions' => SystemPermissions::names(),
            'protectedRoles' => ['Administrador', 'Operador'],
            'statistics' => [
                'users' => $users->count(),
                'active' => $users->where('is_active', true)->count(),
                'roles' => $roles->count(),
                'permissions' => $permissions->count(),
            ],
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);

        DB::transaction(function () use ($validated): void {
            $roleNames = $validated['role_names'] ?? [];
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
                'is_active' => $validated['is_active'],
                'is_admin' => $this->containsAdminRole($roleNames),
            ]);

            $user->syncRoles($roleNames);
            $user->syncPermissions($validated['permission_names'] ?? []);
        });

        return back()->with('status', 'Usuario creado correctamente.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);
        $roleNames = $validated['role_names'] ?? [];
        $willBeAdmin = $this->containsAdminRole($roleNames);

        if ($request->user()->is($user) && (! $validated['is_active'] || ! $willBeAdmin)) {
            return back()->withErrors([
                'user' => 'No puedes desactivar tu propia cuenta ni retirar tu acceso de administrador.',
            ]);
        }

        if ($user->isAdmin() && (! $validated['is_active'] || ! $willBeAdmin) && $this->activeAdministratorCount() <= 1) {
            return back()->withErrors([
                'user' => 'Debe permanecer al menos un administrador activo en el sistema.',
            ]);
        }

        DB::transaction(function () use ($user, $validated, $roleNames, $willBeAdmin): void {
            $attributes = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_active' => $validated['is_active'],
                'is_admin' => $willBeAdmin,
            ];

            if (filled($validated['password'] ?? null)) {
                $attributes['password'] = Hash::make($validated['password']);
            }

            $user->update($attributes);
            $user->syncRoles($roleNames);
            $user->syncPermissions($validated['permission_names'] ?? []);
        });

        return back()->with('status', 'Usuario actualizado correctamente.');
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $this->validateRole($request);
        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permission_names'] ?? []);

        return back()->with('status', 'Rol creado correctamente.');
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $validated = $this->validateRole($request, $role);

        if (in_array($role->name, ['Administrador', 'Operador'], true) && $validated['name'] !== $role->name) {
            return back()->withErrors(['role' => 'Los roles base del sistema no se pueden renombrar.']);
        }

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permission_names'] ?? []);

        return back()->with('status', 'Rol actualizado correctamente.');
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        if (in_array($role->name, ['Administrador', 'Operador'], true)) {
            return back()->withErrors(['role' => 'Los roles base del sistema no se pueden eliminar.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'No puedes eliminar un rol que todavía tiene usuarios asignados.']);
        }

        $role->delete();

        return back()->with('status', 'Rol eliminado correctamente.');
    }

    public function storePermission(Request $request): RedirectResponse
    {
        $validated = $this->validatePermission($request);
        Permission::create(['name' => $validated['name'], 'guard_name' => 'web']);

        return back()->with('status', 'Permiso creado correctamente.');
    }

    public function updatePermission(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $this->validatePermission($request, $permission);

        if (in_array($permission->name, SystemPermissions::names(), true) && $validated['name'] !== $permission->name) {
            return back()->withErrors(['permission' => 'Los permisos funcionales del sistema no se pueden renombrar.']);
        }

        $permission->update(['name' => $validated['name']]);

        return back()->with('status', 'Permiso actualizado correctamente.');
    }

    public function destroyPermission(Permission $permission): RedirectResponse
    {
        if (in_array($permission->name, SystemPermissions::names(), true)) {
            return back()->withErrors(['permission' => 'Los permisos funcionales del sistema no se pueden eliminar.']);
        }

        if ($permission->roles()->exists() || $permission->users()->exists()) {
            return back()->withErrors(['permission' => 'Desasigna este permiso de todos los roles y usuarios antes de eliminarlo.']);
        }

        $permission->delete();

        return back()->with('status', 'Permiso eliminado correctamente.');
    }

    /** @return array<string, mixed> */
    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'is_active' => ['required', 'boolean'],
            'role_names' => ['nullable', 'array'],
            'role_names.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'permission_names' => ['nullable', 'array'],
            'permission_names.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role)],
            'permission_names' => ['nullable', 'array'],
            'permission_names.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatePermission(Request $request, ?Permission $permission = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:125', 'regex:/^[a-z0-9._-]+$/', Rule::unique('permissions', 'name')->where('guard_name', 'web')->ignore($permission)],
        ], [
            'name.regex' => 'Usa minúsculas, números, puntos, guiones o guiones bajos.',
        ]);
    }

    /** @param array<int, string> $roles */
    private function containsAdminRole(array $roles): bool
    {
        return collect($roles)->contains(fn (string $role) => in_array(mb_strtolower($role), ['administrador', 'admin'], true));
    }

    private function activeAdministratorCount(): int
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('is_admin', true)
                    ->orWhereHas('roles', fn ($roles) => $roles->whereIn('name', ['Administrador', 'Admin']));
            })
            ->count();
    }
}
