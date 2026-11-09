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
                ->select([
                    'id', 'user_id', 'ai_article_id', 'source_post_id', 'type', 'status',
                    'step', 'progress', 'payload', 'last_error', 'created_at',
                ])
                ->with([
                    'user:id,name',
                    'article:id,title',
                    'article.images:id,ai_article_id,type,status,file_path,mime_type',
                    'article.publications:id,ai_article_id,wordpress_site_id,status,remote_url,error_message',
                    'article.publications.wordpressSite:id,name,type',
                    'sourcePost:id,title',
                ])
                ->where('type', Scheduler::TYPE_QUICK_POST)
                ->latest()
                ->paginate(50),
        ]);
    }
}
