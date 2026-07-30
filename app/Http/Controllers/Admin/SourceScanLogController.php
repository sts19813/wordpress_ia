<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SourceScanLog;
use Illuminate\Contracts\View\View;

class SourceScanLogController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.source-scan-logs.index', [
            'logs' => SourceScanLog::query()
                ->with(['sourceSite:id,name', 'sourcePost:id,title'])
                ->latest('scanned_at')
                ->get(),
        ]);
    }
}
