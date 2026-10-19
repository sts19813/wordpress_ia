@extends('layouts.admin')

@section('title', $helpArticle['title'].' | Ayuda | '.config('app.name'))

@section('toolbar')
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('admin.help.index') }}" class="btn btn-icon btn-light-primary" aria-label="Volver al centro de ayuda">
            <i class="ki-outline ki-arrow-left fs-2"></i>
        </a>
        <div>
            <div class="text-primary fw-bold fs-8 mb-1">Centro de ayuda</div>
            <h1 class="page-heading text-gray-900 fw-bold fs-3 my-0">{{ $helpArticle['title'] }}</h1>
        </div>
    </div>
@endsection

@section('content')
    <style>
        .help-article-steps { counter-reset: help-step; }
        .help-article-step { position: relative; padding-left: 68px; min-height: 50px; }
        .help-article-step::before { counter-increment: help-step; content: counter(help-step); position: absolute; left: 0; top: -2px; width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #eaf3ff; color: #1769d5; font-weight: 800; }
        .help-article-step:not(:last-child)::after { content: ''; position: absolute; left: 20px; top: 42px; width: 2px; height: calc(100% - 12px); background: #dbeafe; }
        .help-tip { border-left: 4px solid #2f80ed; background: #f3f8ff; }
        .help-screenshot { position: relative; overflow: hidden; border: 1px solid #dbe4f0; border-radius: 14px; background: #f8fafc; }
        .help-screenshot img { display: block; width: 100%; height: auto; }
        .help-screenshot-marker { position: absolute; top: calc(var(--marker-top) * 1%); left: calc(var(--marker-left) * 1%); width: 31px; height: 31px; transform: translate(-50%, -50%); display: flex; align-items: center; justify-content: center; border: 3px solid #fff; border-radius: 50%; background: #1769d5; color: #fff; font-size: .8rem; font-weight: 800; box-shadow: 0 4px 12px rgba(23, 105, 213, .4); }
        .help-screenshot-callouts { border-left: 3px solid #dbeafe; }
        .help-screenshot-callouts li::marker { color: #1769d5; font-weight: 800; }
    </style>

    <div class="row g-7">
        <div class="col-xl-8">
            <article class="card card-flush">
                <div class="card-body p-7 p-lg-10">
                    <p class="fs-5 text-gray-700 lh-lg mb-8">{{ $helpArticle['intro'] }}</p>

                    @if ($helpArticle['prerequisites'])
                        <section class="mb-9" aria-labelledby="help-prerequisites">
                            <h2 id="help-prerequisites" class="fs-4 fw-bold text-gray-900 mb-4">Antes de comenzar</h2>
                            <ul class="ps-5 mb-0 text-gray-700 lh-lg">
                                @foreach ($helpArticle['prerequisites'] as $prerequisite)
                                    <li class="mb-2">{{ $prerequisite }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @foreach ($helpArticle['screenshots'] as $screenshot)
                        <section class="mb-9" aria-labelledby="help-screenshot-{{ $loop->index }}">
                            <h2 id="help-screenshot-{{ $loop->index }}" class="fs-4 fw-bold text-gray-900 mb-4">{{ $screenshot['title'] }}</h2>
                            <figure class="mb-0">
                                <a href="{{ asset($screenshot['path']) }}" target="_blank" rel="noopener noreferrer" class="help-screenshot d-block" title="Abrir la captura a tamaño completo">
                                    <img src="{{ asset($screenshot['path']) }}" alt="{{ $screenshot['alt'] }}" loading="lazy">
                                    @foreach ($screenshot['callouts'] as $callout)
                                        <span class="help-screenshot-marker" style="--marker-top: {{ (float) $callout['top'] }}; --marker-left: {{ (float) $callout['left'] }};" aria-hidden="true">{{ $callout['number'] }}</span>
                                    @endforeach
                                </a>
                                <figcaption class="text-muted fs-8 mt-3">Captura con datos de demostración. Selecciónala para verla a tamaño completo.</figcaption>
                            </figure>
                            <ol class="help-screenshot-callouts ps-5 mt-5 mb-0 text-gray-700 fs-7 lh-lg">
                                @foreach ($screenshot['callouts'] as $callout)
                                    <li class="mb-2">{{ $callout['text'] }}</li>
                                @endforeach
                            </ol>
                        </section>
                    @endforeach

                    <h2 class="fs-3 fw-bold text-gray-900 mb-6">Paso a paso</h2>
                    <div class="help-article-steps mb-9">
                        @foreach ($helpArticle['steps'] as $step)
                            <div class="help-article-step pb-7 fs-6 text-gray-700 lh-lg">{{ $step }}</div>
                        @endforeach
                    </div>

                    <div class="help-tip rounded p-5 d-flex gap-4 align-items-start">
                        <i class="ki-outline ki-information-5 fs-2x text-primary"></i>
                        <div><span class="fw-bold text-gray-900 d-block mb-1">Consejo</span><span class="text-gray-700">{{ $helpArticle['tip'] }}</span></div>
                    </div>

                    @if ($helpArticle['result'])
                        <section class="mt-9" aria-labelledby="help-result">
                            <h2 id="help-result" class="fs-4 fw-bold text-gray-900 mb-3">Al finalizar</h2>
                            <p class="text-gray-700 lh-lg mb-0">{{ $helpArticle['result'] }}</p>
                        </section>
                    @endif

                    @if ($helpArticle['common_problems'])
                        <section class="mt-9" aria-labelledby="help-problems">
                            <h2 id="help-problems" class="fs-4 fw-bold text-gray-900 mb-4">Si algo no sale como esperabas</h2>
                            <div class="accordion" id="help-problems-accordion">
                                @foreach ($helpArticle['common_problems'] as $index => $problem)
                                    <div class="accordion-item border rounded mb-3 overflow-hidden">
                                        <h3 class="accordion-header" id="help-problem-heading-{{ $index }}">
                                            <button class="accordion-button {{ $index ? 'collapsed' : '' }} fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#help-problem-{{ $index }}" aria-expanded="{{ $index ? 'false' : 'true' }}" aria-controls="help-problem-{{ $index }}">
                                                {{ $problem['question'] }}
                                            </button>
                                        </h3>
                                        <div id="help-problem-{{ $index }}" class="accordion-collapse collapse {{ $index ? '' : 'show' }}" aria-labelledby="help-problem-heading-{{ $index }}" data-bs-parent="#help-problems-accordion">
                                            <div class="accordion-body text-gray-700 lh-lg">{{ $problem['answer'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section class="mt-9 pt-8 border-top" aria-labelledby="help-feedback-title">
                        <h2 id="help-feedback-title" class="fs-4 fw-bold text-gray-900 mb-2">¿Te resultó útil esta guía?</h2>
                        <p class="text-muted fs-7 mb-4">Tu respuesta nos ayuda a mantener las instrucciones claras y actualizadas. Si algo faltó, puedes contarnos qué paso necesitas.</p>
                        <form method="POST" action="{{ route('admin.help.feedback') }}">
                            @csrf
                            <input type="hidden" name="article_slug" value="{{ $helpArticle['slug'] }}">
                            <label for="help-feedback-message" class="form-label fs-7 fw-semibold">Comentario opcional</label>
                            <textarea id="help-feedback-message" name="message" rows="3" maxlength="2000" class="form-control form-control-solid mb-4" placeholder="Por ejemplo: no encontré el paso para conectar mi destino."></textarea>
                            <div class="d-flex flex-wrap gap-3">
                                <button type="submit" name="rating" value="helpful" class="btn btn-light-success"><i class="ki-outline ki-like fs-4"></i>Sí, me ayudó</button>
                                <button type="submit" name="rating" value="not_helpful" class="btn btn-light-warning"><i class="ki-outline ki-information-5 fs-4"></i>Necesito más ayuda</button>
                            </div>
                        </form>
                    </section>
                </div>
            </article>
        </div>

        <aside class="col-xl-4">
            <div class="card card-flush mb-7">
                <div class="card-body p-6">
                    <div class="fw-bold text-gray-900 mb-2">¿No encuentras lo que necesitas?</div>
                    <p class="text-muted fs-7 mb-5">Prueba otra búsqueda o consulta con la persona administradora de tu cuenta.</p>
                    <a href="{{ route('admin.help.index') }}" class="btn btn-light-primary w-100">Buscar otra guía</a>
                </div>
            </div>

            @if ($relatedArticles->isNotEmpty())
                <div class="card card-flush">
                    <div class="card-body p-6">
                        <h2 class="fs-5 fw-bold text-gray-900 mb-5">También te puede ayudar</h2>
                        <div class="d-flex flex-column gap-4">
                            @foreach ($relatedArticles as $relatedArticle)
                                <a href="{{ route('admin.help.show', $relatedArticle['slug']) }}" class="text-decoration-none">
                                    <span class="d-block fw-bold text-gray-900 mb-1">{{ $relatedArticle['title'] }}</span>
                                    <span class="text-primary fs-8 fw-bold">Ver guía <i class="ki-outline ki-arrow-right fs-8"></i></span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </aside>
    </div>
@endsection
