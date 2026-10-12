@extends('layouts.admin')

@php
    $oldCompanyIds = array_map('intval', old('company_ids', []));
    $oldPublicationProfileIds = array_map('intval', old('publication_profile_ids', []));
    $defaultProfileId = old('ai_prompt_profile_id', $profiles->firstWhere('is_default', true)?->id);
@endphp

@section('title', 'Generar post | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Generar post</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Pega el texto, agrega imágenes opcionales y publica en una o varias empresas.</div>
        </div>
        <a href="{{ route('admin.quick-posts.create') }}" class="btn btn-light-primary">
            <i class="ki-outline ki-link fs-2"></i>Generar desde URL
        </a>
    </div>
@endsection

@section('content')
    <div class="manual-post-page">
        <form method="POST" action="{{ route('admin.manual-posts.store') }}" enctype="multipart/form-data" id="manual-post-form">
            @csrf
            <div class="row g-6">
                <div class="col-xl-7">
                    <div class="card card-flush h-100">
                        <div class="card-body p-lg-8">
                            <div class="d-flex align-items-start gap-4 mb-6">
                                <div class="symbol symbol-54px flex-shrink-0">
                                    <div class="symbol-label bg-light-primary">
                                        <i class="ki-outline ki-sparkles fs-2x text-primary"></i>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <h2 class="fw-bold text-gray-900 fs-4 mb-1">Texto base</h2>
                                    <div class="text-muted fs-7">La IA redactará una nota original a partir de este contenido.</div>
                                </div>
                            </div>

                            <label for="manual-post-content" class="form-label required fw-bold">Contenido</label>
                            <textarea
                                id="manual-post-content"
                                name="content"
                                class="form-control form-control-solid manual-post-textarea @error('content') is-invalid @enderror"
                                rows="18"
                                maxlength="20000"
                                placeholder="Pega aquí la información de la nota..."
                                required
                                autofocus
                            >{{ old('content') }}</textarea>
                            @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="d-flex justify-content-between gap-3 mt-2">
                                <div class="form-text">Mínimo 80 caracteres.</div>
                                <div class="text-muted fs-8"><span id="manual-post-count">0</span>/20000</div>
                            </div>

                            <div class="separator my-7"></div>

                            <div class="row align-items-end g-4">
                                <div class="col-md-8">
                                    <label for="manual-post-profile" class="form-label required fw-bold">Perfil de generación</label>
                                    <select
                                        id="manual-post-profile"
                                        name="ai_prompt_profile_id"
                                        class="form-select form-select-solid @error('ai_prompt_profile_id') is-invalid @enderror"
                                        required
                                    >
                                        @foreach ($profiles as $profile)
                                            <option value="{{ $profile->id }}" @selected((string) $defaultProfileId === (string) $profile->id)>
                                                {{ $profile->name }}
                                                · {{ App\Models\AiPromptProfile::lengthOptions()[$profile->content_length] ?? $profile->content_length }}
                                                {{ $profile->is_default ? ' · predeterminado' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('ai_prompt_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <a href="{{ route('admin.settings.index') }}" class="btn btn-light w-100 w-md-auto">Perfiles</a>
                                </div>
                            </div>

                            <div class="separator my-7"></div>

                            <label for="manual-post-images" class="form-label fw-bold">Imágenes</label>
                            <label class="manual-post-upload d-flex flex-column align-items-center justify-content-center rounded border border-dashed p-6 cursor-pointer" for="manual-post-images">
                                <i class="ki-outline ki-picture fs-2x text-primary mb-2"></i>
                                <span class="fw-bold text-gray-900">Subir desde galería</span>
                                <span class="text-muted fs-8">Hasta 8 imágenes JPG, PNG o WebP.</span>
                            </label>
                            <input
                                id="manual-post-images"
                                class="d-none @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror"
                                type="file"
                                name="images[]"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                            >
                            @error('images')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                            @error('images.*')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                            <div id="manual-post-preview" class="manual-post-preview-grid mt-4"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="card card-flush h-100">
                        <div class="card-body p-lg-8">
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-5">
                                <div>
                                    <h2 class="fw-bold text-gray-900 fs-4 mb-1">Empresas y destinos</h2>
                                    <div class="text-muted fs-7">Sin selección, el post quedará como borrador.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-light-primary" id="manual-post-select-all">
                                    <i class="ki-outline ki-check-square fs-3"></i>Todas
                                </button>
                            </div>

                            @if ($companies->isNotEmpty())
                                <div class="manual-post-company-list">
                                    @foreach ($companies as $company)
                                        @php
                                            $companyProfileIds = $company->publicationProfiles->pluck('id')->map(fn ($id) => (int) $id)->all();
                                            $companyChecked = in_array($company->id, $oldCompanyIds, true)
                                                || count(array_intersect($companyProfileIds, $oldPublicationProfileIds)) > 0;
                                        @endphp
                                        <div class="manual-post-company rounded border p-4 mb-4" data-company-card="{{ $company->id }}">
                                            <label class="d-flex align-items-start gap-3 cursor-pointer mb-3">
                                                <input
                                                    class="form-check-input mt-1"
                                                    type="checkbox"
                                                    name="company_ids[]"
                                                    value="{{ $company->id }}"
                                                    data-company-toggle="{{ $company->id }}"
                                                    @checked($companyChecked)
                                                >
                                                <span class="min-w-0">
                                                    <span class="fw-bold text-gray-900 d-block">{{ $company->name }}</span>
                                                    <span class="text-muted fs-8">{{ $company->publicationProfiles->count() }} destino(s) activo(s)</span>
                                                </span>
                                            </label>

                                            @if ($company->publicationProfiles->isNotEmpty())
                                                <div class="manual-post-destination-list">
                                                    @foreach ($company->publicationProfiles as $publicationProfile)
                                                        @php
                                                            $destinationClass = match ($publicationProfile->type) {
                                                                App\Models\WordPressSite::TYPE_FACEBOOK_PAGE => 'is-facebook',
                                                                App\Models\WordPressSite::TYPE_INSTAGRAM => 'is-instagram',
                                                                App\Models\WordPressSite::TYPE_X => 'is-x',
                                                                default => 'is-wordpress',
                                                            };
                                                            $destinationChecked = in_array($publicationProfile->id, $oldPublicationProfileIds, true)
                                                                || (in_array($company->id, $oldCompanyIds, true) && $oldPublicationProfileIds === []);
                                                        @endphp
                                                        <label class="manual-post-destination d-flex align-items-center gap-3 rounded p-3 cursor-pointer">
                                                            <input
                                                                class="form-check-input"
                                                                type="checkbox"
                                                                name="publication_profile_ids[]"
                                                                value="{{ $publicationProfile->id }}"
                                                                data-company-destination="{{ $company->id }}"
                                                                @checked($destinationChecked)
                                                            >
                                                            <span class="manual-post-destination-icon {{ $destinationClass }}">
                                                                @if ($publicationProfile->isFacebookPage())
                                                                    <i class="ki-outline ki-facebook"></i>
                                                                @elseif ($publicationProfile->isInstagram())
                                                                    <i class="ki-outline ki-instagram"></i>
                                                                @elseif ($publicationProfile->isX())
                                                                    <strong>X</strong>
                                                                @else
                                                                    <strong>W</strong>
                                                                @endif
                                                            </span>
                                                            <span class="min-w-0 flex-grow-1">
                                                                <span class="fw-semibold text-gray-900 d-block">{{ $publicationProfile->name }}</span>
                                                                <span class="text-muted fs-8 d-block text-truncate">{{ $publicationProfile->typeLabel() }}</span>
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="notice bg-light-warning rounded border-warning border border-dashed p-4 fs-7 text-muted">
                                                    No tiene destinos activos.
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                @error('company_ids')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                                @error('company_ids.*')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                                @error('publication_profile_ids')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                                @error('publication_profile_ids.*')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                            @else
                                <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-5">
                                    <i class="ki-outline ki-information-5 fs-2x text-warning me-3"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-gray-900">No hay empresas activas</div>
                                        <div class="text-muted fs-7 mt-1">El post se guardará como borrador.</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="card-footer d-flex flex-column flex-sm-row justify-content-end gap-3">
                            <a href="{{ route('admin.news.index') }}" class="btn btn-light">Cancelar</a>
                            <button type="submit" class="btn btn-primary" id="manual-post-submit">
                                <i class="ki-outline ki-sparkles fs-2"></i>Generar post
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('styles')
<style>
.manual-post-page {
    min-width: 0;
}

.manual-post-textarea {
    min-height: 430px;
    resize: vertical;
    line-height: 1.65;
}

.manual-post-upload {
    min-height: 138px;
    background: var(--bs-gray-100);
    transition: border-color .18s ease, background-color .18s ease;
}

.manual-post-upload:hover {
    border-color: var(--bs-primary) !important;
    background: var(--bs-primary-light);
}

.manual-post-preview-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .75rem;
}

.manual-post-preview-item {
    position: relative;
    overflow: hidden;
    aspect-ratio: 1 / 1;
    border-radius: .5rem;
    background: var(--bs-gray-100);
}

.manual-post-preview-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.manual-post-preview-item span {
    position: absolute;
    right: .35rem;
    bottom: .35rem;
    padding: .15rem .45rem;
    border-radius: .35rem;
    background: rgba(17, 24, 39, .76);
    color: #fff;
    font-size: .7rem;
    font-weight: 700;
}

.manual-post-company,
.manual-post-destination {
    transition: border-color .18s ease, background-color .18s ease, box-shadow .18s ease;
}

.manual-post-company:has([data-company-toggle]:checked) {
    border-color: var(--bs-primary) !important;
    background: var(--bs-primary-light);
}

.manual-post-destination:has(input:checked) {
    background: #fff;
    box-shadow: inset 0 0 0 1px var(--bs-primary);
}

.manual-post-destination-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    border-radius: 8px;
    color: #fff;
    font-size: 1rem;
}

.manual-post-destination-icon.is-facebook { background: #1877f2; }
.manual-post-destination-icon.is-instagram { background: #c13584; }
.manual-post-destination-icon.is-x { background: #111; }
.manual-post-destination-icon.is-wordpress { background: #28799e; }

@media (max-width: 767.98px) {
    .manual-post-page .row {
        --bs-gutter-y: 1rem;
    }

    .manual-post-page .card-body {
        padding: 1.15rem;
    }

    .manual-post-textarea {
        min-height: 360px;
        font-size: 1rem;
    }

    .manual-post-preview-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .manual-post-company {
        padding: 1rem !important;
    }

    .manual-post-destination {
        align-items: flex-start !important;
    }

    .manual-post-page .card-footer {
        padding: 1rem 1.15rem;
    }

    .manual-post-page .card-footer .btn,
    .manual-post-page .btn-light-primary {
        width: 100%;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('manual-post-form');
    const button = document.getElementById('manual-post-submit');
    const textarea = document.getElementById('manual-post-content');
    const counter = document.getElementById('manual-post-count');
    const imageInput = document.getElementById('manual-post-images');
    const preview = document.getElementById('manual-post-preview');
    const selectAll = document.getElementById('manual-post-select-all');
    const companyToggles = Array.from(document.querySelectorAll('[data-company-toggle]'));
    const destinationChecks = Array.from(document.querySelectorAll('[data-company-destination]'));

    const refreshCounter = function () {
        if (counter && textarea) counter.textContent = String(textarea.value.length);
    };

    const syncCompany = function (companyId, checked) {
        destinationChecks
            .filter(function (input) { return Number(input.dataset.companyDestination) === Number(companyId); })
            .forEach(function (input) { input.checked = checked; });
    };

    const syncCompanyFromDestinations = function (companyId) {
        const destinations = destinationChecks.filter(function (input) {
            return Number(input.dataset.companyDestination) === Number(companyId);
        });
        const toggle = companyToggles.find(function (input) {
            return Number(input.dataset.companyToggle) === Number(companyId);
        });

        if (toggle) toggle.checked = destinations.length > 0 && destinations.some(function (input) { return input.checked; });
    };

    textarea?.addEventListener('input', refreshCounter);
    refreshCounter();

    companyToggles.forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            syncCompany(toggle.dataset.companyToggle, toggle.checked);
        });
    });

    destinationChecks.forEach(function (input) {
        input.addEventListener('change', function () {
            syncCompanyFromDestinations(input.dataset.companyDestination);
        });
    });

    selectAll?.addEventListener('click', function () {
        const shouldCheck = destinationChecks.some(function (input) { return !input.checked; });
        companyToggles.forEach(function (input) { input.checked = shouldCheck; });
        destinationChecks.forEach(function (input) { input.checked = shouldCheck; });
    });

    imageInput?.addEventListener('change', function () {
        const files = Array.from(imageInput.files || []).slice(0, 8);
        preview.innerHTML = '';

        if ((imageInput.files || []).length > 8) {
            const dataTransfer = new DataTransfer();
            files.forEach(function (file) { dataTransfer.items.add(file); });
            imageInput.files = dataTransfer.files;
        }

        files.forEach(function (file, index) {
            const item = document.createElement('div');
            const img = document.createElement('img');
            const label = document.createElement('span');
            item.className = 'manual-post-preview-item';
            img.alt = '';
            img.src = URL.createObjectURL(file);
            img.onload = function () { URL.revokeObjectURL(img.src); };
            label.textContent = String(index + 1);
            item.appendChild(img);
            item.appendChild(label);
            preview.appendChild(item);
        });
    });

    form?.addEventListener('submit', function () {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Añadiendo a la cola...';
    });
});
</script>
@endpush
