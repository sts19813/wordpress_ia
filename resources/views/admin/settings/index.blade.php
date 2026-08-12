@extends('layouts.admin')

@section('title', 'Configuración IA | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 w-100">
        <div><h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">Configuración IA</h1><div class="text-muted fw-semibold fs-7 pt-1">Perfiles de system prompt, redacción, extensión e imagen.</div></div>
        <span class="badge badge-light-primary fs-7">2 perfiles globales para todo el sistema</span>
    </div>
@endsection

@section('content')
    <div class="notice d-flex {{ $apiKeyConfigured ? 'bg-light-success border-success' : 'bg-light-warning border-warning' }} rounded border border-dashed p-6 mb-7">
        <i class="ki-outline {{ $apiKeyConfigured ? 'ki-check-circle text-success' : 'ki-information-5 text-warning' }} fs-2tx me-4"></i>
        <div class="min-w-0 flex-grow-1">
            <div class="fw-bold text-gray-900">API de OpenAI {{ $apiKeyConfigured ? 'configurada' : 'pendiente de configurar' }}</div>
            <div class="text-gray-700 fs-7 mt-1">
                @if ($apiKeyConfigured)
                    La clave se carga desde el entorno y no se muestra ni almacena en la base de datos.
                @else
                    Agrega <code>OPENAI_API_KEY</code> en <code>.env</code> antes de generar el primer borrador.
                @endif
            </div>
        </div>
    </div>

    <div class="row g-7">
        @foreach ($profiles as $profile)
            <div class="col-xl-6">
                <div class="card card-flush h-100">
                    <div class="card-header align-items-center">
                        <div class="card-title"><div><h3 class="fw-bold mb-1">{{ $profile->name }}</h3>@if ($profile->is_default)<span class="badge badge-light-success">Predeterminado</span>@endif <span class="badge badge-light-primary">Global</span></div></div>
                        <div class="card-toolbar d-flex gap-2">
                            @can('update', $profile)
                                <a href="{{ route('admin.settings.prompts.edit', $profile) }}" class="btn btn-icon btn-light-primary btn-sm"><i class="ki-outline ki-pencil fs-3"></i></a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body pt-4">
                        <div class="row g-5 mb-6">
                            <div class="col-6"><div class="text-muted fs-7">Modelo</div><code>{{ $profile->model }}</code></div>
                            <div class="col-6"><div class="text-muted fs-7">Temperatura</div><div class="fw-bold">{{ $profile->temperature }}</div></div>
                            <div class="col-6"><div class="text-muted fs-7">Extensión</div><div class="fw-bold">{{ App\Models\AiPromptProfile::lengthOptions()[$profile->content_length] }}</div></div>
                            <div class="col-6"><div class="text-muted fs-7">Imagen</div><div class="fw-bold">{{ $profile->generate_image ? 'Sí · '.$profile->image_quality : 'No' }}</div></div>
                            <div class="col-12"><div class="text-muted fs-7">Redacción</div><div class="fw-bold">{{ $profile->writing_style }} · {{ $profile->tone }}</div></div>
                        </div>
                        <div class="bg-light rounded p-4 text-gray-700 fs-7" style="white-space: pre-wrap; max-height: 150px; overflow: auto;">{{ $profile->system_prompt }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('styles')
    <style>
        @media (max-width: 767.98px) {
            .notice {
                align-items: flex-start;
                padding: 1.25rem !important;
            }

            .notice code,
            .card-body code,
            .card-body .fw-bold {
                overflow-wrap: anywhere;
            }

            .card-header {
                align-items: flex-start !important;
                flex-wrap: wrap;
                gap: .75rem;
                padding-top: 1.25rem;
                padding-bottom: 1rem;
            }

            .card-toolbar {
                margin-left: 0 !important;
            }
        }
    </style>
@endpush
