@extends('layouts.admin')

@section('title', 'Resumen de ayuda | '.config('app.name'))

@section('toolbar')
    <div class="d-flex align-items-center justify-content-between gap-3 w-100">
        <div>
            <div class="text-primary fw-bold fs-8 mb-1">Centro de ayuda</div>
            <h1 class="page-heading text-gray-900 fw-bold fs-2 my-0">Resumen de ayuda</h1>
            <div class="text-muted fw-semibold fs-7 pt-1">Actividad de los últimos 30 días. Revísala al menos una vez al mes para priorizar mejoras.</div>
        </div>
        <a href="{{ route('admin.help.index') }}" class="btn btn-light-primary">Ver centro de ayuda</a>
    </div>
@endsection

@section('content')
    <div class="row g-5 mb-8">
        <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100"><div class="card-body p-6"><div class="text-muted fs-7 mb-2">Comentarios recibidos</div><div class="fs-2x fw-bold text-gray-900">{{ $feedbackTotal }}</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100"><div class="card-body p-6"><div class="text-muted fs-7 mb-2">Guías útiles</div><div class="fs-2x fw-bold text-success">{{ $helpful }}</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100"><div class="card-body p-6"><div class="text-muted fs-7 mb-2">Necesitan mejora</div><div class="fs-2x fw-bold text-warning">{{ $notHelpful }}</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card card-flush h-100"><div class="card-body p-6"><div class="text-muted fs-7 mb-2">Búsquedas sin resultado</div><div class="fs-2x fw-bold text-danger">{{ $emptySearches }}</div></div></div></div>
    </div>

    <div class="row g-7">
        <div class="col-xl-7">
            <section class="card card-flush h-100">
                <div class="card-header"><div class="card-title"><h2 class="fw-bold fs-4 mb-0">Términos buscados</h2></div></div>
                <div class="card-body pt-0">
                    @if ($topSearches->isNotEmpty())
                        <div class="table-responsive"><table class="table align-middle table-row-dashed gy-4 mb-0"><thead><tr class="text-muted fw-bold fs-8 text-uppercase"><th>Búsqueda</th><th class="text-end">Consultas</th><th class="text-end">Sin resultado</th></tr></thead><tbody>
                            @foreach ($topSearches as $search)
                                <tr><td class="fw-bold text-gray-800">{{ $search->term }}</td><td class="text-end">{{ $search->searches }}</td><td class="text-end">{{ $search->empty_searches }}</td></tr>
                            @endforeach
                        </tbody></table></div>
                    @else
                        <p class="text-muted mb-0">Aún no hay búsquedas registradas.</p>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="card card-flush mb-7">
                <div class="card-header"><div class="card-title"><h2 class="fw-bold fs-4 mb-0">Guías más consultadas</h2></div></div>
                <div class="card-body pt-0">
                    @forelse ($topArticles as $article)
                        <div class="d-flex align-items-center justify-content-between gap-3 border-bottom py-4">
                            <a class="fw-bold text-gray-800 text-hover-primary" href="{{ route('admin.help.show', $article['slug']) }}">{{ $article['title'] }}</a>
                            <span class="badge badge-light-primary">{{ $article['views'] }} consultas</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Aún no hay consultas de guías en este periodo.</p>
                    @endforelse
                </div>
            </section>
            <section class="card card-flush h-100">
                <div class="card-header"><div class="card-title"><h2 class="fw-bold fs-4 mb-0">Comentarios recientes</h2></div></div>
                <div class="card-body pt-0">
                    @forelse ($comments as $comment)
                        <div class="border-bottom pb-4 mb-4">
                            <div class="d-flex justify-content-between gap-3 mb-2"><span class="badge badge-light-{{ $comment->rating === 'helpful' ? 'success' : 'warning' }}">{{ $comment->rating === 'helpful' ? 'Útil' : 'Sugerencia' }}</span><span class="text-muted fs-8">{{ $comment->created_at->format('d/m/Y H:i') }}</span></div>
                            <div class="text-gray-800 fs-7">{{ $comment->message }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Aún no hay comentarios con detalle.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
