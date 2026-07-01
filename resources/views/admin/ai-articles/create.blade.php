@extends('layouts.admin')

@section('title', 'Nueva nota con IA | '.config('app.name'))

@section('toolbar')
    <div>
        <a href="{{ route('admin.ai-articles.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3">
            <i class="ki-outline ki-left fs-4 me-1"></i>Artículos IA
        </a>
        <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Crear nota con IA</h1>
        <div class="text-muted fw-semibold fs-7 pt-1">Selecciona hasta 10 fuentes y un perfil editorial. El resultado se guardará como borrador.</div>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.ai-articles.store') }}" id="ai-generation-form">
        @csrf
        <div class="row g-7">
            <div class="col-xl-8">
                <div class="card card-flush">
                    <div class="card-header align-items-center">
                        <div class="card-title"><h3 class="fw-bold mb-0">Noticias de referencia</h3></div>
                        <div class="card-toolbar"><span class="badge badge-light-primary" id="source-count">0 seleccionadas</span></div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="position-relative mb-5">
                            <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4 top-50 translate-middle-y text-gray-500"></i>
                            <input type="search" class="form-control form-control-solid ps-12" id="source-search" placeholder="Filtrar por título o fuente...">
                        </div>
                        <div class="border rounded overflow-auto" style="max-height: 580px;">
                            @forelse ($sourcePosts as $sourcePost)
                                <label class="d-flex align-items-start gap-4 p-5 border-bottom source-option" data-search="{{ Str::lower($sourcePost->title.' '.$sourcePost->sourceSite?->name) }}">
                                    <input class="form-check-input mt-1 source-checkbox" type="checkbox" name="source_post_ids[]" value="{{ $sourcePost->id }}" @checked(in_array($sourcePost->id, old('source_post_ids', $selectedIds)))>
                                    <span class="flex-grow-1">
                                        <span class="d-block fw-bold text-gray-900">{{ $sourcePost->title }}</span>
                                        <span class="d-block text-muted fs-7 mt-1">{{ $sourcePost->sourceSite?->name ?: 'Sin fuente' }} · {{ $sourcePost->published_at?->format('d/m/Y H:i') ?: 'Sin fecha' }}</span>
                                        <span class="d-block text-gray-600 fs-7 mt-2">{{ Str::limit($sourcePost->summary ?: $sourcePost->content, 180) }}</span>
                                    </span>
                                </label>
                            @empty
                                <div class="p-10 text-center text-muted">Primero obtén noticias desde un sitio fuente.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card card-flush mb-7">
                    <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Configuración</h3></div></div>
                    <div class="card-body">
                        <label class="form-label required">Perfil de generación</label>
                        <select name="ai_prompt_profile_id" class="form-select form-select-solid mb-3" required>
                            @foreach ($profiles as $profile)
                                <option value="{{ $profile->id }}" data-generate-image="{{ $profile->generate_image ? '1' : '0' }}" @selected((string) old('ai_prompt_profile_id', $profiles->firstWhere('is_default', true)?->id) === (string) $profile->id)>
                                    {{ $profile->name }}{{ $profile->is_default ? ' · predeterminado' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <a href="{{ route('admin.settings.index') }}" class="fs-7 fw-semibold">Revisar prompt, temperatura, extensión e imagen</a>

                        <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-5 mt-7">
                            <i class="ki-outline ki-shield-tick fs-2x text-warning me-3"></i>
                            <div class="fs-7 text-gray-700">La generación puede tardar. No se enviará nada a WordPress y podrás editar todo antes de publicar.</div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100" id="generate-draft-button" @disabled($sourcePosts->isEmpty())>
                    <i class="ki-outline ki-sparkles fs-2"></i>Generar y guardar borrador
                </button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const boxes = Array.from(document.querySelectorAll('.source-checkbox'));
    const count = document.getElementById('source-count');
    const search = document.getElementById('source-search');
    const form = document.getElementById('ai-generation-form');
    const submitButton = document.getElementById('generate-draft-button');
    const refresh = () => {
        const selected = boxes.filter(box => box.checked);
        count.textContent = selected.length + (selected.length === 1 ? ' seleccionada' : ' seleccionadas');
        boxes.forEach(box => box.disabled = !box.checked && selected.length >= 10);
    };
    boxes.forEach(box => box.addEventListener('change', refresh));
    search?.addEventListener('input', function () {
        const term = this.value.toLowerCase().trim();
        document.querySelectorAll('.source-option').forEach(row => row.classList.toggle('d-none', !row.dataset.search.includes(term)));
    });
    form?.addEventListener('submit', function (event) {
        if (!boxes.some(box => box.checked)) {
            event.preventDefault();
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Selecciona una noticia',
                    text: 'Necesitas al menos una fuente para generar el borrador.',
                    confirmButtonText: 'Entendido',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
            }
            return;
        }

        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generando borrador...';

        if (!window.Swal) {
            return;
        }

        const selectedProfile = form.querySelector('[name="ai_prompt_profile_id"] option:checked');
        const generatesImage = selectedProfile?.dataset.generateImage === '1';
        const messages = [
            'Analizando las noticias seleccionadas…',
            'Redactando el contenido original…',
            'Preparando metadatos y SEO…',
            ...(generatesImage ? ['Generando la imagen principal…'] : []),
            'Guardando el borrador privado…',
        ];
        let messageIndex = 0;
        let loaderInterval;

        Swal.fire({
            title: 'Generando borrador con IA',
            html: '<div id="ai-generation-message" class="text-gray-700 mb-3">' + messages[0] + '</div><div class="text-muted fs-7">Puede tardar uno o varios minutos. No cierres esta ventana.</div>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
                loaderInterval = window.setInterval(function () {
                    messageIndex = Math.min(messageIndex + 1, messages.length - 1);
                    const message = document.getElementById('ai-generation-message');
                    if (message) message.textContent = messages[messageIndex];
                }, 12000);
            },
            willClose: function () {
                if (loaderInterval) window.clearInterval(loaderInterval);
            },
        });
    });
    refresh();
});
</script>
@endpush
