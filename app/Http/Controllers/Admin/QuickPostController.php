<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Scheduler;
use Illuminate\Contracts\View\View;

class QuickPostController extends Controller
{
    public function index(): View
    {
        return view('admin.quick-posts.index', [
            'tasks' => Scheduler::query()
                ->with(['article.images', 'sourcePost.media'])
                ->where('type', Scheduler::TYPE_QUICK_POST)
                ->latest()
                ->get(),
        ]);
    }
}
