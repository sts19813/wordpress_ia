@php
    $helpArticle = \App\Support\HelpCenter::contextualArticleForRoute(request()->route()?->getName());
@endphp

@if ($helpArticle && ! request()->routeIs('admin.help.*'))
    <div class="d-flex justify-content-end mb-5">
        <a href="{{ route('admin.help.show', $helpArticle['slug']) }}" class="btn btn-sm btn-light-primary">
            <i class="ki-outline ki-question-2 fs-4"></i>Ayuda de esta página
        </a>
    </div>
@endif
