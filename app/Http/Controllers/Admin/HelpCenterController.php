<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpArticleView;
use App\Models\HelpFeedback;
use App\Models\HelpSearch;
use App\Support\HelpCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpCenterController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->string('q'));
        $selectedCategory = $request->string('categoria')->toString();
        $categories = HelpCenter::categories()->map(function (array $category): array {
            $category['article_count'] = HelpCenter::articles()
                ->where('category', $category['slug'])
                ->count();

            return $category;
        });

        $articles = HelpCenter::search($term, $selectedCategory);

        if ($term !== '') {
            HelpSearch::query()->create([
                'user_id' => $request->user()?->id,
                'term' => $term,
                'results_count' => $articles->count(),
            ]);
        }

        return view('admin.help.index', [
            'categories' => $categories,
            'articles' => $articles,
            'workflows' => HelpCenter::workflows(),
            'term' => $term,
            'selectedCategory' => $selectedCategory,
        ]);
    }

    public function show(Request $request, string $article): View
    {
        abort_unless($helpArticle = HelpCenter::article($article), 404);

        HelpArticleView::query()->create([
            'user_id' => $request->user()?->id,
            'article_slug' => $helpArticle['slug'],
        ]);

        $relatedArticles = HelpCenter::articles()
            ->where('category', $helpArticle['category'])
            ->where('slug', '!=', $helpArticle['slug'])
            ->take(3)
            ->values();

        return view('admin.help.show', compact('helpArticle', 'relatedArticles'));
    }

    public function feedback(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'article_slug' => ['nullable', 'string', 'max:120'],
            'rating' => ['nullable', 'in:'.HelpFeedback::RATING_HELPFUL.','.HelpFeedback::RATING_NOT_HELPFUL],
            'search_term' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_if(
            filled($validated['article_slug'] ?? null) && ! HelpCenter::article($validated['article_slug']),
            404,
        );

        HelpFeedback::query()->create([
            'user_id' => $request->user()?->id,
            'article_slug' => $validated['article_slug'] ?? null,
            'rating' => $validated['rating'] ?? null,
            'search_term' => $validated['search_term'] ?? null,
            'message' => filled($validated['message'] ?? null) ? trim($validated['message']) : null,
        ]);

        return back()->with('status', 'Gracias. Tu comentario nos ayudará a mejorar esta ayuda.');
    }

    public function analytics(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $since = now()->subDays(30);
        $feedback = HelpFeedback::query()->where('created_at', '>=', $since);
        $helpful = (clone $feedback)->where('rating', HelpFeedback::RATING_HELPFUL)->count();
        $notHelpful = (clone $feedback)->where('rating', HelpFeedback::RATING_NOT_HELPFUL)->count();
        $rated = $helpful + $notHelpful;

        return view('admin.help.analytics', [
            'since' => $since,
            'feedbackTotal' => (clone $feedback)->count(),
            'helpful' => $helpful,
            'notHelpful' => $notHelpful,
            'satisfaction' => $rated ? (int) round(($helpful / $rated) * 100) : null,
            'emptySearches' => HelpSearch::query()
                ->where('created_at', '>=', $since)
                ->where('results_count', 0)
                ->count(),
            'topSearches' => HelpSearch::query()
                ->where('created_at', '>=', $since)
                ->select('term')
                ->selectRaw('count(*) as searches')
                ->selectRaw('sum(case when results_count = 0 then 1 else 0 end) as empty_searches')
                ->groupBy('term')
                ->orderByDesc('searches')
                ->limit(12)
                ->get(),
            'topArticles' => HelpArticleView::query()
                ->where('created_at', '>=', $since)
                ->select('article_slug')
                ->selectRaw('count(*) as views')
                ->groupBy('article_slug')
                ->orderByDesc('views')
                ->limit(12)
                ->get()
                ->map(function (HelpArticleView $view): array {
                    return [
                        'title' => HelpCenter::article($view->article_slug)['title'] ?? $view->article_slug,
                        'slug' => $view->article_slug,
                        'views' => $view->views,
                    ];
                }),
            'comments' => HelpFeedback::query()
                ->with('user:id,name')
                ->whereNotNull('message')
                ->where('created_at', '>=', $since)
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }
}
