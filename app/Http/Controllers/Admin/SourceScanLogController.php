<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SourceScanLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SourceScanLogController extends Controller
{
    private const MAXIMUM_VISIBLE_LOGS = 200;

    public function __invoke(): View
    {
        return view('admin.source-scan-logs.index', [
            'logs' => SourceScanLog::query()
                ->with(['sourceSite:id,name', 'sourcePost:id,title'])
                ->latest('scanned_at')
                ->limit(self::MAXIMUM_VISIBLE_LOGS)
                ->get(),
            'logCount' => SourceScanLog::query()->count(),
            'maximumVisibleLogs' => self::MAXIMUM_VISIBLE_LOGS,
        ]);
    }

    public function destroy(): RedirectResponse
    {
        $deletedLogs = SourceScanLog::query()->delete();

        return redirect()
            ->route('admin.source-scan-logs.index')
            ->with('status', $deletedLogs === 1
                ? 'Se eliminó 1 registro de la bitácora.'
                : "Se eliminaron {$deletedLogs} registros de la bitácora.");
    }
}
