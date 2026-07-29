@extends('layouts.admin')

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

                        <div class="row g-5 mt-5">
                            @foreach ([
                                ['facebook', 'Facebook', 'ki-facebook'],
                                ['x', 'X', 'ki-message-text-2'],
                                ['instagram', 'Instagram', 'ki-instagram'],
                            ] as [$key, $label, $icon])
                                <div class="col-md-4">
                                    <div class="border border-dashed rounded p-4 h-100">
                                        <i class="ki-outline {{ $icon }} fs-2x text-primary mb-3"></i>
                                        <div class="fw-bold text-gray-900">{{ $label }}</div>
                                        <div class="text-muted fs-8">Posts, fotos{{ $key !== 'x' ? ' y reels' : ' e hilos públicos' }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-5 mt-8">
                            <i class="ki-outline ki-shield-tick fs-2x text-info me-3"></i>
                            <div class="fs-7 text-gray-700">
                                Se guardarán el texto, la URL canónica, los metadatos y copias locales de todas las imágenes detectadas. Después continuará la generación en segundo plano.
                            </div>
                        </div>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('quick-post-form');
    const button = document.getElementById('quick-post-submit');

    form?.addEventListener('submit', function () {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Añadiendo a la cola...';
    });
});
</script>
@endpush
