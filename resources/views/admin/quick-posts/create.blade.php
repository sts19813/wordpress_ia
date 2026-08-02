@extends('layouts.admin')

@php
    $selectedCompanyId = (int) old('company_id', $companies->count() === 1 ? $companies->first()->id : 0);
    $oldPublicationProfileIds = array_map('intval', old(
        'publication_profile_ids',
        $selectedCompanyId ? $publicationProfiles->where('company_id', $selectedCompanyId)->pluck('id')->all() : [],
    ));
@endphp

@section('title', 'Nuevo Post rápido | '.config('app.name'))

@section('toolbar')
    <div>
        <a href="{{ route('admin.quick-posts.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3">
            <i class="ki-outline ki-left fs-4 me-1"></i>Post rápido
        </a>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Crear desde una publicación social</h1>
        <div class="text-muted fw-semibold fs-7 pt-1">Pega un enlace público, recrea el texto con IA y decide qué imágenes conservar para publicar.</div>
    </div>
@endsection

@section('content')
    <div class="row justify-content-center g-7">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('admin.quick-posts.store') }}" id="quick-post-form">
                @csrf
                <div class="card card-flush">
                    <div class="card-body p-lg-10">
                        <div class="d-flex align-items-center gap-4 mb-8">
                            <div class="symbol symbol-60px">
                                <div class="symbol-label bg-light-primary">
                                    <i class="ki-outline ki-flash-circle fs-2x text-primary"></i>
                                </div>
                            </div>
                            <div>
                                <h2 class="fw-bold text-gray-900 mb-1">Solo necesitas la URL</h2>
                                <div class="text-muted">Facebook, X o Instagram · la publicación debe ser visible sin iniciar sesión.</div>
                            </div>
                        </div>

                        <label for="quick-post-url" class="form-label required fw-bold">URL de la publicación original</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="ki-outline ki-link fs-2"></i></span>
                            <input
                                id="quick-post-url"
                                type="url"
                                name="url"
                                value="{{ old('url') }}"
                                class="form-control form-control-solid @error('url') is-invalid @enderror"
                                placeholder="https://www.facebook.com/share/p/..."
                                maxlength="2048"
                                required
                                autofocus
                            >
                        </div>
                        @error('url')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror

                        <div class="separator my-8"></div>

                        <div class="row align-items-end g-4">
                            <div class="col-md-8">
                                <label for="quick-post-profile" class="form-label required fw-bold">Perfil de generación</label>
                                <select
                                    id="quick-post-profile"
                                    name="ai_prompt_profile_id"
                                    class="form-select form-select-solid @error('ai_prompt_profile_id') is-invalid @enderror"
                                    required
                                >
                                    @foreach ($profiles as $profile)
                                        <option
                                            value="{{ $profile->id }}"
                                            @selected((string) old('ai_prompt_profile_id', $profiles->firstWhere('is_default', true)?->id) === (string) $profile->id)
                                        >
                                            {{ $profile->name }}
                                            · {{ App\Models\AiPromptProfile::lengthOptions()[$profile->content_length] ?? $profile->content_length }}
                                            {{ $profile->is_default ? ' · predeterminado' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('ai_prompt_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Se aplicarán su prompt, tono, extensión, modelo de texto y estilo visual.</div>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <a href="{{ route('admin.settings.index') }}" class="btn btn-light">Administrar perfiles</a>
                            </div>
                        </div>

                        <div class="separator my-8"></div>

                        <label class="form-label required fw-bold mb-4">Imágenes del post generado</label>
                        <div class="row g-5">
                            <div class="col-md-6">
                                <label class="border rounded p-5 h-100 d-flex gap-4 cursor-pointer">
                                    <input
                                        class="form-check-input mt-1"
                                        type="radio"
                                        name="image_mode"
                                        value="generate"
                                        @checked(old('image_mode', 'generate') === 'generate')
                                    >
                                    <span>
                                        <span class="fw-bold text-gray-900 d-block mb-1">Generar imágenes nuevas con IA</span>
                                        <span class="text-muted fs-8">Crea una imagen principal nueva usando el estilo del perfil editorial.</span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="border rounded p-5 h-100 d-flex gap-4 cursor-pointer">
                                    <input
                                        class="form-check-input mt-1"
                                        type="radio"
                                        name="image_mode"
                                        value="original"
                                        @checked(old('image_mode') === 'original')
                                    >
                                    <span>
                                        <span class="fw-bold text-gray-900 d-block mb-1">Conservar las imágenes originales</span>
                                        <span class="text-muted fs-8">Usa las imágenes del post sin modificarlas y las prepara para publicar después.</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        @error('image_mode')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror

                        <div class="separator my-8"></div>

                        <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-4 mb-5">
                            <div>
                                <label class="form-label fw-bold mb-1">Publicar automáticamente al terminar</label>
                                <div class="text-muted fs-7">Selecciona uno o varios destinos. Sin selección, se guardará únicamente el borrador.</div>
                            </div>
                            <a href="{{ route('admin.wordpress-sites.index') }}" class="btn btn-sm btn-light-primary text-nowrap">
                                Administrar perfiles de publicación
                            </a>
                        </div>

                        @if ($companies->isNotEmpty())
                            <div class="mb-6">
                                <label for="quick-post-company" class="form-label fw-bold">Empresa</label>
                                <select id="quick-post-company" name="company_id" class="form-select form-select-solid @error('company_id') is-invalid @enderror">
                                    <option value="">No publicar; guardar como borrador</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}" @selected($selectedCompanyId === $company->id)>{{ $company->name }} · {{ $company->publicationProfiles->count() }} destino(s)</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Al seleccionar una empresa se marcarán todos sus destinos; puedes desmarcar los que no apliquen.</div>
                                @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endif

                        @if ($publicationProfiles->isNotEmpty())
                            <div class="row g-4" id="quick-post-destinations">
                                @foreach ($publicationProfiles as $publicationProfile)
                                    @php
                                        $destinationClass = match ($publicationProfile->type) {
                                            App\Models\WordPressSite::TYPE_FACEBOOK_PAGE => 'is-facebook',
                                            App\Models\WordPressSite::TYPE_INSTAGRAM => 'is-instagram',
                                            App\Models\WordPressSite::TYPE_X => 'is-x',
                                            default => 'is-wordpress',
                                        };
                                    @endphp
                                    <div class="col-md-6 @if ((int) $publicationProfile->company_id !== $selectedCompanyId) d-none @endif" data-company-destination="{{ $publicationProfile->company_id }}">
                                        <label class="quick-post-destination border rounded p-5 h-100 d-flex align-items-start gap-4 cursor-pointer">
                                            <input
                                                class="form-check-input mt-1"
                                                type="checkbox"
                                                name="publication_profile_ids[]"
                                                value="{{ $publicationProfile->id }}"
                                                @checked(in_array($publicationProfile->id, $oldPublicationProfileIds))
                                            >
                                            <span class="quick-post-destination-icon {{ $destinationClass }}">
                                                @if ($publicationProfile->isFacebookPage())
                                                    <i class="ki-outline ki-facebook"></i>
                                                @elseif ($publicationProfile->isInstagram())
                                                    <i class="ki-outline ki-instagram"></i>
                                                @elseif ($publicationProfile->isX())
                                                    <strong>𝕏</strong>
                                                @else
                                                    <strong>W</strong>
                                                @endif
                                            </span>
                                            <span class="min-w-0 flex-grow-1">
                                                <span class="fw-bold text-gray-900 d-block">{{ $publicationProfile->name }}</span>
                                                <span class="text-muted fs-8 d-block mt-1">{{ $publicationProfile->typeLabel() }}</span>
                                                <span class="text-muted fs-8 d-block text-truncate mt-1">{{ $publicationProfile->destinationLabel() }}</span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('publication_profile_ids')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                            @error('publication_profile_ids.*')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                        @else
                            <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-5">
                                <i class="ki-outline ki-information-5 fs-2x text-warning me-3"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-gray-900">No hay perfiles listos para publicar</div>
                                    <div class="text-muted fs-7 mt-1">El post se guardará como borrador. Conecta WordPress, Facebook, Instagram o X para publicarlo automáticamente.</div>
                                </div>
                                <a href="{{ route('admin.wordpress-sites.create') }}" class="btn btn-sm btn-warning align-self-center">Agregar perfil</a>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-3">
                        <a href="{{ route('admin.quick-posts.index') }}" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary" id="quick-post-submit">
                            <i class="ki-outline ki-sparkles fs-2"></i>Obtener y generar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('styles')
<style>
.quick-post-destination {
    transition: border-color .18s ease, background-color .18s ease, box-shadow .18s ease;
}

.quick-post-destination:has(input:checked) {
    border-color: var(--bs-primary) !important;
    background: var(--bs-primary-light);
    box-shadow: 0 0 0 3px rgba(47, 128, 237, .08);
}

.quick-post-destination-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    border-radius: 12px;
    color: #fff;
    font-size: 1.25rem;
}

.quick-post-destination-icon.is-facebook { background: #1877f2; }
.quick-post-destination-icon.is-instagram { background: #c13584; }
.quick-post-destination-icon.is-x { background: #111; }
.quick-post-destination-icon.is-wordpress { background: #28799e; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('quick-post-form');
    const button = document.getElementById('quick-post-submit');
    const company = document.getElementById('quick-post-company');
    const destinationCards = Array.from(document.querySelectorAll('[data-company-destination]'));

    const refreshDestinations = function (selectAll) {
        const companyId = Number(company?.value || 0);

        destinationCards.forEach(function (card) {
            const visible = Number(card.dataset.companyDestination) === companyId;
            const checkbox = card.querySelector('input[type="checkbox"]');
            card.classList.toggle('d-none', !visible);

            if (checkbox) {
                if (!visible) checkbox.checked = false;
                else if (selectAll) checkbox.checked = true;
            }
        });
    };

    company?.addEventListener('change', function () { refreshDestinations(true); });
    refreshDestinations(false);

    form?.addEventListener('submit', function () {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Añadiendo a la cola...';
    });
});
</script>
@endpush
