<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiProductionReportRequest;
use App\Services\AiProductionExcelExporter;
use App\Services\AiProductionReportService;
use Illuminate\Contracts\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiProductionReportController extends Controller
{
    public function __construct(
        private readonly AiProductionReportService $reports,
        private readonly AiProductionExcelExporter $excel,
    ) {}

    public function index(AiProductionReportRequest $request): View
    {
        $report = $this->reports->build($this->reports->filters($request->validated()));

        return view('admin.ai-production-report.index', $report);
    }

    public function export(AiProductionReportRequest $request): StreamedResponse
    {
        $report = $this->reports->build($this->reports->filters($request->validated()));
        $spreadsheet = $this->excel->make($report);
        $filename = 'reporte-produccion-ia-'.$report['filters']['date_from'].'-'.$report['filters']['date_to'].'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }
}
