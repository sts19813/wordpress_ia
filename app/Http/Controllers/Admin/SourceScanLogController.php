<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SourceScanLog;
use App\Models\SourceSite;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SourceScanLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $logs = SourceScanLog::query()
            ->with(['sourceSite:id,name', 'sourcePost:id,title'])
            ->when($request->string('search')->toString(), function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%");
                });
            })
            ->when($request->integer('source_site_id'), fn (Builder $query, int $id) => $query->where('source_site_id', $id))
            ->when($request->string('outcome')->toString(), fn (Builder $query, string $outcome) => $query->where('outcome', $outcome))
            ->when($request->date('date_from'), fn (Builder $query, mixed $date) => $query->whereDate('scanned_at', '>=', $date))
            ->when($request->date('date_to'), fn (Builder $query, mixed $date) => $query->whereDate('scanned_at', '<=', $date))
            ->latest('scanned_at')
            ->get();

        return view('admin.source-scan-logs.index', [
            'logs' => $logs,
            'sourceSites' => SourceSite::query()->orderBy('name')->pluck('name', 'id'),
            'outcomeOptions' => SourceScanLog::outcomeOptions(),
        ]);
    }
}
