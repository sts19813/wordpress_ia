<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Contracts\View\View;

class SystemLogController extends Controller
{
    public function __invoke(): View
    {
        $logs = SystemLog::query()
            ->where(fn ($query) => $query
                ->where('level', SystemLog::LEVEL_ERROR)
                ->orWhere('event', SystemLog::EVENT_PUBLICATION_PUBLISHED))
            ->latest('occurred_at')
            ->get();

        return view('admin.system-logs.index', [
            'logs' => $logs,
        ]);
    }
}
