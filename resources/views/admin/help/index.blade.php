@extends('layouts.admin')

@section('title', 'Ayuda | '.config('app.name'))

@section('toolbar')
    <div class="d-flex align-items-start justify-content-between gap-3 w-100">
        <div>
            <h1 class="page-heading text-gray-900 fw-bold fs-2 my-0">¿Cómo podemos ayudarte?</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Encuentra una guía clara para continuar con tu trabajo.</div>
        </div>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.help.analytics') }}" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-chart-simple fs-4"></i>Resumen de ayuda</a>
        @endif
    </div>
@endsection

@section('content')
    <style>
        .help-hero { background: linear-gradient(135deg, #1769d5, #52a7ff); border-radius: 20px; color: #fff; overflow: hidden; position: relative; }
        .help-hero::after { content: ''; position: absolute; width: 270px; height: 270px; border-radius: 50%; right: -85px; top: -150px; background: rgba(255,255,255,.12); }
        .help-hero .card-body { position: relative; z-index: 1; }
        .help-search { max-width: 720px; }
        .help-search .form-control { min-height: 56px; border: 0; padding-left: 48px; border-radius: 12px; box-shadow: 0 12px 30px rgba(11, 53, 113, .2); }
        .help-search .ki-search-list { position: absolute; left: 17px; top: 19px; color: #667085; z-index: 2; }
        .help-category { display: block; color: inherit; transition: transform .18s ease, box-shadow .18s ease; }
        .help-category:hover { color: inherit; transform: translateY(-3px); box-shadow: 0 14px 32px rgba(31, 42, 68, .12); }
        .help-category-icon { width: 46px; height: 46px; border-radius: 13px; display: inline-flex; align-items: center; justify-content: center; background: #eaf3ff; color: #2f80ed; }
        .help-article-card { color: inherit; transition: border-color .18s ease, transform .18s ease; }
        .help-article-card:hover { color: inherit; border-color: #93c5fd; transform: translateY(-2px); }
        .help-empty { border: 1px dashed #cbd5e1; border-radius: 16px; }
    </style>

    <section class="card help-hero mb-8">
        <div class="card-body py-10 px-7 px-lg-12">
            <h2 class="fw-bold fs-1 mb-3 text-white">Encuentra la respuesta que necesitas</h2>
            <p class="fs-6 mb-7 text-white opacity-85">Busca por tarea, por ejemplo: “crear un post”, “agregar una fuente” o “publicar”.</p>
            <form method="GET" action="{{ route('admin.help.index') }}" class="help-search position-relative">
                <i class="ki-outline ki-search-list fs-3"></i>
                <input type="search" name="q" value="{{ $term }}" class="form-control form-control-lg" placeholder="¿Qué quieres hacer?" aria-label="Buscar en ayuda">
                @if ($selectedCategory)
                    <input type="hidden" name="categoria" value="{{ $selectedCategory }}">
                @endif
            </form>
        </div>
    </section>

    <section class="mb-8" aria-labelledby="help-categories-title">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-5">
            <div>
                <h2 id="help-categories-title" class="fs-3 fw-bold text-gray-900 mb-1">Explora por tema</h2>
                <p class="text-muted mb-0">Elige el área en la que estás trabajando.</p>
            </div>
            @if ($term || $selectedCategory)
                <a href="{{ route('admin.help.index') }}" class="btn btn-sm btn-light-primary">Ver todas las guías</a>
            @endif
        </div>
        <div class="row g-5">
            @foreach ($categories as $category)
                <div class="col-md-6 col-xl">
                    <a href="{{ route('admin.help.index', ['categoria' => $category['slug']]) }}" class="card card-flush help-category h-100 text-decoration-none {{ $selectedCategory === $category['slug'] ? 'border-primary' : '' }}">
                        <div class="card-body p-5">
                            <span class="help-category-icon mb-5"><i class="ki-outline {{ $category['icon'] }} fs-2"></i></span>
                            <div class="fw-bold text-gray-900 mb-2">{{ $category['name'] }}</div>
                            <div class="text-muted fs-8 mb-4">{{ $category['description'] }}</div>
                            <span class="text-primary fw-bold fs-8">{{ $category['article_count'] }} {{ $category['article_count'] === 1 ? 'guía' : 'guías' }}</span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </section>

    @if (! $term && ! $selectedCategory)
        <section class="mb-8" aria-labelledby="help-workflows-title">
            <div class="mb-5">
                <h2 id="help-workflows-title" class="fs-3 fw-bold text-gray-900 mb-1">Rutas guiadas</h2>
                <p class="text-muted mb-0">Sigue una ruta completa cuando no sepas cuál es el siguiente paso.</p>
            </div>
            <div class="row g-5">
                @foreach ($workflows as $workflow)
                    <div class="col-lg-4">
                        <div class="card card-flush h-100">
                            <div class="card-body p-6">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <span class="help-category-icon"><i class="ki-outline {{ $workflow['icon'] }} fs-2"></i></span>
                                    <h3 class="fs-5 fw-bold text-gray-900 mb-0">{{ $workflow['title'] }}</h3>
                                </div>
                                <p class="text-muted fs-7 mb-5">{{ $workflow['description'] }}</p>
                                <ol class="ps-4 mb-0">
                                    @foreach ($workflow['articles'] as $slug)
                                        @php($workflowArticle = \App\Support\HelpCenter::article($slug))
                                        @if ($workflowArticle)
                                            <li class="mb-2 ps-1"><a href="{{ route('admin.help.show', $workflowArticle['slug']) }}" class="fw-semibold text-primary">{{ $workflowArticle['title'] }}</a></li>
                                        @endif
                                    @endforeach
                                </ol>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section aria-labelledby="help-articles-title">
        <div class="mb-5">
            <h2 id="help-articles-title" class="fs-3 fw-bold text-gray-900 mb-1">
                @if ($term)
                    Resultados para “{{ $term }}"
                @elseif ($selectedCategory)
                    Guías disponibles
                @else
                    Guías para comenzar
                @endif
            </h2>
            <p class="text-muted mb-0">Sigue los pasos y continúa con seguridad.</p>
        </div>
        @if ($articles->isNotEmpty())
            <div class="row g-5">
                @foreach ($articles as $article)
                    @php($category = $categories->firstWhere('slug', $article['category']))
                    <div class="col-md-6 col-xl-4">
                        <a href="{{ route('admin.help.show', $article['slug']) }}" class="card card-flush help-article-card h-100 text-decoration-none">
                            <div class="card-body p-6 d-flex flex-column">
                                <div class="d-flex align-items-center gap-2 text-primary fs-8 fw-bold mb-4">
                                    <i class="ki-outline {{ $category['icon'] }}"></i>{{ $category['name'] }}
                                </div>
                                <h3 class="fs-5 fw-bold text-gray-900 mb-3">{{ $article['title'] }}</h3>
                                <p class="text-muted fs-7 mb-6">{{ $article['summary'] }}</p>
                                <span class="mt-auto text-primary fw-bold fs-8">Ver guía <i class="ki-outline ki-arrow-right fs-7 ms-1"></i></span>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <div class="help-empty bg-white text-center py-12 px-6">
                <i class="ki-outline ki-search-list fs-3x text-muted mb-4"></i>
                <h3 class="fs-4 fw-bold text-gray-900">No encontramos una guía con esa búsqueda</h3>
                <p class="text-muted mb-5">Intenta usar palabras más simples, como “post”, “fuente” o “publicar”.</p>
                <a href="{{ route('admin.help.index') }}" class="btn btn-light-primary">Ver todas las guías</a>
                <form method="POST" action="{{ route('admin.help.feedback') }}" class="text-start mx-auto mt-7" style="max-width: 620px;">
                    @csrf
                    <input type="hidden" name="search_term" value="{{ $term }}">
                    <label for="help-missing-message" class="form-label fw-bold">¿Qué información esperabas encontrar?</label>
                    <textarea id="help-missing-message" name="message" rows="3" class="form-control form-control-solid" maxlength="2000" placeholder="Cuéntanos qué necesitas hacer para que podamos agregar una guía."></textarea>
                    <button type="submit" class="btn btn-primary btn-sm mt-3">Enviar sugerencia</button>
                </form>
            </div>
        @endif
    </section>
@endsection
