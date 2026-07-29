@extends('layouts.admin')

@section('title', 'Centro de operaciones | '.config('app.name'))

@section('toolbar')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-4 w-100">
        <div>
            <div class="d-flex align-items-center gap-3 mb-1">
                <h1 class="page-heading text-gray-900 fw-bold fs-2 my-0">Centro de operaciones</h1>
                <span class="ops-live-badge"><span></span>En vivo</span>
            </div>
            <div class="text-muted fw-semibold fs-7 text-capitalize">{{ $todayLabel }} · Resumen de todo el flujo editorial</div>
        </div>
        <div class="d-flex flex-wrap gap-3">
            <a href="{{ route('admin.publications.index') }}" class="btn btn-light-primary">
                <i class="ki-outline ki-send fs-2"></i>Ver publicaciones
            </a>
            <a href="{{ route('admin.quick-posts.create') }}" class="btn btn-primary">
                <i class="ki-outline ki-plus fs-2"></i>Crear post rápido
            </a>
        </div>
    </div>
@endsection

@section('content')
    <div class="ops-dashboard">
        @if ($stalledTasks > 0 || $sourceErrors > 0 || $destinationErrors > 0)
            <div class="ops-status-strip ops-status-strip-warning mb-7">
                <div class="ops-status-icon"><i class="ki-outline ki-information-5"></i></div>
                <div class="flex-grow-1">
                    <div class="fw-bold text-gray-900">Hay elementos que necesitan atención</div>
                    <div class="text-gray-600 fs-7 mt-1">
                        @if ($stalledTasks > 0)
                            {{ $stalledTasks }} {{ \Illuminate\Support\Str::plural('trabajo', $stalledTasks) }} llevan más de 2 minutos en cola.
                        @endif
                        @if ($sourceErrors > 0)
                            {{ $sourceErrors }} {{ \Illuminate\Support\Str::plural('fuente', $sourceErrors) }} presentan atraso o error.
                        @endif
                        @if ($destinationErrors > 0)
                            {{ $destinationErrors }} {{ \Illuminate\Support\Str::plural('destino', $destinationErrors) }} requieren revisión.
                        @endif
                    </div>
                </div>
                <a href="{{ route('admin.scheduler.index') }}" class="btn btn-sm btn-warning">Revisar operación</a>
            </div>
        @else
            <div class="ops-status-strip ops-status-strip-success mb-7">
                <div class="ops-status-icon"><i class="ki-outline ki-check-circle"></i></div>
                <div class="flex-grow-1">
                    <div class="fw-bold text-gray-900">La operación está estable</div>
                    <div class="text-gray-600 fs-7 mt-1">No hay colas detenidas ni conexiones con alertas en este momento.</div>
                </div>
                <span class="badge badge-light-success fs-8">Sistemas operativos</span>
            </div>
        @endif

        <div class="row g-5 g-xl-7 mb-7">
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('admin.source-scan-logs.index', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]) }}" class="card card-flush ops-kpi-card ops-kpi-purple h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between mb-7">
                            <div class="ops-kpi-icon"><i class="ki-outline ki-magnifier"></i></div>
                            <span class="ops-kpi-period">Hoy</span>
                        </div>
                        <div class="ops-kpi-value">{{ number_format($scansToday) }}</div>
                        <div class="ops-kpi-label">Entradas escaneadas</div>
                        <div class="ops-kpi-detail">
                            <span>{{ number_format($acceptedToday) }} aplicaron</span>
                            <span class="ops-kpi-dot"></span>
                            <span>{{ $scanAcceptanceRate === null ? 'Sin muestra' : $scanAcceptanceRate.'% aceptación' }}</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('admin.ai-articles.index') }}" class="card card-flush ops-kpi-card ops-kpi-blue h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between mb-7">
                            <div class="ops-kpi-icon"><i class="ki-outline ki-document"></i></div>
                            <span class="ops-kpi-period">Hoy</span>
                        </div>
                        <div class="ops-kpi-value">{{ number_format($generatedToday) }}</div>
                        <div class="ops-kpi-label">Posts generados</div>
                        <div class="ops-kpi-detail">
                            <span>{{ number_format($quickPostsToday) }} posts rápidos capturados</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('admin.publications.index') }}" class="card card-flush ops-kpi-card ops-kpi-green h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between mb-7">
                            <div class="ops-kpi-icon"><i class="ki-outline ki-send"></i></div>
                            <span class="ops-kpi-period">Hoy</span>
                        </div>
                        <div class="ops-kpi-value">{{ number_format($publishedToday) }}</div>
                        <div class="ops-kpi-label">Publicaciones realizadas</div>
                        <div class="ops-kpi-detail">
                            <span>{{ number_format($publishedArticlesToday) }} posts únicos</span>
                            <span class="ops-kpi-dot"></span>
                            <span>{{ $publicationSuccessRate === null ? 'Sin intentos' : $publicationSuccessRate.'% éxito' }}</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-3">
                <a href="#errores-recientes" class="card card-flush ops-kpi-card ops-kpi-red h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between mb-7">
                            <div class="ops-kpi-icon"><i class="ki-outline ki-cross-circle"></i></div>
                            <span class="ops-kpi-period">Hoy</span>
                        </div>
                        <div class="ops-kpi-value">{{ number_format($errorsToday) }}</div>
                        <div class="ops-kpi-label">Errores registrados</div>
                        <div class="ops-kpi-detail">
                            <span>{{ number_format($activeErrors) }} en el historial activo</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-5 g-xl-7 mb-7">
            <div class="col-xl-8">
                <div class="card card-flush h-100">
                    <div class="card-header align-items-center border-0 pt-4">
                        <div class="card-title d-block">
                            <h3 class="fw-bold text-gray-900 mb-1">Ritmo de producción</h3>
                            <div class="text-muted fw-semibold fs-7">Actividad de los últimos siete días</div>
                        </div>
                        <div class="ops-chart-legend">
                            <span><i class="ops-legend-purple"></i>Escaneados</span>
                            <span><i class="ops-legend-blue"></i>Generados</span>
                            <span><i class="ops-legend-green"></i>Publicados</span>
                        </div>
                    </div>
                    <div class="card-body pt-4">
                        <div class="ops-chart">
                            @foreach ($activity as $day)
                                <div class="ops-chart-day">
                                    <div class="ops-chart-bars">
                                        <span class="ops-bar ops-bar-purple" style="height: {{ $day['scan_height'] }}px" title="{{ $day['scans'] }} escaneados"></span>
                                        <span class="ops-bar ops-bar-blue" style="height: {{ $day['generated_height'] }}px" title="{{ $day['generated'] }} generados"></span>
                                        <span class="ops-bar ops-bar-green" style="height: {{ $day['published_height'] }}px" title="{{ $day['published'] }} publicados"></span>
                                    </div>
                                    <div class="ops-chart-values">{{ $day['scans'] }} · {{ $day['generated'] }} · {{ $day['published'] }}</div>
                                    <div class="ops-chart-label">{{ $day['date']->locale('es')->isoFormat('dd') }}</div>
                                    <div class="ops-chart-date">{{ $day['date']->format('d/m') }}</div>
                                    @if ($day['errors'] > 0)
                                        <span class="ops-chart-error" title="{{ $day['errors'] }} errores">{{ $day['errors'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card card-flush h-100">
                    <div class="card-header border-0 pt-4">
                        <div class="card-title d-block">
                            <h3 class="fw-bold text-gray-900 mb-1">Estado operativo</h3>
                            <div class="text-muted fw-semibold fs-7">Capacidad y conexiones actuales</div>
                        </div>
                    </div>
                    <div class="card-body pt-2">
                        <a href="{{ route('admin.source-sites.index') }}" class="ops-health-row">
                            <span class="ops-health-icon bg-light-primary text-primary"><i class="ki-outline ki-abstract-26"></i></span>
                            <span class="flex-grow-1">
                                <span class="ops-health-label">Fuentes activas</span>
                                <span class="ops-health-meta">
                                    @if ($nextScanAt)
                                        Próxima revisión {{ $nextScanAt->isPast() ? 'pendiente' : $nextScanAt->diffForHumans() }}
                                    @else
                                        Sin próxima revisión
                                    @endif
                                </span>
                            </span>
                            <strong>{{ $activeSources }}</strong>
                        </a>
                        <a href="{{ route('admin.wordpress-sites.index') }}" class="ops-health-row">
                            <span class="ops-health-icon bg-light-success text-success"><i class="ki-outline ki-cloud"></i></span>
                            <span class="flex-grow-1">
                                <span class="ops-health-label">Destinos conectados</span>
                                <span class="ops-health-meta">{{ $destinationErrors > 0 ? $destinationErrors.' requieren atención' : 'Conexiones sin alertas' }}</span>
                            </span>
                            <strong>{{ $activeDestinations }}</strong>
                        </a>
                        <a href="{{ route('admin.scheduler.index') }}" class="ops-health-row">
                            <span class="ops-health-icon bg-light-warning text-warning"><i class="ki-outline ki-time"></i></span>
                            <span class="flex-grow-1">
                                <span class="ops-health-label">Cola de trabajo</span>
                                <span class="ops-health-meta">{{ $runningTasks }} procesando · {{ $databaseQueueSize }} en el worker</span>
                            </span>
                            <strong>{{ $queuedTasks }}</strong>
                        </a>
                        <a href="{{ route('admin.scheduler.index') }}" class="ops-health-row">
                            <span class="ops-health-icon bg-light-danger text-danger"><i class="ki-outline ki-shield-cross"></i></span>
                            <span class="flex-grow-1">
                                <span class="ops-health-label">Fallos del worker</span>
                                <span class="ops-health-meta">{{ $failedQueueSize > 0 ? 'Requiere depuración' : 'Sin fallos retenidos' }}</span>
                            </span>
                            <strong>{{ $failedQueueSize }}</strong>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-7 mb-10">
            <div class="col-xl-6" id="errores-recientes">
                <div class="card card-flush h-100">
                    <div class="card-header align-items-center border-0 pt-4">
                        <div class="card-title d-block">
                            <h3 class="fw-bold text-gray-900 mb-1">Errores recientes</h3>
                            <div class="text-muted fw-semibold fs-7">Generación, automatización y publicación</div>
                        </div>
                        @if ($recentErrors->isNotEmpty())
                            <a href="{{ route('admin.scheduler.index') }}" class="btn btn-sm btn-light">Ver procesos</a>
                        @endif
                    </div>
                    <div class="card-body pt-2">
                        @forelse ($recentErrors as $error)
                            <a href="{{ $error['url'] }}" class="ops-error-row">
                                <span class="ops-error-mark"></span>
                                <span class="flex-grow-1 min-w-0">
                                    <span class="d-flex align-items-center justify-content-between gap-3">
                                        <strong class="text-gray-900 fs-7">{{ $error['title'] }}</strong>
                                        <time class="text-muted fs-8 text-nowrap">{{ $error['occurred_at']->diffForHumans() }}</time>
                                    </span>
                                    <span class="d-block text-gray-600 fs-8 text-truncate mt-1">{{ $error['context'] }}</span>
                                    <span class="d-block text-danger fs-8 text-truncate mt-1">{{ \Illuminate\Support\Str::limit($error['message'], 115) }}</span>
                                </span>
                                <i class="ki-outline ki-right fs-3 text-gray-400"></i>
                            </a>
                        @empty
                            <div class="ops-empty-state">
                                <span class="ops-empty-icon bg-light-success text-success"><i class="ki-outline ki-check-circle"></i></span>
                                <div class="fw-bold text-gray-900 mt-4">Todo limpio</div>
                                <div class="text-muted fs-7 mt-1">No hay errores registrados en el flujo.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card card-flush h-100">
                    <div class="card-header align-items-center border-0 pt-4">
                        <div class="card-title d-block">
                            <h3 class="fw-bold text-gray-900 mb-1">Actividad reciente</h3>
                            <div class="text-muted fw-semibold fs-7">Últimos movimientos de la automatización</div>
                        </div>
                        <a href="{{ route('admin.scheduler.index') }}" class="btn btn-sm btn-light-primary">Abrir programador</a>
                    </div>
                    <div class="card-body pt-2">
                        @forelse ($recentTasks as $task)
                            @php
                                $taskStatusClass = match ($task->status) {
                                    \App\Models\Scheduler::STATUS_COMPLETED => 'success',
                                    \App\Models\Scheduler::STATUS_FAILED => 'danger',
                                    \App\Models\Scheduler::STATUS_RUNNING => 'primary',
                                    default => 'warning',
                                };
                                $taskContext = $task->sourceSite?->name
                                    ?: $task->sourcePost?->title
                                    ?: $task->article?->title
                                    ?: $task->name;
                            @endphp
                            <a href="{{ route('admin.scheduler.index', ['task' => $task->id]) }}" class="ops-activity-row">
                                <span class="ops-activity-icon bg-light-{{ $taskStatusClass }} text-{{ $taskStatusClass }}">
                                    <i class="ki-outline {{ $task->status === \App\Models\Scheduler::STATUS_COMPLETED ? 'ki-check' : ($task->status === \App\Models\Scheduler::STATUS_FAILED ? 'ki-cross' : 'ki-time') }}"></i>
                                </span>
                                <span class="flex-grow-1 min-w-0">
                                    <span class="d-flex align-items-center gap-2">
                                        <strong class="text-gray-900 fs-7">{{ $task->typeLabel() }}</strong>
                                        <span class="badge badge-light-{{ $taskStatusClass }} fs-9">{{ $task->statusLabel() }}</span>
                                    </span>
                                    <span class="d-block text-muted fs-8 text-truncate mt-1">{{ \Illuminate\Support\Str::limit($taskContext, 80) }}</span>
                                </span>
                                <time class="text-muted fs-8 text-nowrap">{{ $task->updated_at->diffForHumans() }}</time>
                            </a>
                        @empty
                            <div class="ops-empty-state">
                                <span class="ops-empty-icon bg-light-primary text-primary"><i class="ki-outline ki-time"></i></span>
                                <div class="fw-bold text-gray-900 mt-4">Aún no hay actividad</div>
                                <div class="text-muted fs-7 mt-1">Los procesos automáticos aparecerán aquí.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <section class="ops-posts-section">
            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-6">
                <div>
                    <span class="ops-section-eyebrow">Contenido publicado</span>
                    <h2 class="fw-bold text-gray-900 fs-2 mb-1 mt-2">Así se ven tus posts más recientes</h2>
                    <p class="text-muted fw-semibold mb-0">Cada tarjeta reúne todos los destinos en los que ya fue publicado el post.</p>
                </div>
                <a href="{{ route('admin.publications.index') }}" class="btn btn-light-primary align-self-md-center">
                    Historial completo <i class="ki-outline ki-right fs-3 ms-1"></i>
                </a>
            </div>

            @if ($publishedArticles->isNotEmpty())
                <div class="row g-6">
                    @foreach ($publishedArticles as $article)
                        @php
                            $coverImage = $article->images->first(fn ($image) => $image->type === \App\Models\AiImage::TYPE_MAIN && $image->status === \App\Models\AiImage::STATUS_GENERATED)
                                ?: $article->images->firstWhere('status', \App\Models\AiImage::STATUS_GENERATED);
                            $excerpt = trim(strip_tags($article->excerpt ?: $article->content ?: ''));
                            $lastPublication = $article->publications->first();
                        @endphp
                        <div class="col-md-6 col-xxl-4">
                            <article class="card card-flush ops-post-card h-100">
                                <div class="ops-post-media">
                                    @if ($coverImage)
                                        <img src="{{ route('admin.ai-images.file', $coverImage) }}" alt="{{ $article->title }}" loading="lazy">
                                    @else
                                        <div class="ops-post-placeholder">
                                            <i class="ki-outline ki-picture"></i>
                                            <span>Post publicado</span>
                                        </div>
                                    @endif
                                    <div class="ops-post-overlay">
                                        <span><i class="ki-outline ki-check-circle"></i> Publicado</span>
                                        @if ($lastPublication?->published_at)
                                            <time>{{ $lastPublication->published_at->locale('es')->diffForHumans() }}</time>
                                        @endif
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                                        <div class="ops-destination-avatars">
                                            @foreach ($article->publications->take(3) as $publication)
                                                <span class="{{ $publication->wordpressSite?->isFacebookPage() ? 'is-facebook' : 'is-wordpress' }}" title="{{ $publication->wordpressSite?->name }}">
                                                    @if ($publication->wordpressSite?->isFacebookPage())
                                                        <i class="ki-outline ki-facebook"></i>
                                                    @else
                                                        <strong>W</strong>
                                                    @endif
                                                </span>
                                            @endforeach
                                            @if ($article->publications->count() > 3)
                                                <span class="is-more">+{{ $article->publications->count() - 3 }}</span>
                                            @endif
                                        </div>
                                        <span class="text-muted fs-8">{{ $article->publications->count() }} {{ \Illuminate\Support\Str::plural('destino', $article->publications->count()) }}</span>
                                    </div>
                                    <a href="{{ route('admin.ai-articles.show', $article) }}" class="ops-post-title text-gray-900 text-hover-primary">
                                        {{ $article->title }}
                                    </a>
                                    <p class="ops-post-excerpt">{{ \Illuminate\Support\Str::limit($excerpt, 155) ?: 'Contenido publicado correctamente.' }}</p>

                                    @if ($article->tags)
                                        <div class="ops-post-tags">
                                            @foreach (array_slice($article->tags, 0, 3) as $tag)
                                                <span>#{{ ltrim($tag, '#') }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="ops-post-destinations mt-auto">
                                        @foreach ($article->publications as $publication)
                                            @if ($publication->remote_url)
                                                <a href="{{ $publication->remote_url }}" target="_blank" rel="noopener noreferrer">
                                                    <span class="text-truncate">{{ $publication->wordpressSite?->name ?: 'Publicación' }}</span>
                                                    <i class="ki-outline ki-exit-up"></i>
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="card card-flush">
                    <div class="card-body ops-posts-empty">
                        <span class="ops-empty-icon bg-light-primary text-primary"><i class="ki-outline ki-send"></i></span>
                        <h3 class="fw-bold text-gray-900 mt-5 mb-2">Tus publicaciones aparecerán aquí</h3>
                        <p class="text-muted mb-5">Cuando publiques el primer post, podrás abrir cada destino directamente desde su tarjeta.</p>
                        <a href="{{ route('admin.ai-articles.index') }}" class="btn btn-primary">Elegir un artículo</a>
                    </div>
                </div>
            @endif
        </section>
    </div>
@endsection

@push('styles')
    <style>
        .ops-dashboard {
            --ops-purple: #7756ff;
            --ops-blue: #2f80ed;
            --ops-green: #20b486;
            --ops-red: #ef5a67;
            padding: 0 20px 8px;
        }

        .ops-live-badge {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .38rem .7rem;
            border-radius: 999px;
            background: #eafaf4;
            color: #168767;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .ops-live-badge span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #20b486;
            box-shadow: 0 0 0 4px rgba(32, 180, 134, .13);
        }

        .ops-status-strip {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.15rem 1.35rem;
            border: 1px solid;
            border-radius: 16px;
        }

        .ops-status-strip-success {
            border-color: #ccefe3;
            background: linear-gradient(90deg, #effbf7 0%, #fff 75%);
        }

        .ops-status-strip-warning {
            border-color: #f7dfaa;
            background: linear-gradient(90deg, #fff8e9 0%, #fff 75%);
        }

        .ops-status-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 12px;
            background: #fff;
            font-size: 1.45rem;
            box-shadow: 0 6px 20px rgba(31, 42, 68, .08);
        }

        .ops-status-strip-success .ops-status-icon { color: var(--ops-green); }
        .ops-status-strip-warning .ops-status-icon { color: #e9a817; }

        .ops-kpi-card {
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
            box-shadow: 0 8px 30px rgba(31, 42, 68, .055);
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .ops-kpi-card::after {
            content: "";
            position: absolute;
            width: 130px;
            height: 130px;
            top: -72px;
            right: -54px;
            border-radius: 50%;
            background: currentColor;
            opacity: .055;
        }

        .ops-kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 34px rgba(31, 42, 68, .1);
        }

        .ops-kpi-purple { color: var(--ops-purple); border-color: rgba(119, 86, 255, .13); }
        .ops-kpi-blue { color: var(--ops-blue); border-color: rgba(47, 128, 237, .13); }
        .ops-kpi-green { color: var(--ops-green); border-color: rgba(32, 180, 134, .13); }
        .ops-kpi-red { color: var(--ops-red); border-color: rgba(239, 90, 103, .13); }

        .ops-kpi-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: currentColor;
            font-size: 1.55rem;
        }

        .ops-kpi-icon i { color: #fff; }

        .ops-kpi-period {
            padding: .35rem .65rem;
            border-radius: 999px;
            background: #f5f7fa;
            color: #7e8299;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .ops-kpi-value {
            color: #16213b;
            font-size: 2.4rem;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -.04em;
        }

        .ops-kpi-label {
            margin-top: .65rem;
            color: #34405c;
            font-size: .95rem;
            font-weight: 700;
        }

        .ops-kpi-detail {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .5rem;
            min-height: 18px;
            margin-top: 1rem;
            color: #7e8299;
            font-size: .73rem;
            font-weight: 600;
        }

        .ops-kpi-dot {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: #c3c8d4;
        }

        .ops-chart-legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .65rem 1rem;
            color: #7e8299;
            font-size: .72rem;
            font-weight: 600;
        }

        .ops-chart-legend span { display: inline-flex; align-items: center; gap: .4rem; }
        .ops-chart-legend i { width: 8px; height: 8px; border-radius: 50%; }
        .ops-legend-purple { background: var(--ops-purple); }
        .ops-legend-blue { background: var(--ops-blue); }
        .ops-legend-green { background: var(--ops-green); }

        .ops-chart {
            display: grid;
            grid-template-columns: repeat(7, minmax(58px, 1fr));
            align-items: end;
            gap: .5rem;
            min-height: 175px;
            padding: 1rem .5rem 0;
            overflow-x: auto;
            border-bottom: 1px solid #edf0f5;
        }

        .ops-chart-day {
            position: relative;
            min-width: 58px;
            text-align: center;
        }

        .ops-chart-bars {
            height: 98px;
            display: flex;
            align-items: end;
            justify-content: center;
            gap: 4px;
        }

        .ops-bar {
            width: 9px;
            min-height: 5px;
            border-radius: 6px 6px 2px 2px;
            transition: opacity .2s ease;
        }

        .ops-chart-day:hover .ops-bar { opacity: .72; }
        .ops-bar-purple { background: linear-gradient(180deg, #937cff 0%, var(--ops-purple) 100%); }
        .ops-bar-blue { background: linear-gradient(180deg, #69a9ff 0%, var(--ops-blue) 100%); }
        .ops-bar-green { background: linear-gradient(180deg, #52d6ac 0%, var(--ops-green) 100%); }

        .ops-chart-values {
            margin-top: .5rem;
            color: #a1a5b7;
            font-size: .62rem;
            white-space: nowrap;
        }

        .ops-chart-label {
            margin-top: .35rem;
            color: #34405c;
            font-size: .74rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .ops-chart-date {
            padding-bottom: .7rem;
            color: #a1a5b7;
            font-size: .67rem;
        }

        .ops-chart-error {
            position: absolute;
            top: -7px;
            right: 3px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 9px;
            background: #fff0f1;
            color: var(--ops-red);
            font-size: .62rem;
            font-weight: 800;
        }

        .ops-health-row {
            display: flex;
            align-items: center;
            gap: .9rem;
            padding: .85rem 0;
            text-decoration: none;
            border-bottom: 1px solid #f0f1f5;
        }

        .ops-health-row:last-child { border-bottom: 0; }
        .ops-health-row:hover .ops-health-label { color: var(--ops-blue); }

        .ops-health-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            border-radius: 12px;
            font-size: 1.25rem;
        }

        .ops-health-label,
        .ops-health-meta { display: block; }
        .ops-health-label { color: #34405c; font-size: .78rem; font-weight: 700; }
        .ops-health-meta { color: #a1a5b7; font-size: .68rem; margin-top: .2rem; }
        .ops-health-row strong { color: #16213b; font-size: 1.18rem; }

        .ops-error-row,
        .ops-activity-row {
            display: flex;
            align-items: center;
            gap: .85rem;
            min-height: 61px;
            padding: .75rem 0;
            text-decoration: none;
            border-bottom: 1px solid #f0f1f5;
        }

        .ops-error-row:last-child,
        .ops-activity-row:last-child { border-bottom: 0; }

        .ops-error-row:hover,
        .ops-activity-row:hover { background: linear-gradient(90deg, transparent, #fafbfc, transparent); }

        .ops-error-mark {
            width: 8px;
            height: 42px;
            flex: 0 0 8px;
            border-radius: 6px;
            background: linear-gradient(180deg, #ff8c95 0%, var(--ops-red) 100%);
        }

        .ops-activity-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            border-radius: 50%;
            font-size: 1rem;
        }

        .ops-empty-state,
        .ops-posts-empty {
            padding: 2.5rem 1rem;
            text-align: center;
        }

        .ops-empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 58px;
            height: 58px;
            border-radius: 18px;
            font-size: 1.8rem;
        }

        .ops-section-eyebrow {
            color: var(--ops-blue);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .ops-post-card {
            overflow: hidden;
            border: 1px solid #e9ebf1;
            box-shadow: 0 10px 32px rgba(31, 42, 68, .06);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .ops-post-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 42px rgba(31, 42, 68, .12);
        }

        .ops-post-media {
            position: relative;
            aspect-ratio: 1.55 / 1;
            overflow: hidden;
            background: #edf2f8;
        }

        .ops-post-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .35s ease;
        }

        .ops-post-card:hover .ops-post-media img { transform: scale(1.035); }

        .ops-post-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            gap: .7rem;
            background:
                radial-gradient(circle at 15% 20%, rgba(119, 86, 255, .2), transparent 34%),
                radial-gradient(circle at 85% 80%, rgba(47, 128, 237, .22), transparent 34%),
                linear-gradient(135deg, #263756, #1b253d);
            color: rgba(255, 255, 255, .9);
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .ops-post-placeholder i { font-size: 2.4rem; color: #fff; }

        .ops-post-overlay {
            position: absolute;
            inset: auto 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 2.5rem 1rem .9rem;
            background: linear-gradient(transparent, rgba(13, 22, 39, .82));
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
        }

        .ops-post-overlay span { display: inline-flex; align-items: center; gap: .35rem; }
        .ops-post-overlay time { color: rgba(255, 255, 255, .75); font-weight: 500; }

        .ops-destination-avatars { display: flex; align-items: center; }

        .ops-destination-avatars span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            margin-right: -7px;
            border: 3px solid #fff;
            border-radius: 50%;
            color: #fff;
            font-size: .9rem;
        }

        .ops-destination-avatars .is-facebook { background: #1877f2; }
        .ops-destination-avatars .is-wordpress { background: #28799e; }
        .ops-destination-avatars .is-more { background: #eef1f6; color: #667085; font-size: .65rem; font-weight: 800; }

        .ops-post-title {
            display: -webkit-box;
            overflow: hidden;
            min-height: 3.4rem;
            font-size: 1.08rem;
            line-height: 1.55;
            font-weight: 800;
            text-decoration: none;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .ops-post-excerpt {
            display: -webkit-box;
            overflow: hidden;
            min-height: 3.9rem;
            margin: .75rem 0 1rem;
            color: #7e8299;
            font-size: .78rem;
            line-height: 1.65;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
        }

        .ops-post-tags {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            margin-bottom: 1rem;
        }

        .ops-post-tags span {
            padding: .3rem .55rem;
            border-radius: 999px;
            background: #f0f5ff;
            color: #4775b8;
            font-size: .65rem;
            font-weight: 700;
        }

        .ops-post-destinations {
            padding-top: 1rem;
            border-top: 1px solid #edf0f5;
        }

        .ops-post-destinations a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .45rem 0;
            color: #4b5675;
            font-size: .72rem;
            font-weight: 700;
            text-decoration: none;
        }

        .ops-post-destinations a:hover { color: var(--ops-blue); }
        .ops-post-destinations i { flex: 0 0 auto; font-size: .95rem; }

        html[data-bs-theme="dark"] .ops-status-strip-success,
        html[data-bs-theme="dark"] .ops-status-strip-warning {
            background: var(--bs-gray-200);
            border-color: var(--bs-gray-300);
        }

        html[data-bs-theme="dark"] .ops-kpi-value,
        html[data-bs-theme="dark"] .ops-health-row strong,
        html[data-bs-theme="dark"] .ops-kpi-label,
        html[data-bs-theme="dark"] .ops-health-label {
            color: var(--bs-gray-900);
        }

        html[data-bs-theme="dark"] .ops-chart,
        html[data-bs-theme="dark"] .ops-health-row,
        html[data-bs-theme="dark"] .ops-error-row,
        html[data-bs-theme="dark"] .ops-activity-row,
        html[data-bs-theme="dark"] .ops-post-destinations {
            border-color: var(--bs-gray-300);
        }

        html[data-bs-theme="dark"] .ops-destination-avatars span { border-color: var(--bs-body-bg); }

        @media (max-width: 767.98px) {
            .ops-dashboard { padding-right: 16px; padding-left: 16px; }
            .ops-status-strip { align-items: flex-start; flex-wrap: wrap; }
            .ops-status-strip .btn { width: 100%; }
            .ops-chart-legend { justify-content: flex-start; }
            .ops-kpi-value { font-size: 2rem; }
        }
    </style>
@endpush
