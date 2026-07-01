@extends('layouts.admin')

@php($editing = $profile->exists)

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
                        <div class="mb-6"><label class="form-label required">Nombre del perfil</label><input name="name" class="form-control form-control-solid" value="{{ old('name', $profile->name) }}" required placeholder="Ej. Noticias tecnológicas"></div>
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
                        <div class="mb-6"><label class="form-label required">Modelo</label><input name="model" class="form-control form-control-solid" value="{{ old('model', $profile->model) }}" required><div class="form-text">El predeterminado admite temperatura y salida estructurada.</div></div>
                        <div><div class="d-flex justify-content-between"><label class="form-label required">Temperatura</label><strong id="temperature-value">{{ old('temperature', $profile->temperature) }}</strong></div><input type="range" name="temperature" min="0" max="2" step="0.05" class="form-range" value="{{ old('temperature', $profile->temperature) }}" oninput="document.getElementById('temperature-value').textContent=this.value"><div class="form-text">0 = más estable; 2 = más variación. Algunos modelos de razonamiento ignoran este control.</div></div>
                    </div>
                </div>

                <div class="card card-flush mb-7">
                    <div class="card-header"><div class="card-title"><h3 class="fw-bold mb-0">Imagen principal</h3></div></div>
                    <div class="card-body">
                        <label class="form-check form-switch form-check-custom form-check-solid mb-6"><input type="checkbox" name="generate_image" value="1" class="form-check-input" @checked(old('generate_image', $profile->generate_image))><span class="form-check-label fw-semibold">Generar imagen con la nota</span></label>
                        <div class="mb-5"><label class="form-label">Modelo</label><input name="image_model" class="form-control form-control-solid" value="{{ old('image_model', $profile->image_model) }}"></div>
                        <div class="mb-5"><label class="form-label">Resolución</label><select name="image_size" class="form-select form-select-solid">@foreach (['1024x1024' => 'Cuadrada · 1024×1024', '1024x1536' => 'Vertical · 1024×1536', '1536x1024' => 'Horizontal · 1536×1024'] as $value => $label)<option value="{{ $value }}" @selected(old('image_size', $profile->image_size) === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="mb-5"><label class="form-label">Calidad</label><select name="image_quality" class="form-select form-select-solid">@foreach (['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta'] as $value => $label)<option value="{{ $value }}" @selected(old('image_quality', $profile->image_quality) === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div><label class="form-label">Estilo visual</label><textarea name="image_style" rows="4" class="form-control form-control-solid">{{ old('image_style', $profile->image_style) }}</textarea></div>
                    </div>
                </div>

                <div class="card card-flush mb-7"><div class="card-body"><label class="form-check form-switch form-check-custom form-check-solid"><input type="checkbox" name="is_default" value="1" class="form-check-input" @checked(old('is_default', $profile->is_default))><span class="form-check-label fw-semibold">Usar como perfil predeterminado</span></label></div></div>
                <button class="btn btn-primary w-100" type="submit"><i class="ki-outline ki-check fs-2"></i>{{ $editing ? 'Guardar cambios' : 'Crear perfil' }}</button>
            </div>
        </div>
    </form>
@endsection
