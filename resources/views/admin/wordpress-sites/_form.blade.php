@php($isEdit = $site->exists)

<form method="POST" action="{{ $isEdit ? route('admin.wordpress-sites.update', $site) : route('admin.wordpress-sites.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="row g-7">
        <div class="col-xl-8">
            <div class="card card-flush mb-7">
                <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Datos del sitio</h3></div></div>
                <div class="card-body">
                    <div class="row g-6">
                        <div class="col-md-6">
                            <label class="form-label required">Nombre para identificarlo</label>
                            <input type="text" name="name" value="{{ old('name', $site->name) }}" class="form-control form-control-solid @error('name') is-invalid @enderror" placeholder="Blog principal" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Dominio de WordPress</label>
                            <input type="url" name="rest_api_url" value="{{ old('rest_api_url', $site->rest_api_url) }}" class="form-control form-control-solid @error('rest_api_url') is-invalid @enderror" placeholder="https://misitio.com" required>
                            <div class="form-text">Escribe la dirección principal, sin <code>/wp-admin</code> ni <code>/wp-json</code>.</div>
                            @error('rest_api_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Usuario de WordPress</label>
                            <input type="text" name="username" value="{{ old('username', $site->username) }}" class="form-control form-control-solid @error('username') is-invalid @enderror" autocomplete="username" required>
                            @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label {{ $isEdit ? '' : 'required' }}">Contraseña de aplicación</label>
                            <input type="password" name="application_password" class="form-control form-control-solid @error('application_password') is-invalid @enderror" autocomplete="new-password" {{ $isEdit ? '' : 'required' }}>
                            <div class="form-text">{{ $isEdit ? 'Déjala vacía para conservar la actual.' : 'Usa una contraseña de aplicación, no la contraseña normal de tu cuenta.' }}</div>
                            @error('application_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-check form-switch form-check-custom form-check-solid">
                                <input type="hidden" name="active" value="0">
                                <input class="form-check-input" type="checkbox" name="active" value="1" @checked((bool) old('active', $site->active))>
                                <span class="form-check-label fw-semibold text-gray-700">Disponible para publicar</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush mb-7 bg-light-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-5">
                        <i class="ki-outline ki-shield-tick fs-2hx text-primary me-3"></i>
                        <h3 class="fw-bold mb-0">Cómo obtener la contraseña</h3>
                    </div>
                    <ol class="text-gray-700 fw-semibold ps-5 mb-4">
                        <li class="mb-3">Entra al panel de tu WordPress.</li>
                        <li class="mb-3">Abre <strong>Usuarios → Perfil</strong>.</li>
                        <li class="mb-3">Busca <strong>Contraseñas de aplicación</strong>.</li>
                        <li>Crea una llamada “WordPress IA” y copia aquí el valor.</li>
                    </ol>
                    <div class="fs-8 text-muted">La credencial se almacena cifrada y nunca vuelve a mostrarse en pantalla.</div>
                </div>
            </div>

            @if ($site->connection_error)
                <div class="alert alert-danger">
                    <div class="fw-bold mb-2">Última conexión fallida</div>
                    <div>{{ $site->connection_error }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="d-flex justify-content-end gap-3 mt-2">
        <a href="{{ route('admin.wordpress-sites.index') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="ki-outline ki-check fs-2"></i>Guardar y probar conexión
        </button>
    </div>
</form>
