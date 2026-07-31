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
                            <input type="text" name="name" value="{{ old('name', $site->name) }}" class="form-control form-control-solid @error('name') is-invalid @enderror" placeholder="Blog principal, Instagram o X" required autofocus>
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
                                    <div class="form-text">Puedes pegar un Page Access Token o un User Access Token con <code>pages_show_list</code> y <code>pages_manage_posts</code>. Si solo administra una página, la aplicación obtendrá y guardará automáticamente el token correcto de la página.</div>
                                    @error('facebook_access_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-12 publication-platform-fields" data-profile-fields="instagram">
                            <div class="alert alert-info mb-6">
                                <div class="fw-bold mb-1">Disponible para cuentas profesionales</div>
                                <div>Instagram permite publicar por API en cuentas Business o Creator. Cada publicación requiere una imagen generada.</div>
                            </div>
                            <div class="row g-6">
                                <div class="col-md-6">
                                    <label class="form-label required">ID de la cuenta profesional</label>
                                    <input type="text" inputmode="numeric" name="instagram_account_id" value="{{ old('instagram_account_id', $site->instagram_account_id) }}" class="form-control form-control-solid @error('instagram_account_id') is-invalid @enderror" placeholder="17841400000000000" data-required-for="instagram">
                                    @error('instagram_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Versión de Graph API</label>
                                    <input type="text" name="instagram_api_version" value="{{ old('instagram_api_version', $site->instagram_api_version ?: 'v24.0') }}" class="form-control form-control-solid @error('instagram_api_version') is-invalid @enderror" placeholder="v24.0" data-required-for="instagram">
                                    @error('instagram_api_version')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label {{ filled($site->instagram_access_token) ? '' : 'required' }}">Access Token de Instagram</label>
                                    <textarea name="instagram_access_token" rows="3" class="form-control form-control-solid @error('instagram_access_token') is-invalid @enderror" autocomplete="off" data-required-for="instagram" data-required-on-create="{{ filled($site->instagram_access_token) ? '0' : '1' }}" placeholder="{{ filled($site->instagram_access_token) ? 'Déjalo vacío para conservar el token actual' : 'Pega aquí el token con permiso para publicar' }}"></textarea>
                                    <div class="form-text">Debe incluir <code>instagram_basic</code> e <code>instagram_content_publish</code>.</div>
                                    @error('instagram_access_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-12 publication-platform-fields" data-profile-fields="x">
                            <div class="alert alert-info mb-6">
                                <div class="fw-bold mb-1">{{ filled($site->x_access_token) ? 'Cuenta conectada'.($site->x_username ? ' como @'.$site->x_username : '') : 'Conexión directa desde esta página' }}</div>
                                <div>Guarda las credenciales de tu app y serás enviado a X para autorizar la cuenta. Los tokens se obtienen y renuevan automáticamente.</div>
                            </div>
                            <div class="row g-6">
                                <div class="col-md-6">
                                    <label class="form-label required">Client ID</label>
                                    <input type="text" name="x_client_id" value="{{ old('x_client_id', $site->x_client_id) }}" class="form-control form-control-solid @error('x_client_id') is-invalid @enderror" autocomplete="off" data-required-for="x">
                                    <div class="form-text">Está en <strong>Keys &amp; Tokens → OAuth 2.0 Client ID</strong>.</div>
                                    @error('x_client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label {{ filled($site->x_client_secret) ? '' : 'required' }}">Client Secret</label>
                                    <input type="password" name="x_client_secret" class="form-control form-control-solid @error('x_client_secret') is-invalid @enderror" autocomplete="new-password" data-required-for="x" data-required-on-create="{{ filled($site->x_client_secret) ? '0' : '1' }}" placeholder="{{ filled($site->x_client_secret) ? 'Déjalo vacío para conservar el actual' : '' }}">
                                    <div class="form-text">Se almacena cifrado y nunca vuelve a mostrarse.</div>
                                    @error('x_client_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <div class="separator separator-dashed my-2"></div>
                                    <div class="fw-bold text-gray-800 mb-1">Tokens OAuth 2.0 de usuario <span class="text-muted fw-semibold">(alternativa al botón de conexión)</span></div>
                                    <div class="text-muted fs-8">Si X no completa la autorización, genera estos tokens en Keys &amp; Tokens y pégalos aquí. Se validarán al guardar.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Token de acceso</label>
                                    <textarea name="x_access_token" rows="3" class="form-control form-control-solid @error('x_access_token') is-invalid @enderror" autocomplete="off" placeholder="{{ filled($site->x_access_token) ? 'Déjalo vacío para conservar el actual' : 'Access Token OAuth 2.0' }}"></textarea>
                                    <div class="form-text">Caduca aproximadamente en dos horas.</div>
                                    @error('x_access_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Token de actualización</label>
                                    <textarea name="x_refresh_token" rows="3" class="form-control form-control-solid @error('x_refresh_token') is-invalid @enderror" autocomplete="off" placeholder="{{ filled($site->x_refresh_token) ? 'Déjalo vacío para conservar el actual' : 'Refresh Token OAuth 2.0' }}"></textarea>
                                    <div class="form-text">Permite renovar la conexión sin volver a autorizar.</div>
                                    @error('x_refresh_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                @if ($site->exists && $site->isX() && filled($site->x_client_id) && filled($site->x_client_secret))
                                    <div class="col-12">
                                        <a href="{{ route('admin.x-oauth.redirect', $site) }}" class="btn btn-light-primary">
                                            <span class="fw-bold me-2">𝕏</span>{{ filled($site->x_access_token) ? 'Reconectar cuenta de X' : 'Conectar cuenta de X' }}
                                        </a>
                                    </div>
                                @endif
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

            <div class="card card-flush mb-7 bg-light-primary publication-platform-help" data-profile-help="instagram">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-5">
                        <i class="ki-outline ki-instagram fs-2hx text-primary me-3"></i>
                        <h3 class="fw-bold mb-0">Conectar Instagram</h3>
                    </div>
                    <ol class="text-gray-700 fw-semibold ps-5 mb-4">
                        <li class="mb-3">Usa una cuenta Business o Creator vinculada a una app de Meta.</li>
                        <li class="mb-3">Autoriza <code>instagram_basic</code> e <code>instagram_content_publish</code>.</li>
                        <li class="mb-3">Obtén el ID profesional y un token de usuario válido.</li>
                        <li>Guarda el perfil; se comprobará que el token pertenece a la cuenta.</li>
                    </ol>
                    <div class="fs-8 text-muted">La imagen se comparte con Meta mediante una URL temporal firmada y caduca automáticamente.</div>
                </div>
            </div>

            <div class="card card-flush mb-7 bg-light-primary publication-platform-help" data-profile-help="x">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-5">
                        <span class="fs-2hx fw-bold text-primary me-3">𝕏</span>
                        <h3 class="fw-bold mb-0">Conectar X</h3>
                    </div>
                    <ol class="text-gray-700 fw-semibold ps-5 mb-4">
                        <li class="mb-3">En X habilita OAuth 2.0 con permisos de lectura y escritura.</li>
                        <li class="mb-3">Registra esta callback exactamente: <code>{{ route('x-oauth.callback') }}</code>.</li>
                        <li class="mb-3">Copia aquí el Client ID y Client Secret.</li>
                        <li class="mb-3">Guarda y autoriza la cuenta directamente en X.</li>
                        <li>Si X falla al autorizar, genera y pega un Access Token y Refresh Token OAuth 2.0.</li>
                    </ol>
                    <div class="fs-8 text-muted">La autorización solicita <code>tweet.write</code>, <code>media.write</code> y <code>offline.access</code> para publicar imágenes y renovar la sesión.</div>
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
            <i class="ki-outline ki-check fs-2"></i><span data-profile-submit-label>{{ $selectedType === App\Models\WordPressSite::TYPE_X ? 'Guardar y conectar con X' : 'Guardar y probar perfil' }}</span>
        </button>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selector = document.getElementById('publication-profile-type');
    const submitLabel = document.querySelector('[data-profile-submit-label]');
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

        if (submitLabel) {
            submitLabel.textContent = selected === 'x' ? 'Guardar y conectar con X' : 'Guardar y probar perfil';
        }
    };

    selector.addEventListener('change', refreshProfileFields);
    refreshProfileFields();
});
</script>
@endpush
