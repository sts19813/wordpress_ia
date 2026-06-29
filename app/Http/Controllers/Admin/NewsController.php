<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SourcePost;
use App\Repositories\SourcePostRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(
        private readonly SourcePostRepository $sourcePosts,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.news.index', [
            'sourcePosts' => $this->sourcePosts->paginateForAdmin($request->query()),
            'filterOptions' => $this->sourcePosts->filterOptions(),
            'statusOptions' => SourcePost::statusOptions(),
        ]);
    }

    public function show(SourcePost $sourcePost): View
    {
        $sourcePost->load('sourceSite:id,name,url');

        return view('admin.news.show', [
            'sourcePost' => $sourcePost,
        ]);
    }
}
