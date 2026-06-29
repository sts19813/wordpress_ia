@php
    $isEdit = $sourceSite->exists;
    $jsonValue = function (string $field) use ($sourceSite) {
        $value = old($field);

        if ($value !== null) {
            return $value;
        }

        $stored = $sourceSite->{$field};

        return $stored ? json_encode($stored, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    };
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.source-sites.update', $sourceSite) : route('admin.source-sites.store') }}" class="form">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="card card-flush mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold mb-0">Información general</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-7">
                <div class="col-lg-6">
                    <label class="form-label required">Nombre</label>
                    <input type="text" name="name" value="{{ old('name', $sourceSite->name) }}" class="form-control form-control-solid @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-6">
                    <label class="form-label required">URL</label>
                    <input type="url" name="url" value="{{ old('url', $sourceSite->url) }}" class="form-control form-control-solid @error('url') is-invalid @enderror" placeholder="https://example.com/feed">
                    @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label required">Tipo</label>
                    <select name="type" class="form-select form-select-solid @error('type') is-invalid @enderror">
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $sourceSite->type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label required">Estado</label>
                    <select name="status" class="form-select form-select-solid @error('status') is-invalid @enderror">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $sourceSite->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label required">Frecuencia de consulta</label>
                    <div class="input-group input-group-solid">
                        <input type="number" name="frequency_minutes" value="{{ old('frequency_minutes', $sourceSite->frequency_minutes) }}" class="form-control @error('frequency_minutes') is-invalid @enderror" min="5">
                        <span class="input-group-text">min</span>
                    </div>
                    @error('frequency_minutes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label">Categoría</label>
                    <input type="text" name="category" value="{{ old('category', $sourceSite->category) }}" class="form-control form-control-solid @error('category') is-invalid @enderror">
                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label required">Idioma</label>
                    <input type="text" name="language" value="{{ old('language', $sourceSite->language ?: 'es') }}" class="form-control form-control-solid @error('language') is-invalid @enderror" maxlength="10">
                    @error('language')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label">País</label>
                    <input type="text" name="country" value="{{ old('country', $sourceSite->country) }}" class="form-control form-control-solid @error('country') is-invalid @enderror">
                    @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label required">Prioridad</label>
                    <input type="number" name="priority" value="{{ old('priority', $sourceSite->priority) }}" class="form-control form-control-solid @error('priority') is-invalid @enderror" min="1" max="10">
                    <div class="form-text">1 es menor prioridad, 10 es mayor prioridad.</div>
                    @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label">Límite diario</label>
                    <input type="number" name="daily_limit" value="{{ old('daily_limit', $sourceSite->daily_limit) }}" class="form-control form-control-solid @error('daily_limit') is-invalid @enderror" min="0">
                    @error('daily_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label">Última sincronización</label>
                    <input type="datetime-local" name="last_synced_at" value="{{ old('last_synced_at', $sourceSite->last_synced_at?->format('Y-m-d\TH:i')) }}" class="form-control form-control-solid @error('last_synced_at') is-invalid @enderror">
                    @error('last_synced_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4 d-flex align-items-center">
                    <label class="form-check form-switch form-check-custom form-check-solid mt-8">
                        <input type="hidden" name="active" value="0">
                        <input class="form-check-input" type="checkbox" name="active" value="1" @checked((bool) old('active', $sourceSite->active))>
                        <span class="form-check-label fw-semibold text-gray-700">Activo</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush mb-8">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold mb-0">Autenticación</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-7">
                <div class="col-lg-4">
                    <label class="form-label required">Método de autenticación</label>
                    <select name="auth_method" class="form-select form-select-solid @error('auth_method') is-invalid @enderror">
                        @foreach ($authMethodOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('auth_method', $sourceSite->auth_method) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('auth_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label">API Key</label>
                    <input type="text" name="api_key" value="{{ old('api_key') }}" class="form-control form-control-solid @error('api_key') is-invalid @enderror" autocomplete="off">
                    @if ($isEdit)
                        <div class="form-text">Déjalo vacío para conservar el valor actual.</div>
                    @endif
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
                    @if ($isEdit)
                        <div class="form-text">Déjalo vacío para conservar el valor actual.</div>
                    @endif
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header">
            <div class="card-title">
                <h3 class="fw-bold mb-0">Headers y cookies</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-7">
                <div class="col-lg-6">
                    <label class="form-label">Headers personalizados JSON</label>
                    <textarea name="custom_headers" rows="8" class="form-control form-control-solid font-monospace @error('custom_headers') is-invalid @enderror" placeholder='{"Accept": "application/json"}'>{{ $jsonValue('custom_headers') }}</textarea>
                    @error('custom_headers')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-6">
                    <label class="form-label">Cookies JSON</label>
                    <textarea name="cookies" rows="8" class="form-control form-control-solid font-monospace @error('cookies') is-invalid @enderror" placeholder='{"session": "valor"}'>{{ $jsonValue('cookies') }}</textarea>
                    @error('cookies')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-3 mt-8">
        <a href="{{ route('admin.source-sites.index') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="ki-outline ki-check fs-2"></i>
            {{ $isEdit ? 'Guardar cambios' : 'Crear sitio' }}
        </button>
    </div>
</form>
