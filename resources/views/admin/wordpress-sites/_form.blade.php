@php
    $isEdit = $site->exists;
    $selectedType = old('type', $site->type ?: App\Models\WordPressSite::TYPE_WORDPRESS);
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.wordpress-sites.update', $site) : route('admin.wordpress-sites.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="row g-7">
        <div class="col-xl-8">
            <div class="card card-flush mb-7">
                <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Datos del perfil de publicación</h3></div></div>
                <div class="card-body">
                    <div class="row g-6">
                        <div class="col-md-6">
                            <label class="form-label required">Nombre para identificarlo</label>
                            <input type="text" name="name" value="{{ old('name', $site->name) }}" class="form-control form-control-solid @error('name') is-invalid @enderror" placeholder="Blog principal o Facebook Noticias" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Tipo de publicación</label>
                            <select name="type" id="publication-profile-type" class="form-select form-select-solid @error('type') is-invalid @enderror" required>
                                @foreach (App\Models\WordPressSite::typeOptions() as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 publication-platform-fields" data-profile-fields="wordpress">
                            <div class="row g-6">
                                <div class="col-md-6">
                                    <label class="form-label required">Dominio de WordPress</label>
                                    <input type="url" name="rest_api_url" value="{{ old('rest_api_url', $site->rest_api_url) }}" class="form-control form-control-solid @error('rest_api_url') is-invalid @enderror" placeholder="https://misitio.com" data-required-for="wordpress">
                                    <div class="form-text">Dirección principal, sin <code>/wp-admin</code> ni <code>/wp-json</code>.</div>
                                    @error('rest_api_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Usuario de WordPress</label>
                                    <input type="text" name="username" value="{{ old('username', $site->username) }}" class="form-control form-control-solid @error('username') is-invalid @enderror" autocomplete="username" data-required-for="wordpress">
                                    @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label {{ filled($site->application_password) ? '' : 'required' }}">Contraseña de aplicación</label>
                                    <input type="password" name="application_password" class="form-control form-control-solid @error('application_password') is-invalid @enderror" autocomplete="new-password" data-required-for="wordpress" data-required-on-create="{{ filled($site->application_password) ? '0' : '1' }}">
                                    <div class="form-text">{{ filled($site->application_password) ? 'Déjala vacía para conservar la actual.' : 'Usa una contraseña de aplicación, no la contraseña normal de tu cuenta.' }}</div>
                                    @error('application_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-12 publication-platform-fields" data-profile-fields="facebook_page">
                            <div class="alert alert-info mb-6">
                                <div class="fw-bold mb-1">Publicación automática disponible para páginas</div>
                                <div>Facebook no permite publicar automáticamente en perfiles personales. Conecta una página que administres mediante su Page Access Token.</div>
                            </div>
                            <div class="row g-6">
                                <div class="col-md-6">
                                    <label class="form-label required">ID de la página de Facebook</label>
                                    <input type="text" inputmode="numeric" name="facebook_page_id" value="{{ old('facebook_page_id', $site->facebook_page_id) }}" class="form-control form-control-solid @error('facebook_page_id') is-invalid @enderror" placeholder="123456789012345" data-required-for="facebook_page">
                                    @error('facebook_page_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Versión de Graph API</label>
                                    <input type="text" name="facebook_api_version" value="{{ old('facebook_api_version', $site->facebook_api_version ?: 'v24.0') }}" class="form-control form-control-solid @error('facebook_api_version') is-invalid @enderror" placeholder="v24.0" data-required-for="facebook_page">
                                    @error('facebook_api_version')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label {{ filled($site->facebook_access_token) ? '' : 'required' }}">Page Access Token</label>
                                    <textarea name="facebook_access_token" rows="3" class="form-control form-control-solid @error('facebook_access_token') is-invalid @enderror" autocomplete="off" data-required-for="facebook_page" data-required-on-create="{{ filled($site->facebook_access_token) ? '0' : '1' }}" placeholder="{{ filled($site->facebook_access_token) ? 'Déjalo vacío para conservar el token actual' : 'Pega aquí el token de acceso de la página' }}"></textarea>
                                    <div class="form-text">El token debe poder administrar publicaciones de esta página. Se almacena cifrado y no volverá a mostrarse.</div>
                                    @error('facebook_access_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
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
            <div class="card card-flush mb-7 bg-light-primary publication-platform-help" data-profile-help="wordpress">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-5">
                        <i class="ki-outline ki-shield-tick fs-2hx text-primary me-3"></i>
                        <h3 class="fw-bold mb-0">Conectar WordPress</h3>
                    </div>
                    <ol class="text-gray-700 fw-semibold ps-5 mb-4">
                        <li class="mb-3">Entra al panel de tu WordPress.</li>
                        <li class="mb-3">Abre <strong>Usuarios → Perfil</strong>.</li>
                        <li class="mb-3">Busca <strong>Contraseñas de aplicación</strong>.</li>
                        <li>Crea una llamada “WordPress IA” y copia aquí el valor.</li>
                    </ol>
                    <div class="fs-8 text-muted">La credencial se almacena cifrada y nunca vuelve a mostrarse.</div>
                </div>
            </div>

            <div class="card card-flush mb-7 bg-light-primary publication-platform-help" data-profile-help="facebook_page">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-5">
                        <i class="ki-outline ki-facebook fs-2hx text-primary me-3"></i>
                        <h3 class="fw-bold mb-0">Conectar una página</h3>
                    </div>
                    <ol class="text-gray-700 fw-semibold ps-5 mb-4">
                        <li class="mb-3">Crea o usa una app de Meta vinculada a tu negocio.</li>
                        <li class="mb-3">Autoriza <code>pages_show_list</code>, <code>pages_read_engagement</code> y <code>pages_manage_posts</code>.</li>
                        <li class="mb-3">Obtén el Page Access Token de la página que administras.</li>
                        <li>Pega el ID y el token; al guardar se comprobará la conexión.</li>
                    </ol>
                    <div class="fs-8 text-muted">La aplicación publicará el título, resumen, enlace disponible e imagen generada.</div>
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
            <i class="ki-outline ki-check fs-2"></i>Guardar y probar perfil
        </button>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selector = document.getElementById('publication-profile-type');
    if (!selector) return;

    const refreshProfileFields = function () {
        const selected = selector.value;

        document.querySelectorAll('[data-profile-fields], [data-profile-help]').forEach(function (element) {
            const platform = element.dataset.profileFields || element.dataset.profileHelp;
            element.classList.toggle('d-none', platform !== selected);
        });

        document.querySelectorAll('[data-required-for]').forEach(function (field) {
            const needsValue = field.dataset.requiredFor === selected && field.dataset.requiredOnCreate !== '0';
            field.required = needsValue;
            field.disabled = field.dataset.requiredFor !== selected;
        });
    };

    selector.addEventListener('change', refreshProfileFields);
    refreshProfileFields();
});
</script>
@endpush
