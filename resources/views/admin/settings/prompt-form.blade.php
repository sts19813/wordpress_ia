@extends('layouts.admin')

@php
    $editing = $profile->exists;
    $imageCostCalculator = app(App\Services\OpenAI\OpenAICostCalculator::class);
    $imageCostEstimates = [];
    foreach (array_keys(App\Models\AiPromptProfile::imageModelOptions()) as $model) {
        foreach (array_keys(App\Models\AiPromptProfile::imageQualityOptions()) as $quality) {
            foreach (array_keys(App\Models\AiPromptProfile::imageSizeOptions()) as $size) {
                $imageCostEstimates[$model][$quality][$size] = $imageCostCalculator->estimatedImageOutput($model, $size, $quality);
            }
        }
    }
@endphp

@section('title', ($editing ? 'Editar' : 'Nuevo').' perfil IA | '.config('app.name'))

@section('toolbar')
    <div><a href="{{ route('admin.settings.index') }}" class="text-muted text-hover-primary fw-semibold d-inline-flex align-items-center mb-3"><i class="ki-outline ki-left fs-4 me-1"></i>Configuración IA</a><h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">{{ $editing ? 'Editar perfil' : 'Nuevo perfil de generación' }}</h1></div>
@endsection

@section('content')
    <form method="POST" action="{{ $editing ? route('admin.settings.prompts.update', $profile) : route('admin.settings.prompts.store') }}">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-7">
            <div class="col-xl-8">
                <div class="card card-flush mb-7">
                    <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Instrucciones editoriales</h3></div></div>
                    <div class="card-body">
                        <div class="mb-6"><label class="form-label required">Nombre del perfil global</label><input type="hidden" name="name" value="{{ $profile->name }}"><input class="form-control form-control-solid" value="{{ $profile->name }}" disabled></div>
                        <div><label class="form-label required">System prompt</label><textarea name="system_prompt" rows="18" class="form-control form-control-solid font-monospace" required>{{ old('system_prompt', $profile->system_prompt) }}</textarea><div class="form-text">Define identidad, reglas, rigor y límites. La estructura JSON se aplica por separado desde la API.</div></div>
                    </div>
                </div>
                <div class="card card-flush">
                    <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Voz y contenido</h3></div></div>
                    <div class="card-body"><div class="row g-6">
                        <div class="col-md-6"><label class="form-label required">Manera de redacción</label><input name="writing_style" class="form-control form-control-solid" value="{{ old('writing_style', $profile->writing_style) }}" required></div>
                        <div class="col-md-6"><label class="form-label required">Tono</label><input name="tone" class="form-control form-control-solid" value="{{ old('tone', $profile->tone) }}" required></div>
                        <div class="col-md-6"><label class="form-label required">Tamaño del contenido</label><select name="content_length" class="form-select form-select-solid">@foreach (App\Models\AiPromptProfile::lengthOptions() as $value => $label)<option value="{{ $value }}" @selected(old('content_length', $profile->content_length) === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label required">Idioma</label><input name="language" class="form-control form-control-solid" value="{{ old('language', $profile->language) }}" required></div>
                        <div class="col-md-3"><label class="form-label required">Máx. tokens</label><input type="number" name="max_output_tokens" min="512" max="32000" class="form-control form-control-solid" value="{{ old('max_output_tokens', $profile->max_output_tokens) }}" required></div>
                        <div class="col-12"><label class="form-label required">Audiencia</label><input name="audience" class="form-control form-control-solid" value="{{ old('audience', $profile->audience) }}" required></div>
                    </div></div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card card-flush mb-7">
                    <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Modelo de texto</h3></div></div>
                    <div class="card-body">
                        <div class="mb-6">
                            <label class="form-label required">Modelo</label>
                            <select name="model" class="form-select form-select-solid" required>
                                @foreach (App\Models\AiPromptProfile::textModelOptions() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('model', App\Models\AiPromptProfile::normalizeTextModel($profile->model)) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">GPT-5.4 mini ofrece la mejor calidad compacta. GPT-4.1 mini conserva control de temperatura sin razonamiento.</div>
                        </div>
                        <div><div class="d-flex justify-content-between"><label class="form-label required">Temperatura</label><strong id="temperature-value">{{ old('temperature', $profile->temperature) }}</strong></div><input type="range" name="temperature" min="0" max="2" step="0.05" class="form-range" value="{{ old('temperature', $profile->temperature) }}" oninput="document.getElementById('temperature-value').textContent=this.value"><div class="form-text">0 = más estable; 2 = más variación. Algunos modelos de razonamiento ignoran este control.</div></div>
                    </div>
                </div>

                <div class="card card-flush mb-7">
                    <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Imagen principal</h3></div></div>
                    <div class="card-body">
                        <label class="form-check form-switch form-check-custom form-check-solid mb-2"><input type="checkbox" name="use_source_image" value="1" class="form-check-input" @checked(old('use_source_image', $profile->use_source_image ?? true))><span class="form-check-label fw-semibold">Utilizar imagen del post</span></label>
                        <div class="form-text mb-6">Si la nota escaneada incluye una imagen válida, se reutiliza, recorta y ajusta a la resolución configurada sin consumir generación de imágenes.</div>
                        <label class="form-check form-switch form-check-custom form-check-solid mb-2"><input type="checkbox" name="generate_image" value="1" class="form-check-input" @checked(old('generate_image', $profile->generate_image))><span class="form-check-label fw-semibold">Generar imagen con IA si no hay una original</span></label>
                        <div class="form-text mb-6">OpenAI sólo se utilizará cuando no exista una imagen original disponible o no sea posible descargarla.</div>
                        <div class="mb-5">
                            <label class="form-label">Modelo</label>
                            <select name="image_model" id="image-model" class="form-select form-select-solid">
                                @foreach (App\Models\AiPromptProfile::imageModelOptions() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('image_model', App\Models\AiPromptProfile::normalizeImageModel($profile->image_model)) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">GPT Image 2 en calidad baja es más económico que GPT Image 1.5. La versión 1.5 está deprecada y se conserva sólo por compatibilidad.</div>
                        </div>
                        <div class="mb-5">
                            <label class="form-label">Resolución</label>
                            <select name="image_size" id="image-size" class="form-select form-select-solid">
                                @foreach (App\Models\AiPromptProfile::imageSizeOptions() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('image_size', $profile->image_size) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-5">
                            <label class="form-label">Calidad</label>
                            <select name="image_quality" id="image-quality" class="form-select form-select-solid">
                                @foreach (App\Models\AiPromptProfile::imageQualityOptions() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('image_quality', $profile->image_quality) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="notice bg-light-success border border-success border-dashed rounded p-4 mb-5">
                            <div class="fw-bold text-gray-900">Costo estimado de salida</div>
                            <div class="fs-4 fw-bold text-success mt-1" id="image-cost-per-unit">—</div>
                            <div class="text-gray-700 fs-7 mt-1" id="image-cost-at-scale">Este costo sólo aplica cuando sea necesario generar con IA.</div>
                        </div>
                        <div class="row g-5 mb-5">
                            <div class="col-7">
                                <label class="form-label">Formato del archivo</label>
                                <select name="image_format" id="image-format" class="form-select form-select-solid">
                                    @foreach (App\Models\AiPromptProfile::imageFormatOptions() as $value => $label)
                                        <option value="{{ $value }}" @selected(old('image_format', $profile->image_format ?: 'jpeg') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-5">
                                <label class="form-label">Compresión</label>
                                <input type="number" name="image_compression" min="40" max="100" class="form-control form-control-solid" value="{{ old('image_compression', $profile->image_compression ?: 85) }}">
                            </div>
                            <div class="col-12 form-text mt-2">JPEG 85 reduce almacenamiento y transferencia sin afectar el costo de generación. PNG ignora la compresión.</div>
                        </div>
                        <div><label class="form-label">Estilo visual</label><textarea name="image_style" rows="4" class="form-control form-control-solid">{{ old('image_style', $profile->image_style) }}</textarea></div>
                    </div>
                </div>

                <input type="hidden" name="is_default" value="{{ $profile->is_default ? 1 : 0 }}">
                <div class="notice bg-light-primary border border-primary border-dashed rounded p-5 mb-7 text-gray-700 fs-7">
                    Este perfil es global y está disponible para todos los usuarios, empresas y fuentes del sistema.
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="ki-outline ki-check fs-2"></i>{{ $editing ? 'Guardar cambios' : 'Crear perfil' }}</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const costs = @json($imageCostEstimates);
            const model = document.getElementById('image-model');
            const size = document.getElementById('image-size');
            const quality = document.getElementById('image-quality');
            const unit = document.getElementById('image-cost-per-unit');
            const scale = document.getElementById('image-cost-at-scale');

            const refresh = () => {
                const cost = Number(costs?.[model.value]?.[quality.value]?.[size.value] || 0);
                unit.textContent = cost > 0 ? `$${cost.toFixed(3)} USD por imagen` : 'Costo no disponible';
                scale.textContent = cost > 0
                    ? `100 imágenes generadas con IA: ~$${(cost * 100).toFixed(2)} USD. Las imágenes originales reutilizadas cuestan $0.`
                    : 'Consulta el precio vigente del modelo antes de generar en volumen.';
            };

            [model, size, quality].forEach(element => element?.addEventListener('change', refresh));
            refresh();
        })();
    </script>
@endpush
