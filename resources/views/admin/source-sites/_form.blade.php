@php
    $isEdit = $sourceSite->exists;
    $jsonValue = function (string $field) use ($sourceSite) {
        $value = old($field);
        if ($value !== null) {
            return is_array($value)
                ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : $value;
        }
        $stored = $sourceSite->{$field};

        return $stored ? json_encode($stored, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    };
    $topicValue = function (string $field) use ($sourceSite) {
        $value = old($field, $sourceSite->{$field} ?: []);

        return is_array($value) ? implode("\n", $value) : $value;
    };
    $filterErrors = $errors->hasAny(['filter_topics', 'filter_topics.*', 'excluded_topics', 'excluded_topics.*', 'filter_instructions']);
    $advancedErrors = $errors->hasAny(['daily_limit', 'max_posts_per_scan', 'active', 'auth_method', 'api_key', 'username', 'password', 'custom_headers', 'cookies']);
    $automationErrors = $errors->hasAny(['auto_generate', 'auto_publish', 'ai_prompt_profile_id', 'company_id', 'publication_profile_ids', 'publication_profile_ids.*', 'max_generations_per_scan']);
    $selectedPublicationProfileIds = array_map('intval', old('publication_profile_ids', $sourceSite->selectedPublicationProfileIds()));
    $selectedCompanyId = (int) old('company_id', $sourceSite->company_id ?: ($companies->count() === 1 ? $companies->first()->id : 0));
    $activeTab = $automationErrors ? 'automation' : ($advancedErrors ? 'advanced' : ($filterErrors ? 'filters' : 'basic'));
@endphp

<form method="POST"
    action="{{ $isEdit ? route('admin.source-sites.update', $sourceSite) : route('admin.source-sites.store') }}"
    class="form"
    id="source-site-form"
    data-test-url="{{ route('admin.source-sites.test') }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="source_site_id" value="{{ $sourceSite->id }}">
    @endif

    <div class="card card-flush mb-8">
        <div class="card-header border-0 pt-6 pb-0">
            <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x fs-6 fw-bold border-0">
                <li class="nav-item">
                    <button class="nav-link {{ $activeTab === 'basic' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#source-basic" type="button">
                        <i class="ki-outline ki-information-5 fs-3 me-2"></i>Datos básicos
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link {{ $activeTab === 'filters' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#source-filters" type="button">
                        <i class="ki-outline ki-filter-search fs-3 me-2"></i>Filtros inteligentes
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link {{ $activeTab === 'automation' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#source-automation" type="button">
                        <i class="ki-outline ki-arrows-circle fs-3 me-2"></i>Automatización
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link {{ $activeTab === 'advanced' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#source-advanced" type="button">
                        <i class="ki-outline ki-setting-4 fs-3 me-2"></i>Avanzado
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body pt-8">
            <div class="tab-content">
                <div class="tab-pane fade {{ $activeTab === 'basic' ? 'show active' : '' }}" id="source-basic">
                    <div class="row g-7">
                        <div class="col-lg-6">
                            <label class="form-label required">Nombre</label>
                            <input type="text" name="name" value="{{ old('name', $sourceSite->name) }}" class="form-control form-control-solid @error('name') is-invalid @enderror" placeholder="Ej. El Financiero" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label required">URL del medio, feed o API</label>
                            <input type="url" name="url" value="{{ old('url', $sourceSite->url) }}" class="form-control form-control-solid @error('url') is-invalid @enderror" placeholder="https://medio.com" required>
                            <div class="form-text">Puedes pegar la portada; la prueba intentará encontrar el mejor formato disponible.</div>
                            @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-8">
                            <label class="form-label required">Tipo de conexión</label>
                            <select name="type" id="source-type" class="form-select form-select-solid @error('type') is-invalid @enderror">
                                @foreach ($typeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type', $sourceSite->type) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text" id="source-type-help">La detección automática analizará el sitio y recomendará el conector más confiable.</div>
                            <div class="notice d-none bg-light-warning border-warning border border-dashed rounded p-4 mt-4" id="ai-connection-notice">
                                <div class="d-flex align-items-start">
                                    <i class="ki-outline ki-sparkles fs-2 text-warning me-3"></i>
                                    <div class="text-gray-700">
                                        La IA descargará y analizará el HTML para localizar publicaciones. Después extraerá el contenido completo de cada nota aceptada. Requiere una API Key de OpenAI configurada.
                                    </div>
                                </div>
                            </div>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label required">Frecuencia de consulta</label>
                            <div class="input-group input-group-solid">
                                <input type="number" name="frequency_hours" value="{{ old('frequency_hours', max(1, (int) ceil(($sourceSite->frequency_minutes ?: 60) / 60))) }}" class="form-control @error('frequency_hours') is-invalid @enderror" min="1" max="168" step="1" required>
                                <span class="input-group-text">horas</span>
                            </div>
                            @error('frequency_hours')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade {{ $activeTab === 'filters' ? 'show active' : '' }}" id="source-filters">
                    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-6 mb-8">
                        <i class="ki-outline ki-sparkles fs-2tx text-primary me-4"></i>
                        <div>
                            <div class="fw-bold text-gray-900 mb-1">La IA decide qué notas sí aplican</div>
                            <div class="text-gray-700">Primero se revisan título, resumen, categorías y tags. Solo las notas aceptadas se descargan completas. Cada decisión queda guardada en la bitácora.</div>
                        </div>
                    </div>

                    <div class="row g-7">
                        <div class="col-lg-6">
                            <label class="form-label">Temas que sí deben obtenerse</label>
                            <textarea name="filter_topics" rows="7" class="form-control form-control-solid @error('filter_topics') is-invalid @enderror" placeholder="Política&#10;Economía&#10;Finanzas públicas">{{ $topicValue('filter_topics') }}</textarea>
                            <div class="form-text">Un tema por línea o separado por comas. Déjalo vacío para aceptar todos.</div>
                            @error('filter_topics')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('filter_topics.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Temas que siempre deben descartarse</label>
                            <textarea name="excluded_topics" rows="7" class="form-control form-control-solid @error('excluded_topics') is-invalid @enderror" placeholder="Tecnología&#10;Deportes&#10;Espectáculos">{{ $topicValue('excluded_topics') }}</textarea>
                            <div class="form-text">Las exclusiones tienen prioridad sobre los temas aceptados.</div>
                            @error('excluded_topics')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('excluded_topics.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Instrucciones adicionales para la IA</label>
                            <textarea name="filter_instructions" rows="4" class="form-control form-control-solid @error('filter_instructions') is-invalid @enderror" placeholder="Ej. Aceptar notas sobre decisiones del Congreso aunque el título no diga política.">{{ old('filter_instructions', $sourceSite->filter_instructions) }}</textarea>
                            <div class="form-text">Úsalo para reglas editoriales, entidades, regiones o casos especiales.</div>
                            @error('filter_instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade {{ $activeTab === 'automation' ? 'show active' : '' }}" id="source-automation">
                    <div class="notice d-flex bg-light-success rounded border-success border border-dashed p-6 mb-8">
                        <i class="ki-outline ki-arrows-circle fs-2tx text-success me-4"></i>
                        <div>
                            <div class="fw-bold text-gray-900 mb-1">Flujo completo mediante colas</div>
                            <div class="text-gray-700">Cada nota aceptada puede generar un artículo con IA y publicarse en todos los WordPress, Facebook, Instagram y X seleccionados para la empresa. El progreso completo aparecerá en el Programador.</div>
                        </div>
                    </div>

                    <div class="row g-7">
                        <div class="col-lg-6">
                            <label class="form-label required">Empresa que publicará las notas</label>
                            <select name="company_id" id="source-company-id" class="form-select form-select-solid @error('company_id') is-invalid @enderror" required>
                                <option value="">Selecciona una empresa</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected($selectedCompanyId === $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Al cambiarla se cargarán y marcarán todos sus destinos disponibles.</div>
                            @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label required">Perfil editorial para generar artículos</label>
                            <select name="ai_prompt_profile_id" class="form-select form-select-solid @error('ai_prompt_profile_id') is-invalid @enderror">
                                <option value="">Selecciona un perfil</option>
                                @foreach ($promptProfiles as $profile)
                                    <option value="{{ $profile->id }}" @selected((int) old('ai_prompt_profile_id', $sourceSite->ai_prompt_profile_id) === $profile->id)>
                                        {{ $profile->name }}{{ $profile->is_default ? ' · Predeterminado' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ai_prompt_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <label class="form-label mb-0">Destinos de publicación automática</label>
                                @if ($wordpressSites->isNotEmpty())
                                    <button type="button" class="btn btn-sm btn-light-primary py-1 px-3" id="select-all-publication-profiles">
                                        Seleccionar todos
                                    </button>
                                @endif
                            </div>
                            <select
                                name="publication_profile_ids[]"
                                id="publication-profile-ids"
                                class="form-select form-select-solid @error('publication_profile_ids') is-invalid @enderror @error('publication_profile_ids.*') is-invalid @enderror"
                                multiple
                                data-placeholder="Solo guardar como borrador"
                            >
                                @foreach ($wordpressSites as $wordpressSite)
                                    @if ((int) $wordpressSite->company_id === $selectedCompanyId)
                                        <option value="{{ $wordpressSite->id }}" @selected(in_array($wordpressSite->id, $selectedPublicationProfileIds, true))>{{ $wordpressSite->typeLabel() }} · {{ $wordpressSite->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <div class="form-text">Busca y selecciona uno, varios o todos tus perfiles activos. Al elegir destinos se activa la publicación; sin selección, la nota quedará como borrador.</div>
                            @error('publication_profile_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('publication_profile_ids.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label required">Máximo de artículos generados por consulta</label>
                            <input type="number" name="max_generations_per_scan" value="{{ old('max_generations_per_scan', $sourceSite->max_generations_per_scan ?: 5) }}" class="form-control form-control-solid @error('max_generations_per_scan') is-invalid @enderror" min="1" max="1000" required>
                            <div class="form-text">Solo esta cantidad de notas nuevas aceptadas generará y publicará automáticamente. Las demás permanecerán disponibles en Noticias.</div>
                            @error('max_generations_per_scan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-6">
                            <label class="form-check form-switch form-check-custom form-check-solid">
                                <input type="hidden" name="auto_generate" value="0">
                                <input class="form-check-input" type="checkbox" name="auto_generate" value="1" @checked((bool) old('auto_generate', $sourceSite->auto_generate ?? true))>
                                <span class="form-check-label">
                                    <span class="fw-bold text-gray-800 d-block">Generar artículo con IA</span>
                                    <span class="text-muted fs-8">Solo para notas nuevas que hayan aprobado los filtros.</span>
                                </span>
                            </label>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-check form-switch form-check-custom form-check-solid">
                                <input type="hidden" name="auto_publish" value="0">
                                <input class="form-check-input" type="checkbox" name="auto_publish" value="1" @checked((bool) old('auto_publish', $sourceSite->auto_publish ?? false))>
                                <span class="form-check-label">
                                    <span class="fw-bold text-gray-800 d-block">Publicar automáticamente</span>
                                    <span class="text-muted fs-8">Si está desactivado, el artículo queda como borrador para revisión.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade {{ $activeTab === 'advanced' ? 'show active' : '' }}" id="source-advanced">
                    <div class="mb-9">
                        <h3 class="fw-bold mb-1">Autenticación</h3>
                        <div class="text-muted fw-semibold">Solo es necesaria para medios privados o APIs protegidas.</div>
                    </div>
                    <div class="row g-7 mb-10">
                        <div class="col-lg-4">
                            <label class="form-label">Método</label>
                            <select name="auth_method" class="form-select form-select-solid @error('auth_method') is-invalid @enderror">
                                @foreach ($authMethodOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('auth_method', $sourceSite->auth_method) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('auth_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">API Key o token</label>
                            <input type="password" name="api_key" value="{{ old('api_key') }}" class="form-control form-control-solid @error('api_key') is-invalid @enderror" autocomplete="new-password">
                            @if ($isEdit)<div class="form-text">Vacío conserva el valor actual.</div>@endif
                            @error('api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Usuario</label>
                            <input type="text" name="username" value="{{ old('username', $sourceSite->username) }}" class="form-control form-control-solid @error('username') is-invalid @enderror" autocomplete="off">
                            @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" value="{{ old('password') }}" class="form-control form-control-solid @error('password') is-invalid @enderror" autocomplete="new-password">
                            @if ($isEdit)<div class="form-text">Vacío conserva el valor actual.</div>@endif
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="separator separator-dashed mb-9"></div>
                    <div class="mb-7">
                        <h3 class="fw-bold mb-1">Headers y cookies</h3>
                        <div class="text-muted fw-semibold">Escribe objetos JSON válidos. Se usarán tanto en la prueba como en los escaneos.</div>
                    </div>
                    <div class="row g-7 mb-10">
                        <div class="col-lg-6">
                            <label class="form-label">Headers personalizados</label>
                            <textarea name="custom_headers" rows="7" class="form-control form-control-solid font-monospace @error('custom_headers') is-invalid @enderror" placeholder='{"Accept": "application/json"}'>{{ $jsonValue('custom_headers') }}</textarea>
                            @error('custom_headers')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Cookies</label>
                            <textarea name="cookies" rows="7" class="form-control form-control-solid font-monospace @error('cookies') is-invalid @enderror" placeholder='{"session": "valor"}'>{{ $jsonValue('cookies') }}</textarea>
                            @error('cookies')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="separator separator-dashed mb-9"></div>
                    <div class="row g-7">
                        <div class="col-lg-4">
                            <label class="form-label required">Límite de posts escaneados al día</label>
                            <input type="number" name="daily_limit" value="{{ old('daily_limit', $sourceSite->daily_limit ?: 20) }}" class="form-control form-control-solid @error('daily_limit') is-invalid @enderror" min="1" max="10000" required>
                            <div class="form-text">Incluye notas aceptadas, descartadas, duplicadas y elementos no interpretables. El conteo se reinicia cada día.</div>
                            @error('daily_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label required">Máximo de posts por consulta</label>
                            <input type="number" name="max_posts_per_scan" value="{{ old('max_posts_per_scan', $sourceSite->max_posts_per_scan ?: 20) }}" class="form-control form-control-solid @error('max_posts_per_scan') is-invalid @enderror" min="1" max="1000" required>
                            <div class="form-text">Limita cuántos elementos se descargan y evalúan cada vez que se ejecuta la fuente. Nunca supera el saldo diario.</div>
                            @error('max_posts_per_scan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-4 d-flex align-items-center">
                            <label class="form-check form-switch form-check-custom form-check-solid mt-lg-8">
                                <input type="hidden" name="active" value="0">
                                <input class="form-check-input" type="checkbox" name="active" value="1" @checked((bool) old('active', $sourceSite->active))>
                                <span class="form-check-label fw-semibold text-gray-700">Sitio activo para escaneos programados</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush mb-8 d-none" id="source-test-result" aria-live="polite">
        <div class="card-header align-items-center">
            <div class="card-title">
                <div>
                    <h3 class="fw-bold mb-1">Resultado de la prueba</h3>
                    <div class="text-muted fw-semibold fs-7" id="test-result-subtitle"></div>
                </div>
            </div>
            <div class="card-toolbar" id="test-recommendation-action"></div>
        </div>
        <div class="card-body">
            <div id="test-capabilities" class="d-flex flex-wrap gap-2 mb-7"></div>
            <div class="row g-7">
                <div class="col-xl-4">
                    <div class="rounded bg-light min-h-250px d-flex align-items-center justify-content-center overflow-hidden">
                        <img id="test-post-image" class="w-100 d-none" style="height: 280px; object-fit: cover;" alt="">
                        <i id="test-post-image-empty" class="ki-outline ki-picture fs-3x text-gray-400"></i>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="d-flex flex-wrap gap-2 mb-4" id="test-post-meta"></div>
                    <h2 class="fw-bold text-gray-900 mb-3" id="test-post-title"></h2>
                    <a id="test-post-url" href="#" target="_blank" rel="noopener" class="text-primary text-break d-block mb-5"></a>
                    <p class="text-gray-700 mb-0" id="test-post-summary"></p>
                </div>
            </div>

            <ul class="nav nav-tabs nav-line-tabs mt-10 mb-6 fs-6 fw-bold">
                <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#test-content-pane">Contenido extraído</button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#test-html-pane">HTML del post</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="test-content-pane">
                    <div class="notice bg-light rounded p-6 mb-5 d-none" id="test-content-warning"></div>
                    <div class="text-gray-800 lh-lg" id="test-post-content" style="white-space: pre-wrap; max-height: 480px; overflow: auto;"></div>
                </div>
                <div class="tab-pane fade" id="test-html-pane">
                    <textarea readonly rows="18" id="test-post-html" class="form-control form-control-solid font-monospace"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-column flex-sm-row justify-content-end gap-3">
        <a href="{{ route('admin.source-sites.index') }}" class="btn btn-light">Cancelar</a>
        <button type="button" class="btn btn-light-primary" id="test-source-button">
            <i class="ki-outline ki-flask fs-2"></i>
            Probar y traer la nota más reciente
        </button>
        <button type="submit" class="btn btn-primary" id="save-source-button" @disabled(! $isEdit)>
            <i class="ki-outline ki-check fs-2"></i>
            {{ $isEdit ? 'Guardar cambios' : 'Guardar sitio fuente' }}
        </button>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('source-site-form');
    if (!form) return;

    const testButton = document.getElementById('test-source-button');
    const saveButton = document.getElementById('save-source-button');
    const resultCard = document.getElementById('source-test-result');
    const typeSelect = document.getElementById('source-type');
    const typeHelp = document.getElementById('source-type-help');
    const aiNotice = document.getElementById('ai-connection-notice');
    const requiresTest = @json(! $isEdit);
    const publicationProfiles = document.getElementById('publication-profile-ids');
    const companySelector = document.getElementById('source-company-id');
    const selectAllPublicationProfiles = document.getElementById('select-all-publication-profiles');
    const autoPublishToggle = form.querySelector('input[type="checkbox"][name="auto_publish"]');
    const allPublicationProfiles = @json($wordpressSites->map(fn ($profile) => ['id' => $profile->id, 'company_id' => $profile->company_id, 'label' => $profile->typeLabel().' · '.$profile->name])->values());
    const initiallySelectedProfileIds = @json($selectedPublicationProfileIds);
    let lastRecommendation = null;

    const syncAutoPublishWithDestinations = () => {
        if (autoPublishToggle && publicationProfiles) {
            autoPublishToggle.checked = publicationProfiles.selectedOptions.length > 0;
        }
    };

    let publicationProfilesSelect = null;
    const initializePublicationProfiles = () => {
        if (!publicationProfiles || !window.jQuery?.fn?.select2) return;
        publicationProfilesSelect = window.jQuery(publicationProfiles).select2({
            placeholder: publicationProfiles.dataset.placeholder,
            width: '100%',
            closeOnSelect: false,
        });
        publicationProfilesSelect.on('select2:select select2:unselect', syncAutoPublishWithDestinations);
    };

    const loadCompanyProfiles = (selectAll = false) => {
        if (!publicationProfiles) return;
        const companyId = Number(companySelector?.value || 0);
        const previousSelection = selectAll
            ? []
            : Array.from(publicationProfiles.selectedOptions).map(option => Number(option.value)).concat(initiallySelectedProfileIds);

        if (publicationProfilesSelect) {
            publicationProfilesSelect.off('select2:select select2:unselect');
            publicationProfilesSelect.select2('destroy');
            publicationProfilesSelect = null;
        }

        publicationProfiles.innerHTML = '';
        allPublicationProfiles
            .filter(profile => Number(profile.company_id) === companyId)
            .forEach(profile => {
                const option = new Option(profile.label, profile.id, false, selectAll || previousSelection.includes(Number(profile.id)));
                publicationProfiles.add(option);
            });

        initializePublicationProfiles();
        syncAutoPublishWithDestinations();
    };

    companySelector?.addEventListener('change', () => loadCompanyProfiles(true));

    if (publicationProfiles && window.jQuery?.fn?.select2) {
        initializePublicationProfiles();

        selectAllPublicationProfiles?.addEventListener('click', () => {
            const allProfileIds = Array.from(publicationProfiles.options).map(option => option.value);
            publicationProfilesSelect.val(allProfileIds).trigger('change');
            syncAutoPublishWithDestinations();
        });
    } else {
        publicationProfiles?.addEventListener('change', syncAutoPublishWithDestinations);
        selectAllPublicationProfiles?.addEventListener('click', () => {
            Array.from(publicationProfiles?.options || []).forEach(option => { option.selected = true; });
            syncAutoPublishWithDestinations();
        });
    }

    const typeDescriptions = {
        auto: 'Probará primero los conectores convencionales y usará IA automáticamente si ninguno localiza publicaciones.',
        wordpress_rest: 'Usa la API REST nativa de WordPress para obtener publicaciones estructuradas.',
        rss: 'Lee publicaciones desde un feed RSS o Atom.',
        json_feed: 'Lee publicaciones desde un documento JSON Feed.',
        sitemap: 'Localiza publicaciones mediante un sitemap XML.',
        html: 'Busca artículos, metadatos y datos estructurados directamente en el HTML.',
        ai_web: 'Descarga las páginas y usa IA para reconocer la estructura, localizar notas y extraer su contenido.',
    };

    const updateTypeHelp = () => {
        typeHelp.textContent = typeDescriptions[typeSelect.value] || '';
        aiNotice.classList.toggle('d-none', typeSelect.value !== 'ai_web' && typeSelect.value !== 'auto');
    };
    updateTypeHelp();
    typeSelect.addEventListener('change', updateTypeHelp);

    const invalidateTest = () => {
        if (requiresTest) saveButton.disabled = true;
    };

    form.querySelectorAll('input[name="url"], select[name="type"], select[name="auth_method"], input[name="api_key"], input[name="username"], input[name="password"], textarea[name="custom_headers"], textarea[name="cookies"]')
        .forEach(field => {
            field.addEventListener('input', invalidateTest);
            field.addEventListener('change', invalidateTest);
        });

    testButton.addEventListener('click', async () => {
        const url = form.querySelector('[name="url"]');
        if (!url.value || !url.checkValidity()) {
            url.reportValidity();
            return;
        }

        const originalHtml = testButton.innerHTML;
        testButton.disabled = true;
        testButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analizando sitio y publicación…';

        try {
            const data = new FormData(form);
            data.delete('_method');
            const response = await fetch(form.dataset.testUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: data,
            });
            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                throw new Error(validation || payload.message || 'No fue posible probar el sitio.');
            }

            renderResult(payload);
            saveButton.disabled = false;
            resultCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'No se pudo obtener una nota', text: error.message, confirmButtonText: 'Entendido' });
            } else {
                alert(error.message);
            }
        } finally {
            testButton.disabled = false;
            testButton.innerHTML = originalHtml;
        }
    });

    function renderResult(payload) {
        const post = payload.post;
        lastRecommendation = payload.recommendation;
        resultCard.classList.remove('d-none');
        document.getElementById('test-result-subtitle').textContent =
            `${payload.items_found} publicaciones detectadas · Probado con ${payload.tested_type_label}`;

        document.getElementById('test-capabilities').innerHTML = payload.recommendation.capabilities
            .map(capability => `<span class="badge badge-light-primary">${escapeHtml(capability.label)} · ${capability.confidence}%</span>`)
            .join('');

        const action = document.getElementById('test-recommendation-action');
        const isRecommended = typeSelect.value === payload.recommendation.type || typeSelect.value === 'auto';
        action.innerHTML = isRecommended
            ? `<span class="badge badge-light-success fs-7">Recomendado: ${escapeHtml(payload.recommendation.label)}</span>`
            : `<button type="button" class="btn btn-sm btn-light-success" id="use-recommended-type">Usar ${escapeHtml(payload.recommendation.label)}</button>`;
        document.getElementById('use-recommended-type')?.addEventListener('click', () => {
            typeSelect.value = lastRecommendation.type;
            action.innerHTML = `<span class="badge badge-light-success fs-7">Tipo recomendado aplicado</span>`;
            invalidateTest();
        });

        const image = document.getElementById('test-post-image');
        const emptyImage = document.getElementById('test-post-image-empty');
        if (post.image_url) {
            image.src = post.image_url;
            image.alt = post.title || 'Imagen de la publicación';
            image.classList.remove('d-none');
            emptyImage.classList.add('d-none');
        } else {
            image.removeAttribute('src');
            image.classList.add('d-none');
            emptyImage.classList.remove('d-none');
        }

        document.getElementById('test-post-title').textContent = post.title || 'Sin título';
        const postUrl = document.getElementById('test-post-url');
        postUrl.textContent = post.url || '';
        postUrl.href = post.url || '#';
        document.getElementById('test-post-summary').textContent = post.summary || '';
        document.getElementById('test-post-content').textContent = post.content || 'No se pudo separar el contenido; revisa el HTML disponible.';
        document.getElementById('test-post-html').value = post.raw_html || post.content_html || 'El medio no devolvió HTML.';

        const meta = [];
        if (post.author) meta.push(post.author);
        if (post.published_at) meta.push(formatDate(post.published_at));
        (post.categories || []).slice(0, 4).forEach(category => meta.push(category));
        document.getElementById('test-post-meta').innerHTML = meta
            .map(value => `<span class="badge badge-light">${escapeHtml(value)}</span>`)
            .join('');

        const warning = document.getElementById('test-content-warning');
        if (!post.has_full_content || post.extraction_error) {
            warning.textContent = post.extraction_error
                ? `La publicación fue localizada, pero el contenido completo no pudo separarse: ${post.extraction_error}. El HTML disponible se conserva en la pestaña contigua.`
                : 'La publicación fue localizada, pero parece contener solo un resumen. Revisa la pestaña HTML del post.';
            warning.classList.remove('d-none');
        } else {
            warning.classList.add('d-none');
        }
    }

    function formatDate(value) {
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char]));
    }
});
</script>
@endpush
