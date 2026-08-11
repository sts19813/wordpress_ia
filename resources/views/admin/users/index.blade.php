@extends('layouts.admin')

@section('title', 'Usuarios y permisos | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-2 my-0">Usuarios, roles y permisos</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Controla quién entra al sistema y qué módulos puede administrar.</div>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create-user-modal">
            <i class="ki-outline ki-plus fs-2"></i>Nuevo usuario
        </button>
    </div>
@endsection

@section('content')
    <div class="row g-5 mb-7">
        @foreach ([
            ['value' => $statistics['users'], 'label' => 'Usuarios', 'icon' => 'ki-people', 'color' => 'primary'],
            ['value' => $statistics['active'], 'label' => 'Usuarios activos', 'icon' => 'ki-check-circle', 'color' => 'success'],
            ['value' => $statistics['roles'], 'label' => 'Roles', 'icon' => 'ki-security-user', 'color' => 'info'],
            ['value' => $statistics['permissions'], 'label' => 'Permisos', 'icon' => 'ki-key', 'color' => 'warning'],
        ] as $stat)
            <div class="col-6 col-xl-3">
                <div class="card card-flush h-100">
                    <div class="card-body d-flex align-items-center gap-4 py-5">
                        <div class="symbol symbol-45px"><div class="symbol-label bg-light-{{ $stat['color'] }}"><i class="ki-outline {{ $stat['icon'] }} fs-2 text-{{ $stat['color'] }}"></i></div></div>
                        <div><div class="fs-2 fw-bold text-gray-900">{{ $stat['value'] }}</div><div class="text-muted fw-semibold fs-7">{{ $stat['label'] }}</div></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card card-flush">
        <div class="card-header border-0 pt-6">
            <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x fs-6 fw-semibold border-0" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#users-tab" type="button">Usuarios <span class="badge badge-light ms-1">{{ $statistics['users'] }}</span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#roles-tab" type="button">Roles <span class="badge badge-light ms-1">{{ $statistics['roles'] }}</span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#permissions-tab" type="button">Permisos <span class="badge badge-light ms-1">{{ $statistics['permissions'] }}</span></button></li>
            </ul>
        </div>

        <div class="card-body pt-4">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="users-tab" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed gy-5 admin-datatable" data-page-length="25">
                            <thead><tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0"><th>Usuario</th><th>Estado</th><th>Roles</th><th>Permisos directos</th><th class="text-end no-sort no-search">Acciones</th></tr></thead>
                            <tbody class="fw-semibold text-gray-700">
                                @foreach ($users as $managedUser)
                                    <tr>
                                        <td class="min-w-240px">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="symbol symbol-45px symbol-circle">
                                                    @if ($managedUser->profile_photo_path || $managedUser->google_avatar_url)
                                                        <img src="{{ $managedUser->avatarUrl() }}" alt="{{ $managedUser->name }}">
                                                    @else
                                                        <div class="symbol-label bg-light-primary fw-bold text-primary">{{ $managedUser->initials() }}</div>
                                                    @endif
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="fw-bold text-gray-900 text-truncate">{{ $managedUser->name }}</div>
                                                    <div class="text-muted fs-8 text-truncate">{{ $managedUser->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge {{ $managedUser->is_active ? 'badge-light-success' : 'badge-light-danger' }}">{{ $managedUser->is_active ? 'Activo' : 'Desactivado' }}</span></td>
                                        <td class="min-w-180px">
                                            <div class="d-flex flex-wrap gap-2">
                                                @forelse ($managedUser->roles as $role)<span class="badge badge-light-primary">{{ $role->name }}</span>@empty<span class="text-muted fs-8">Sin rol</span>@endforelse
                                            </div>
                                        </td>
                                        <td class="min-w-220px">
                                            <div class="d-flex flex-wrap gap-2">
                                                @forelse ($managedUser->permissions as $permission)<span class="badge badge-light">{{ $permissionLabels[$permission->name] ?? $permission->name }}</span>@empty<span class="text-muted fs-8">Ninguno</span>@endforelse
                                            </div>
                                        </td>
                                        <td class="text-end"><button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#edit-user-{{ $managedUser->id }}"><i class="ki-outline ki-pencil fs-4"></i>Editar</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="roles-tab" role="tabpanel">
                    <div class="d-flex justify-content-end mb-5"><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create-role-modal"><i class="ki-outline ki-plus fs-2"></i>Nuevo rol</button></div>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed gy-5">
                            <thead><tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0"><th>Rol</th><th>Usuarios</th><th>Permisos incluidos</th><th class="text-end">Acciones</th></tr></thead>
                            <tbody class="fw-semibold text-gray-700">
                                @foreach ($roles as $role)
                                    <tr>
                                        <td><div class="fw-bold text-gray-900">{{ $role->name }}</div>@if(in_array($role->name, $protectedRoles, true))<span class="badge badge-light-info mt-1">Rol base</span>@endif</td>
                                        <td>{{ $role->users_count }}</td>
                                        <td class="min-w-300px"><div class="d-flex flex-wrap gap-2">@forelse($role->permissions as $permission)<span class="badge badge-light-primary">{{ $permissionLabels[$permission->name] ?? $permission->name }}</span>@empty<span class="text-muted fs-8">Sin permisos</span>@endforelse</div></td>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm btn-icon btn-light-primary" data-bs-toggle="modal" data-bs-target="#edit-role-{{ $role->id }}" title="Editar rol"><i class="ki-outline ki-pencil fs-3"></i></button>
                                            @unless(in_array($role->name, $protectedRoles, true))
                                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline" data-confirm-delete data-confirm-title="Eliminar rol" data-confirm-text="El rol solo se eliminará si no tiene usuarios asignados.">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-icon btn-light-danger" title="Eliminar rol"><i class="ki-outline ki-trash fs-3"></i></button></form>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="permissions-tab" role="tabpanel">
                    <div class="alert alert-primary d-flex align-items-start gap-3"><i class="ki-outline ki-information-5 fs-2 mt-1"></i><div><strong>Los permisos funcionales están protegidos.</strong><div class="fs-7">Puedes asignarlos a roles y usuarios. Los permisos personalizados pueden crearse para futuras integraciones.</div></div></div>
                    <div class="d-flex justify-content-end mb-5"><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create-permission-modal"><i class="ki-outline ki-plus fs-2"></i>Nuevo permiso</button></div>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed gy-5">
                            <thead><tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0"><th>Permiso</th><th>Clave</th><th>Asignaciones</th><th class="text-end">Acciones</th></tr></thead>
                            <tbody class="fw-semibold text-gray-700">
                                @foreach ($permissions as $permission)
                                    <tr>
                                        <td class="fw-bold text-gray-900">{{ $permissionLabels[$permission->name] ?? str($permission->name)->replace(['.', '_'], ' ')->headline() }}</td>
                                        <td><code>{{ $permission->name }}</code>@if(in_array($permission->name, $protectedPermissions, true))<span class="badge badge-light-info ms-2">Sistema</span>@endif</td>
                                        <td>{{ $permission->roles_count }} roles · {{ $permission->users_count }} usuarios directos</td>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm btn-icon btn-light-primary" data-bs-toggle="modal" data-bs-target="#edit-permission-{{ $permission->id }}" title="Editar permiso"><i class="ki-outline ki-pencil fs-3"></i></button>
                                            @unless(in_array($permission->name, $protectedPermissions, true))
                                                <form method="POST" action="{{ route('admin.permissions.destroy', $permission) }}" class="d-inline" data-confirm-delete data-confirm-title="Eliminar permiso" data-confirm-text="Debe estar desasignado de roles y usuarios.">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-icon btn-light-danger"><i class="ki-outline ki-trash fs-3"></i></button></form>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="create-user-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><form method="POST" action="{{ route('admin.users.store') }}" class="modal-content">@csrf
            <div class="modal-header"><h2 class="modal-title">Nuevo usuario</h2><button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal"><i class="ki-outline ki-cross fs-2"></i></button></div>
            <div class="modal-body">
                <div class="row g-5 mb-7"><div class="col-md-6"><label class="form-label required">Nombre</label><input type="text" name="name" class="form-control form-control-solid" required></div><div class="col-md-6"><label class="form-label required">Correo</label><input type="email" name="email" class="form-control form-control-solid" required></div><div class="col-md-6"><label class="form-label required">Contraseña</label><input type="password" name="password" class="form-control form-control-solid" autocomplete="new-password" required></div><div class="col-md-6"><label class="form-label required">Confirmar contraseña</label><input type="password" name="password_confirmation" class="form-control form-control-solid" autocomplete="new-password" required></div></div>
                <input type="hidden" name="is_active" value="0"><label class="form-check form-switch form-check-custom form-check-solid mb-8"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked><span class="form-check-label fw-semibold">Cuenta activa</span></label>
                <h3 class="fs-5 mb-4">Roles</h3>@include('admin.users._role-options', ['prefix' => 'create-user', 'selectedRoles' => ['Operador']])
                <div class="separator my-8"></div><h3 class="fs-5 mb-2">Permisos directos</h3><p class="text-muted fs-7 mb-4">Úsalos solo para excepciones; normalmente basta con asignar un rol.</p>@include('admin.users._permission-options', ['prefix' => 'create-user', 'selectedPermissions' => []])
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Crear usuario</button></div>
        </form></div>
    </div>

    @foreach ($users as $managedUser)
        <div class="modal fade" id="edit-user-{{ $managedUser->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><form method="POST" action="{{ route('admin.users.update', $managedUser) }}" class="modal-content">@csrf @method('PUT')
                <div class="modal-header"><div><h2 class="modal-title">Editar usuario</h2><div class="text-muted fs-7">{{ $managedUser->email }}</div></div><button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal"><i class="ki-outline ki-cross fs-2"></i></button></div>
                <div class="modal-body">
                    <div class="row g-5 mb-7"><div class="col-md-6"><label class="form-label required">Nombre</label><input type="text" name="name" value="{{ $managedUser->name }}" class="form-control form-control-solid" required></div><div class="col-md-6"><label class="form-label required">Correo</label><input type="email" name="email" value="{{ $managedUser->email }}" class="form-control form-control-solid" required></div><div class="col-md-6"><label class="form-label">Nueva contraseña</label><input type="password" name="password" class="form-control form-control-solid" autocomplete="new-password"><div class="form-text">Déjala vacía para conservar la actual.</div></div><div class="col-md-6"><label class="form-label">Confirmar contraseña</label><input type="password" name="password_confirmation" class="form-control form-control-solid" autocomplete="new-password"></div></div>
                    <input type="hidden" name="is_active" value="0"><label class="form-check form-switch form-check-custom form-check-solid mb-8"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($managedUser->is_active)><span class="form-check-label fw-semibold">Cuenta activa</span></label>
                    <h3 class="fs-5 mb-4">Roles</h3>@include('admin.users._role-options', ['prefix' => 'edit-user-'.$managedUser->id, 'selectedRoles' => $managedUser->roles->pluck('name')->all()])
                    <div class="separator my-8"></div><h3 class="fs-5 mb-2">Permisos directos</h3><p class="text-muted fs-7 mb-4">Se suman a los permisos heredados de sus roles.</p>@include('admin.users._permission-options', ['prefix' => 'edit-user-'.$managedUser->id, 'selectedPermissions' => $managedUser->permissions->pluck('name')->all()])
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
            </form></div>
        </div>
    @endforeach

    <div class="modal fade" id="create-role-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><form method="POST" action="{{ route('admin.roles.store') }}" class="modal-content">@csrf
            <div class="modal-header"><h2 class="modal-title">Nuevo rol</h2><button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal"><i class="ki-outline ki-cross fs-2"></i></button></div>
            <div class="modal-body"><div class="mb-8"><label class="form-label required">Nombre del rol</label><input type="text" name="name" class="form-control form-control-solid" placeholder="Ej. Editor" required></div><h3 class="fs-5 mb-4">Permisos incluidos</h3>@include('admin.users._permission-options', ['prefix' => 'create-role', 'selectedPermissions' => []])</div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Crear rol</button></div>
        </form></div>
    </div>

    @foreach ($roles as $role)
        <div class="modal fade" id="edit-role-{{ $role->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><form method="POST" action="{{ route('admin.roles.update', $role) }}" class="modal-content">@csrf @method('PUT')
                <div class="modal-header"><h2 class="modal-title">Editar rol</h2><button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal"><i class="ki-outline ki-cross fs-2"></i></button></div>
                <div class="modal-body"><div class="mb-8"><label class="form-label required">Nombre del rol</label><input type="text" name="name" value="{{ $role->name }}" class="form-control form-control-solid" @readonly(in_array($role->name, $protectedRoles, true)) required></div><h3 class="fs-5 mb-4">Permisos incluidos</h3>@include('admin.users._permission-options', ['prefix' => 'edit-role-'.$role->id, 'selectedPermissions' => $role->permissions->pluck('name')->all()])</div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
            </form></div>
        </div>
    @endforeach

    <div class="modal fade" id="create-permission-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('admin.permissions.store') }}" class="modal-content">@csrf
            <div class="modal-header"><h2 class="modal-title">Nuevo permiso</h2><button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal"><i class="ki-outline ki-cross fs-2"></i></button></div>
            <div class="modal-body"><label class="form-label required">Clave del permiso</label><input type="text" name="name" class="form-control form-control-solid" placeholder="ej. reportes.exportar" pattern="[a-z0-9._-]+" required><div class="form-text">Usa una clave estable en minúsculas.</div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Crear permiso</button></div>
        </form></div>
    </div>

    @foreach ($permissions as $permission)
        <div class="modal fade" id="edit-permission-{{ $permission->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('admin.permissions.update', $permission) }}" class="modal-content">@csrf @method('PUT')
                <div class="modal-header"><h2 class="modal-title">Editar permiso</h2><button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal"><i class="ki-outline ki-cross fs-2"></i></button></div>
                <div class="modal-body"><label class="form-label required">Clave del permiso</label><input type="text" name="name" value="{{ $permission->name }}" class="form-control form-control-solid" pattern="[a-z0-9._-]+" @readonly(in_array($permission->name, $protectedPermissions, true)) required>@if(in_array($permission->name, $protectedPermissions, true))<div class="form-text">Este permiso es utilizado por rutas del sistema y su clave está protegida.</div>@endif</div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>@unless(in_array($permission->name, $protectedPermissions, true))<button type="submit" class="btn btn-primary">Guardar cambios</button>@endunless</div>
            </form></div>
        </div>
    @endforeach
@endsection

@push('styles')
<style>
    #kt_app_content_container .modal .border { border-color: #e4e6ef !important; }
    #kt_app_content_container .modal label.border:hover { border-color: var(--bs-primary) !important; background: var(--bs-primary-light); }
    @media (max-width: 767.98px) {
        #kt_app_content_container .nav-tabs { width: 100%; gap: .35rem; }
        #kt_app_content_container .nav-tabs .nav-item { flex: 1 1 auto; }
        #kt_app_content_container .nav-tabs .nav-link { width: 100%; padding-inline: .5rem; }
        #kt_app_content_container .modal-dialog { margin: .75rem; }
    }
</style>
@endpush
