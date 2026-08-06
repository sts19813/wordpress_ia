<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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
            'logCount' => SystemLog::query()->count(),
        ]);
    }

    public function destroy(): RedirectResponse
    {
        $deletedLogs = SystemLog::query()->delete();

        return redirect()
            ->route('admin.system-logs.index')
            ->with('status', $deletedLogs === 1
                ? 'Se eliminó 1 registro del log del sistema.'
                : "Se eliminaron {$deletedLogs} registros del log del sistema.");
    }
}
