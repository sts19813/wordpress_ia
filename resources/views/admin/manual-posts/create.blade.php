@extends('layouts.admin')

@php
    $oldCompanyIds = array_map('intval', old('company_ids', []));
    $oldPublicationProfileIds = array_map('intval', old('publication_profile_ids', []));
    $defaultProfileId = old('ai_prompt_profile_id', $profiles->firstWhere('is_default', true)?->id);
    $reviewCompanyIds = array_map('intval', data_get($reviewTask?->payload, 'company_ids', []));
    $reviewProfileIds = array_map('intval', data_get($reviewTask?->payload, 'publication_profile_ids', []));
    $reviewArticle = $reviewTask?->article;
@endphp

@section('title', 'Generar post | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Generar post</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Crea una nota desde texto, URL o voz y revísala antes de publicar.</div>
        </div>
        <a href="{{ route('admin.quick-posts.index') }}" class="btn btn-light-primary"><i class="ki-outline ki-time fs-2"></i>Historial</a>
    </div>
@endsection

@section('content')
    <div class="manual-post-page">
        <form method="POST" action="{{ route('admin.manual-posts.store') }}" enctype="multipart/form-data" id="manual-post-form"
            data-manual-action="{{ route('admin.manual-posts.store') }}"
            data-url-action="{{ route('admin.manual-posts.store-url') }}"
            data-transcribe-url="{{ route('admin.manual-posts.transcribe') }}"
            data-initial-mode="{{ $activeMode }}">
            @csrf
            <div class="row g-6">
                <div class="col-xl-7">
                    <div class="card card-flush h-100">
                        <div class="card-body p-lg-8">
                            <div class="d-flex align-items-start gap-4 mb-6">
                                <div class="symbol symbol-54px flex-shrink-0"><div class="symbol-label bg-light-primary"><i class="ki-outline ki-sparkles fs-2x text-primary"></i></div></div>
                                <div class="min-w-0"><h2 class="fw-bold text-gray-900 fs-4 mb-1">Nueva nota</h2><div class="text-muted fs-7">Elige la forma más rápida de entregar la información a la IA.</div></div>
                            </div>

                            <div class="manual-post-mode-switch mb-7" role="tablist" aria-label="Origen de la nota">
                                <button type="button" class="manual-post-mode-button" data-post-mode="text" role="tab"><i class="ki-outline ki-document fs-3"></i><span>Texto</span></button>
                                <button type="button" class="manual-post-mode-button" data-post-mode="url" role="tab"><i class="ki-outline ki-link fs-3"></i><span>Desde URL</span></button>
                                <button type="button" class="manual-post-mode-button" data-post-mode="voice" role="tab"><i class="ki-outline ki-microphone fs-3"></i><span>Voz</span></button>
                            </div>

                            <div data-mode-url-workspace class="d-none">
                                <label for="quick-post-url" class="form-label required fw-bold">URL de la publicación</label>
                                <input id="quick-post-url" type="url" name="url" value="{{ old('url') }}" class="form-control form-control-solid @error('url') is-invalid @enderror" placeholder="https://www.facebook.com/... o https://x.com/..." inputmode="url" autocomplete="url">
                                @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text mt-2">Puedes usar enlaces públicos de Facebook, Instagram o X.</div>
                                <div class="mt-6">
                                    <label class="form-label fw-bold">Imagen principal</label>
                                    <div class="d-flex flex-column gap-3">
                                        <label class="manual-post-choice d-flex align-items-start gap-3 rounded border p-4 cursor-pointer">
                                            <input class="form-check-input mt-1" type="radio" name="image_mode" value="generate" checked>
                                            <span><span class="fw-bold text-gray-900 d-block">Generar con IA</span><span class="text-muted fs-8">Se crea una imagen nueva para la nota.</span></span>
                                        </label>
                                        <label class="manual-post-choice d-flex align-items-start gap-3 rounded border p-4 cursor-pointer">
                                            <input class="form-check-input mt-1" type="radio" name="image_mode" value="original" @checked(old('image_mode') === 'original')>
                                            <span><span class="fw-bold text-gray-900 d-block">Conservar imágenes originales</span><span class="text-muted fs-8">Se intentarán usar las imágenes públicas del enlace.</span></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div data-mode-text-workspace>
                                <div data-voice-panel class="manual-post-voice d-none mb-6">
                                    <div class="manual-post-voice-controls">
                                        <button type="button" class="btn btn-primary" id="voice-record-button"><i class="ki-outline ki-microphone fs-2"></i>Grabar</button>
                                        <button type="button" class="btn btn-light-danger d-none" id="voice-stop-button" disabled><i class="ki-outline ki-stop fs-2"></i>Detener</button>
                                        <label for="manual-post-audio" class="btn btn-light-primary mb-0"><i class="ki-outline ki-file-up fs-2"></i>Cargar audio</label>
                                        <input id="manual-post-audio" class="d-none" type="file" accept="audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/webm,audio/ogg">
                                    </div>
                                    <div class="text-muted fs-8" id="voice-status">Graba o carga un audio de hasta 25 MB. La transcripción aparecerá abajo.</div>
                                </div>

                                <label for="manual-post-content" class="form-label required fw-bold">Contenido</label>
                                <textarea id="manual-post-content" name="content" class="form-control form-control-solid manual-post-textarea @error('content') is-invalid @enderror" rows="18" maxlength="20000" placeholder="Pega aquí la información de la nota..." autofocus>{{ old('content') }}</textarea>
                                @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="d-flex justify-content-between gap-3 mt-2"><div class="form-text">Mínimo 80 caracteres.</div><div class="text-muted fs-8"><span id="manual-post-count">0</span>/20000</div></div>
                            </div>

                            <div class="separator my-7"></div>
                            <div class="row align-items-end g-4">
                                <div class="col-md-8">
                                    <label for="manual-post-profile" class="form-label required fw-bold">Perfil de generación</label>
                                    <select id="manual-post-profile" name="ai_prompt_profile_id" class="form-select form-select-solid @error('ai_prompt_profile_id') is-invalid @enderror" required>
                                        @foreach ($profiles as $profile)
                                            <option value="{{ $profile->id }}" @selected((string) $defaultProfileId === (string) $profile->id)>{{ $profile->name }} · {{ \App\Models\AiPromptProfile::lengthOptions()[$profile->content_length] ?? $profile->content_length }}{{ $profile->is_default ? ' · predeterminado' : '' }}</option>
                                        @endforeach
                                    </select>
                                    @error('ai_prompt_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4 text-md-end"><a href="{{ route('admin.settings.index') }}" class="btn btn-light w-100 w-md-auto">Perfiles</a></div>
                            </div>

                            <div data-mode-media-workspace>
                                <div class="separator my-7"></div>
                                <label for="manual-post-images" class="form-label fw-bold">Imágenes</label>
                                <label class="manual-post-upload d-flex flex-column align-items-center justify-content-center rounded border border-dashed p-6 cursor-pointer" for="manual-post-images">
                                    <i class="ki-outline ki-picture fs-2x text-primary mb-2"></i><span class="fw-bold text-gray-900">Subir desde galería</span><span class="text-muted fs-8">Hasta 8 imágenes JPG, PNG o WebP.</span>
                                </label>
                                <input id="manual-post-images" class="d-none @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
                                @error('images')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                                @error('images.*')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                                <div id="manual-post-preview" class="manual-post-preview-grid mt-4"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="card card-flush h-100">
                        <div class="card-body p-lg-8">
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-5">
                                <div><h2 class="fw-bold text-gray-900 fs-4 mb-1">Empresas y destinos</h2><div class="text-muted fs-7">Elige dónde se publicará después de tu revisión.</div></div>
                                <button type="button" class="btn btn-sm btn-light-primary" id="manual-post-select-all"><i class="ki-outline ki-check-square fs-3"></i>Todas</button>
                            </div>
                            @if ($companies->isNotEmpty())
                                <div class="manual-post-company-list">
                                    @foreach ($companies as $company)
                                        @php
                                            $companyProfileIds = $company->publicationProfiles->pluck('id')->map(fn ($id) => (int) $id)->all();
                                            $companyChecked = in_array($company->id, $oldCompanyIds, true) || count(array_intersect($companyProfileIds, $oldPublicationProfileIds)) > 0;
                                        @endphp
                                        <div class="manual-post-company rounded border p-4 mb-4" data-company-card="{{ $company->id }}">
                                            <label class="d-flex align-items-start gap-3 cursor-pointer mb-3">
                                                <input class="form-check-input mt-1" type="checkbox" name="company_ids[]" value="{{ $company->id }}" data-company-toggle="{{ $company->id }}" @checked($companyChecked)>
                                                <span class="min-w-0"><span class="fw-bold text-gray-900 d-block">{{ $company->name }}</span><span class="text-muted fs-8">{{ $company->publicationProfiles->count() }} destino(s) activo(s)</span></span>
                                            </label>
                                            @if ($company->publicationProfiles->isNotEmpty())
                                                <div class="manual-post-destination-list">
                                                    @foreach ($company->publicationProfiles as $publicationProfile)
                                                        @php
                                                            $destinationClass = match ($publicationProfile->type) {
                                                                \App\Models\WordPressSite::TYPE_FACEBOOK_PAGE => 'is-facebook',
                                                                \App\Models\WordPressSite::TYPE_INSTAGRAM => 'is-instagram',
                                                                \App\Models\WordPressSite::TYPE_X => 'is-x',
                                                                default => 'is-wordpress',
                                                            };
                                                            $destinationChecked = in_array($publicationProfile->id, $oldPublicationProfileIds, true) || (in_array($company->id, $oldCompanyIds, true) && $oldPublicationProfileIds === []);
                                                        @endphp
                                                        <label class="manual-post-destination d-flex align-items-center gap-3 rounded p-3 cursor-pointer">
                                                            <input class="form-check-input" type="checkbox" name="publication_profile_ids[]" value="{{ $publicationProfile->id }}" data-company-destination="{{ $company->id }}" @checked($destinationChecked)>
                                                            <span class="manual-post-destination-icon {{ $destinationClass }}">@if ($publicationProfile->isFacebookPage())<i class="ki-outline ki-facebook"></i>@elseif ($publicationProfile->isInstagram())<i class="ki-outline ki-instagram"></i>@elseif ($publicationProfile->isX())<strong>X</strong>@else<strong>W</strong>@endif</span>
                                                            <span class="min-w-0 flex-grow-1"><span class="fw-semibold text-gray-900 d-block">{{ $publicationProfile->name }}</span><span class="text-muted fs-8 d-block text-truncate">{{ $publicationProfile->typeLabel() }}</span></span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="notice bg-light-warning rounded border-warning border border-dashed p-4 fs-7 text-muted">No tiene destinos activos.</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                @error('company_ids')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                                @error('company_ids.*')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                                @error('publication_profile_ids')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                                @error('publication_profile_ids.*')<div class="text-danger fs-7 mt-3">{{ $message }}</div>@enderror
                            @else
                                <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-5"><i class="ki-outline ki-information-5 fs-2x text-warning me-3"></i><div><div class="fw-bold text-gray-900">No hay empresas activas</div><div class="text-muted fs-7 mt-1">El post se guardará como borrador.</div></div></div>
                            @endif
                        </div>
                        <div class="card-footer d-flex flex-column flex-sm-row justify-content-end gap-3">
                            <a href="{{ route('admin.news.index') }}" class="btn btn-light">Cancelar</a>
                            <label class="manual-post-auto-publish d-flex align-items-center gap-2 cursor-pointer mb-0">
                                <span class="text-end"><span class="fw-bold text-gray-900 d-block fs-8">Publicar sin revisar</span><span class="text-muted d-block fs-9">Autoriza publicación automática</span></span>
                                <span class="form-check form-switch form-check-custom form-check-solid m-0"><input class="form-check-input" type="checkbox" name="publish_without_review" value="1" id="publish-without-review" @checked(old('publish_without_review'))></span>
                            </label>
                            <button type="submit" class="btn btn-primary" id="manual-post-submit"><i class="ki-outline ki-sparkles fs-2"></i>Generar post</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        @if ($reviewTask && $reviewArticle)
            <div class="modal fade" id="generated-post-review" tabindex="-1" aria-labelledby="generated-post-review-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-xl modal-fullscreen-md-down">
                    <form method="POST" action="{{ route('admin.manual-posts.review.update', $reviewTask) }}" enctype="multipart/form-data" class="modal-content" id="generated-post-review-form" data-publish-action="{{ route('admin.manual-posts.publish', $reviewTask) }}">
                        @csrf @method('PUT')
                        <div class="modal-header">
                            <div class="min-w-0"><h2 class="fw-bold fs-4 mb-1" id="generated-post-review-title">Revisar borrador</h2><div class="text-muted fs-8">Ajusta imágenes y destinos antes de publicar.</div></div>
                            <a href="{{ route('admin.manual-posts.create') }}" class="btn btn-icon btn-sm btn-active-light-primary" aria-label="Cerrar"><i class="ki-outline ki-cross fs-2"></i></a>
                        </div>
                        <div class="modal-body p-0">
                            <div class="row g-0">
                                <div class="col-lg-7 border-end">
                                    <div class="p-5 p-lg-8">
                                        @forelse ($reviewArticle->images->where('status', 'generated') as $image)
                                            <img src="{{ route('admin.ai-images.file', $image) }}" alt="" class="review-post-image rounded mb-4">
                                        @empty
                                            <div class="review-image-empty mb-5"><i class="ki-outline ki-picture fs-2x text-primary"></i><div><div class="fw-bold text-gray-900">Sin imágenes</div><div class="text-muted fs-8">Puedes agregarlas antes de publicar.</div></div></div>
                                        @endforelse

                                        @if ($reviewArticle->images->where('status', 'generated')->isEmpty())
                                            <label for="review-post-images" class="manual-post-upload d-flex flex-column align-items-center justify-content-center rounded border border-dashed p-5 cursor-pointer mb-6">
                                                <i class="ki-outline ki-picture fs-2x text-primary mb-2"></i><span class="fw-bold text-gray-900">Agregar imágenes desde galería</span><span class="text-muted fs-8">Hasta 8 imágenes JPG, PNG o WebP.</span>
                                            </label>
                                            <input id="review-post-images" class="d-none" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
                                        @endif

                                        <h1 class="fs-3 fw-bolder text-gray-900 mb-4">{{ $reviewArticle->title }}</h1>
                                        @if ($reviewArticle->excerpt)<p class="fs-5 text-gray-600 fw-semibold mb-6">{{ $reviewArticle->excerpt }}</p>@endif
                                        <div class="ai-article-preview text-gray-800 fs-6 lh-lg">{!! $reviewArticle->content !!}</div>
                                        @if ($reviewArticle->conclusion)<div class="border-top mt-7 pt-5"><h2 class="fs-4 fw-bold">Conclusión</h2><p>{{ $reviewArticle->conclusion }}</p></div>@endif
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <div class="p-5 p-lg-7">
                                        <div class="d-flex align-items-center justify-content-between gap-3 mb-5"><div><h3 class="fw-bold fs-5 mb-1">Publicar en</h3><div class="text-muted fs-8">Selecciona una o varias empresas.</div></div><button type="button" class="btn btn-sm btn-light-primary" data-review-select-all>Todas</button></div>
                                        @foreach ($companies as $company)
                                            @php
                                                $profileIds = $company->publicationProfiles->pluck('id')->map(fn ($id) => (int) $id)->all();
                                                $companyChecked = in_array($company->id, $reviewCompanyIds, true) || count(array_intersect($profileIds, $reviewProfileIds)) > 0;
                                            @endphp
                                            <div class="manual-post-company rounded border p-4 mb-4">
                                                <label class="d-flex align-items-start gap-3 cursor-pointer mb-3"><input class="form-check-input mt-1" type="checkbox" name="company_ids[]" value="{{ $company->id }}" data-review-company="{{ $company->id }}" @checked($companyChecked)><span><span class="fw-bold text-gray-900 d-block">{{ $company->name }}</span><span class="text-muted fs-8">{{ $company->publicationProfiles->count() }} destino(s)</span></span></label>
                                                @foreach ($company->publicationProfiles as $publicationProfile)
                                                    @php($checked = in_array($publicationProfile->id, $reviewProfileIds, true) || ($companyChecked && $reviewProfileIds === []))
                                                    <label class="manual-post-destination d-flex align-items-center gap-3 rounded p-3 cursor-pointer"><input class="form-check-input" type="checkbox" name="publication_profile_ids[]" value="{{ $publicationProfile->id }}" data-review-destination="{{ $company->id }}" @checked($checked)><span class="flex-grow-1"><span class="fw-semibold text-gray-900 d-block">{{ $publicationProfile->name }}</span><span class="text-muted fs-8">{{ $publicationProfile->typeLabel() }}</span></span></label>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer flex-column flex-sm-row gap-3">
                            <button type="submit" class="btn btn-light-primary w-100 w-sm-auto">Guardar borrador</button>
                            @if (! data_get($reviewTask->payload, 'review_approved'))
                                <button type="button" class="btn btn-success w-100 w-sm-auto" id="review-publish-button"><i class="ki-outline ki-send fs-2"></i>Publicar en destinos elegidos</button>
                            @else
                                <span class="btn btn-light w-100 w-sm-auto disabled">Publicación en proceso</span>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('styles')
<style>
.manual-post-page { min-width: 0; }
.manual-post-mode-switch { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; padding: .35rem; border: 1px solid var(--bs-gray-300); border-radius: .5rem; background: var(--bs-gray-100); }
.manual-post-mode-button { display: flex; min-height: 48px; align-items: center; justify-content: center; gap: .45rem; border: 0; border-radius: .35rem; background: transparent; color: var(--bs-gray-700); font-weight: 700; }
.manual-post-mode-button.is-active { background: #fff; color: var(--bs-primary); box-shadow: 0 1px 3px rgba(0, 0, 0, .12); }
.manual-post-textarea { min-height: 430px; resize: vertical; line-height: 1.65; }
.manual-post-upload { min-height: 138px; background: var(--bs-gray-100); transition: border-color .18s ease, background-color .18s ease; }
.manual-post-upload:hover { border-color: var(--bs-primary) !important; background: var(--bs-primary-light); }
.manual-post-preview-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
.manual-post-preview-item { position: relative; overflow: hidden; aspect-ratio: 1 / 1; border-radius: .5rem; background: var(--bs-gray-100); }
.manual-post-preview-item img { width: 100%; height: 100%; object-fit: cover; }
.manual-post-preview-item span { position: absolute; right: .35rem; bottom: .35rem; padding: .15rem .45rem; border-radius: .35rem; background: rgba(17, 24, 39, .76); color: #fff; font-size: .7rem; font-weight: 700; }
.manual-post-choice:has(input:checked), .manual-post-company:has([data-company-toggle]:checked) { border-color: var(--bs-primary) !important; background: var(--bs-primary-light); }
.manual-post-company, .manual-post-destination, .manual-post-choice { transition: border-color .18s ease, background-color .18s ease, box-shadow .18s ease; }
.manual-post-destination:has(input:checked) { background: #fff; box-shadow: inset 0 0 0 1px var(--bs-primary); }
.manual-post-destination-icon { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; flex: 0 0 36px; border-radius: 8px; color: #fff; font-size: 1rem; }
.manual-post-destination-icon.is-facebook { background: #1877f2; }.manual-post-destination-icon.is-instagram { background: #c13584; }.manual-post-destination-icon.is-x { background: #111; }.manual-post-destination-icon.is-wordpress { background: #28799e; }
.manual-post-voice { padding: 1rem; border: 1px dashed var(--bs-primary); border-radius: .5rem; background: var(--bs-primary-light); }.manual-post-voice-controls { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .65rem; }
.manual-post-auto-publish { min-height: 42px; }
.review-post-image { width: 100%; max-height: 420px; object-fit: cover; }.review-image-empty { display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 1px dashed var(--bs-primary); border-radius: .5rem; background: var(--bs-primary-light); }
@media (max-width: 767.98px) { .manual-post-page .row { --bs-gutter-y: 1rem; }.manual-post-page .card-body { padding: 1.15rem; }.manual-post-mode-button { flex-direction: column; gap: .1rem; font-size: .75rem; }.manual-post-textarea { min-height: 320px; font-size: 1rem; }.manual-post-preview-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.manual-post-company { padding: 1rem !important; }.manual-post-destination { align-items: flex-start !important; }.manual-post-page .card-footer { padding: 1rem 1.15rem; }.manual-post-page .card-footer .btn, .manual-post-page .btn-light-primary { width: 100%; }.manual-post-auto-publish { width: 100%; justify-content: space-between; padding: .2rem 0; }.manual-post-voice-controls .btn { flex: 1 1 auto; } }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('manual-post-form');
    if (!form) return;
    const button = document.getElementById('manual-post-submit');
    const textarea = document.getElementById('manual-post-content');
    const counter = document.getElementById('manual-post-count');
    const imageInput = document.getElementById('manual-post-images');
    const preview = document.getElementById('manual-post-preview');
    const urlInput = document.getElementById('quick-post-url');
    const urlWorkspace = document.querySelector('[data-mode-url-workspace]');
    const textWorkspace = document.querySelector('[data-mode-text-workspace]');
    const mediaWorkspace = document.querySelector('[data-mode-media-workspace]');
    const voicePanel = document.querySelector('[data-voice-panel]');
    const modeButtons = Array.from(document.querySelectorAll('[data-post-mode]'));
    const radioInputs = Array.from(document.querySelectorAll('input[name="image_mode"]'));
    const selectAll = document.getElementById('manual-post-select-all');
    const companyToggles = Array.from(document.querySelectorAll('[data-company-toggle]'));
    const destinationChecks = Array.from(document.querySelectorAll('[data-company-destination]'));
    const recordButton = document.getElementById('voice-record-button');
    const stopButton = document.getElementById('voice-stop-button');
    const audioInput = document.getElementById('manual-post-audio');
    const voiceStatus = document.getElementById('voice-status');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let mode = form.dataset.initialMode || 'text';
    let mediaRecorder = null;
    let mediaStream = null;
    let audioChunks = [];

    const setHidden = (element, hidden) => element?.classList.toggle('d-none', hidden);
    const refreshCounter = () => { if (counter && textarea) counter.textContent = String(textarea.value.length); };
    const setButton = (busy) => {
        button.disabled = busy;
        button.innerHTML = busy
            ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generando...'
            : '<i class="ki-outline ki-sparkles fs-2"></i>Generar post';
    };
    const setMode = (nextMode) => {
        mode = nextMode;
        form.action = mode === 'url' ? form.dataset.urlAction : form.dataset.manualAction;
        modeButtons.forEach((item) => { item.classList.toggle('is-active', item.dataset.postMode === mode); item.setAttribute('aria-selected', item.dataset.postMode === mode ? 'true' : 'false'); });
        setHidden(urlWorkspace, mode !== 'url');
        setHidden(textWorkspace, mode === 'url');
        setHidden(mediaWorkspace, mode === 'url');
        setHidden(voicePanel, mode !== 'voice');
        urlInput.disabled = mode !== 'url'; urlInput.required = mode === 'url';
        textarea.disabled = mode === 'url'; textarea.required = mode !== 'url';
        imageInput.disabled = mode === 'url';
        radioInputs.forEach((input) => { input.disabled = mode !== 'url'; });
    };
    const syncCompany = (companyId, checked) => destinationChecks.filter((input) => Number(input.dataset.companyDestination) === Number(companyId)).forEach((input) => { input.checked = checked; });
    const syncCompanyFromDestinations = (companyId) => {
        const destinations = destinationChecks.filter((input) => Number(input.dataset.companyDestination) === Number(companyId));
        const toggle = companyToggles.find((input) => Number(input.dataset.companyToggle) === Number(companyId));
        if (toggle) toggle.checked = destinations.length > 0 && destinations.some((input) => input.checked);
    };
    const swalError = (title, message) => Swal.fire({ icon: 'error', title, text: message || 'No fue posible continuar. Intenta nuevamente.' });
    const updateGeneratingAlert = (progress, step) => {
        const container = Swal.getHtmlContainer();
        if (container) container.innerHTML = '<div class="text-muted mb-4">' + (step || 'Preparando la nota...') + '</div><div class="progress h-8px"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:' + Math.max(5, Number(progress) || 5) + '%"></div></div>';
    };
    const pollTask = async (statusUrl) => {
        try {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const task = await response.json();
            if (!response.ok) throw new Error(task.message || 'No se pudo consultar la generación.');
            updateGeneratingAlert(task.progress, task.step);
            if (task.status === 'completed' && (task.review_url || task.preview_url || task.article_url)) {
                const needsReview = Boolean(task.review_url || task.preview_url);
                await Swal.fire({ icon: 'success', title: needsReview ? 'Borrador listo' : 'Post publicado', text: needsReview ? 'Ábrelo para revisarlo antes de publicar.' : 'La publicación se completó en los destinos elegidos.', confirmButtonText: needsReview ? 'Ver borrador' : 'Ver post', allowOutsideClick: false });
                window.location.assign(task.review_url || task.preview_url || task.article_url);
                return;
            }
            if (task.status === 'failed') throw new Error(task.last_error || 'La generación no pudo completarse.');
            window.setTimeout(() => pollTask(statusUrl), 2800);
        } catch (error) {
            Swal.close(); setButton(false); swalError('No se pudo generar', error.message);
        }
    };
    const transcribeAudio = async (file) => {
        if (!file) return;
        if (file.size > 25 * 1024 * 1024) { swalError('Audio demasiado grande', 'El audio puede pesar máximo 25 MB.'); return; }
        voiceStatus.textContent = 'Transcribiendo audio...';
        Swal.fire({ title: 'Transcribiendo audio', text: 'Estamos preparando tu nota.', allowEscapeKey: false, allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const data = new FormData(); data.append('audio', file, file.name || 'nota.webm');
            const response = await fetch(form.dataset.transcribeUrl, { method: 'POST', body: data, headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, credentials: 'same-origin' });
            const body = await response.json();
            if (!response.ok) throw new Error(body.message || Object.values(body.errors || {}).flat()[0] || 'No se pudo transcribir el audio.');
            textarea.value = [textarea.value.trim(), body.text.trim()].filter(Boolean).join(textarea.value.trim() ? '\n\n' : '');
            refreshCounter(); voiceStatus.textContent = 'Transcripción lista. Puedes editarla antes de generar.';
            await Swal.fire({ icon: 'success', title: 'Transcripción lista', text: 'Ya puedes revisar y generar la nota.' });
        } catch (error) {
            voiceStatus.textContent = 'No se pudo transcribir el audio.';
            Swal.close(); swalError('No se pudo transcribir', error.message);
        }
    };

    textarea?.addEventListener('input', refreshCounter);
    modeButtons.forEach((item) => item.addEventListener('click', () => setMode(item.dataset.postMode)));
    companyToggles.forEach((toggle) => toggle.addEventListener('change', () => syncCompany(toggle.dataset.companyToggle, toggle.checked)));
    destinationChecks.forEach((input) => input.addEventListener('change', () => syncCompanyFromDestinations(input.dataset.companyDestination)));
    selectAll?.addEventListener('click', () => { const shouldCheck = destinationChecks.some((input) => !input.checked); companyToggles.forEach((input) => { input.checked = shouldCheck; }); destinationChecks.forEach((input) => { input.checked = shouldCheck; }); });
    imageInput?.addEventListener('change', () => {
        const files = Array.from(imageInput.files || []).slice(0, 8); preview.innerHTML = '';
        if ((imageInput.files || []).length > 8) { const transfer = new DataTransfer(); files.forEach((file) => transfer.items.add(file)); imageInput.files = transfer.files; }
        files.forEach((file, index) => { const item = document.createElement('div'); const img = document.createElement('img'); const label = document.createElement('span'); item.className = 'manual-post-preview-item'; img.alt = ''; img.src = URL.createObjectURL(file); img.onload = () => URL.revokeObjectURL(img.src); label.textContent = String(index + 1); item.append(img, label); preview.appendChild(item); });
    });
    audioInput?.addEventListener('change', () => transcribeAudio(audioInput.files?.[0]));
    recordButton?.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) { swalError('Grabación no disponible', 'Este navegador no permite grabar audio. Puedes cargar un archivo de audio.'); return; }
        try {
            mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const mimeType = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg'].find((type) => MediaRecorder.isTypeSupported(type));
            mediaRecorder = mimeType ? new MediaRecorder(mediaStream, { mimeType }) : new MediaRecorder(mediaStream);
            audioChunks = [];
            mediaRecorder.ondataavailable = (event) => { if (event.data.size > 0) audioChunks.push(event.data); };
            mediaRecorder.onstop = () => {
                const type = mediaRecorder.mimeType || 'audio/webm';
                const audio = new File(audioChunks, 'nota.' + (type.includes('ogg') ? 'ogg' : 'webm'), { type });
                mediaStream?.getTracks().forEach((track) => track.stop());
                recordButton.classList.remove('d-none'); stopButton.classList.add('d-none'); stopButton.disabled = true;
                transcribeAudio(audio);
            };
            mediaRecorder.start(); voiceStatus.textContent = 'Grabando... toca Detener cuando termines.';
            recordButton.classList.add('d-none'); stopButton.classList.remove('d-none'); stopButton.disabled = false;
        } catch (error) {
            voiceStatus.textContent = 'No se pudo iniciar la grabación.';
            swalError('No se pudo usar el micrófono', 'Revisa el permiso del micrófono o carga un archivo de audio.');
        }
    });
    stopButton?.addEventListener('click', () => { if (mediaRecorder?.state === 'recording') mediaRecorder.stop(); });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) return;
        setButton(true);
        Swal.fire({ title: 'Generando post', html: '<div class="text-muted">Enviando la información...</div><div class="progress h-8px mt-4"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:5%"></div></div>', allowEscapeKey: false, allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, credentials: 'same-origin' });
            const body = await response.json();
            if (!response.ok) throw new Error(body.message || Object.values(body.errors || {}).flat()[0] || 'Revisa los datos de la nota.');
            updateGeneratingAlert(10, body.message); pollTask(body.status_url);
        } catch (error) {
            Swal.close(); setButton(false); swalError('No se pudo iniciar', error.message);
        }
    });
    const reviewModalElement = document.getElementById('generated-post-review');
    if (reviewModalElement && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(reviewModalElement, { backdrop: 'static' }).show();
        const reviewForm = document.getElementById('generated-post-review-form');
        const reviewCompanies = Array.from(document.querySelectorAll('[data-review-company]'));
        const reviewDestinations = Array.from(document.querySelectorAll('[data-review-destination]'));
        reviewCompanies.forEach((input) => input.addEventListener('change', () => reviewDestinations.filter((destination) => destination.dataset.reviewDestination === input.dataset.reviewCompany).forEach((destination) => { destination.checked = input.checked; })));
        reviewDestinations.forEach((input) => input.addEventListener('change', () => { const company = reviewCompanies.find((item) => item.dataset.reviewCompany === input.dataset.reviewDestination); if (company) company.checked = reviewDestinations.some((destination) => destination.dataset.reviewDestination === input.dataset.reviewDestination && destination.checked); }));
        document.querySelector('[data-review-select-all]')?.addEventListener('click', () => { const checked = reviewDestinations.some((input) => !input.checked); reviewCompanies.forEach((input) => { input.checked = checked; }); reviewDestinations.forEach((input) => { input.checked = checked; }); });
        document.getElementById('review-publish-button')?.addEventListener('click', () => {
            reviewForm.action = reviewForm.dataset.publishAction;
            reviewForm.querySelector('[name="_method"]')?.remove();
            reviewForm.submit();
        });
    }
    refreshCounter(); setMode(mode);
});
</script>
@endpush
