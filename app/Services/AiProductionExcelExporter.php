<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AiProductionExcelExporter
{
    /** @param array<string, mixed> $report */
    public function make(array $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle('Resumen de producción IA')
            ->setSubject('Notas generadas y publicadas');

        $this->summarySheet($spreadsheet->getActiveSheet(), $report);
        $this->articlesSheet($spreadsheet->createSheet(), $report['articles']);
        $this->destinationsSheet($spreadsheet->createSheet(), $report['destinations']);
        $this->failuresSheet($spreadsheet->createSheet(), $report['failures']);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** @param array<string, mixed> $report */
    private function summarySheet(Worksheet $sheet, array $report): void
    {
        $sheet->setTitle('Resumen diario');
        $sheet->mergeCells('A1:F1');
        $this->setText($sheet, 'A1', 'Resumen de producción de notas con IA');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF075FD1');
        $sheet->getRowDimension(1)->setRowHeight(28);

        $company = $report['companies']->firstWhere('id', $report['filters']['company_id'])?->name ?? 'Todas';
        $status = match ($report['filters']['publication_status']) {
            'published' => 'Sólo publicadas',
            'unpublished' => 'Sólo sin publicar',
            default => 'Todas',
        };
        $this->writeRow($sheet, 3, ['Desde', $report['filters']['date_from'], 'Hasta', $report['filters']['date_to']]);
        $this->writeRow($sheet, 4, ['Empresa', $company, 'Estado', $status]);

        $this->writeRow($sheet, 6, ['Métrica', 'Valor']);
        $metrics = [
            ['Notas generadas con IA', $report['summary']['generated']],
            ['Notas publicadas', $report['summary']['published']],
            ['Notas sin publicar', $report['summary']['unpublished']],
            ['Efectividad de publicación', $report['summary']['publication_rate'] === null ? 'Sin muestra' : $report['summary']['publication_rate'].'%'],
            ['Envíos exitosos', $report['summary']['publication_sends']],
            ['Envíos con perfil eliminado', $report['summary']['historical_destination_sends']],
            ['Intentos de generación fallidos', $report['summary']['failed_generations']],
        ];
        $row = 7;
        foreach ($metrics as $metric) {
            $this->writeRow($sheet, $row++, $metric);
        }
        $this->header($sheet, 'A6:B6');

        $row += 2;
        $headerRow = $row;
        $this->writeRow($sheet, $row++, ['Fecha', 'Generadas', 'Publicadas', 'Sin publicar', 'Envíos exitosos', 'Fallos de generación']);
        foreach ($report['daily'] as $day) {
            $this->writeRow($sheet, $row++, [
                $day['date']->format('d/m/Y'),
                $day['generated'],
                $day['published'],
                $day['unpublished'],
                $day['publication_sends'],
                $day['failed'],
            ]);
        }

        $this->table($sheet, $headerRow, $row - 1, 6);
        $sheet->freezePane('A'.($headerRow + 1));
        $this->widths($sheet, [22, 18, 18, 18, 20, 22]);
    }

    /** @param Collection<int, array<string, mixed>> $articles */
    private function articlesSheet(Worksheet $sheet, Collection $articles): void
    {
        $sheet->setTitle('Notas');
        $headers = ['Fecha', 'Hora', 'ID', 'Nota', 'Empresa', 'Estado', 'Envíos', 'Perfiles vigentes', 'Destinos', 'URLs', 'Modelo', 'Costo USD'];
        $this->writeRow($sheet, 1, $headers);

        $row = 2;
        foreach ($articles as $article) {
            $destinations = $article['destinations']->map(fn (array $destination) => implode(' · ', array_filter([
                $destination['type_label'],
                $destination['name'],
                $destination['company'],
            ])))->unique()->join(' | ');
            $urls = $article['destinations']->pluck('url')->filter()->unique()->join(' | ');

            $this->writeRow($sheet, $row++, [
                $article['generated_at']->format('d/m/Y'),
                $article['generated_at']->format('H:i'),
                $article['id'],
                $article['title'],
                $article['company'] ?: 'Sin empresa asignada',
                $article['published'] ? 'Publicada' : 'Sin publicar',
                $article['publication_count'],
                $article['current_destination_count'],
                $destinations ?: '—',
                $urls ?: '—',
                $article['model'] ?: '—',
                $article['cost'],
            ]);
        }

        $this->table($sheet, 1, max(1, $row - 1), count($headers));
        $sheet->freezePane('A2');
        $sheet->getStyle('D2:J'.max(2, $row - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $this->widths($sheet, [13, 9, 9, 52, 24, 15, 11, 17, 58, 52, 20, 14]);
    }

    /** @param Collection<int, array<string, mixed>> $destinations */
    private function destinationsSheet(Worksheet $sheet, Collection $destinations): void
    {
        $sheet->setTitle('Destinos');
        $headers = ['Perfil', 'Tipo', 'Empresa', 'Notas únicas', 'Envíos', 'Perfil vigente', 'URL de ejemplo'];
        $this->writeRow($sheet, 1, $headers);

        $row = 2;
        foreach ($destinations as $destination) {
            $this->writeRow($sheet, $row++, [
                $destination['name'],
                $destination['type_label'],
                $destination['company'] ?: '—',
                $destination['article_count'],
                $destination['publication_count'],
                $destination['historical'] ? 'No' : 'Sí',
                $destination['url'] ?: '—',
            ]);
        }

        $this->table($sheet, 1, max(1, $row - 1), count($headers));
        $sheet->freezePane('A2');
        $this->widths($sheet, [38, 20, 28, 16, 12, 16, 52]);
    }

    /** @param Collection<int, mixed> $failures */
    private function failuresSheet(Worksheet $sheet, Collection $failures): void
    {
        $sheet->setTitle('Fallos');
        $headers = ['Fecha', 'Hora', 'ID', 'Nota', 'Modelo', 'Motivo'];
        $this->writeRow($sheet, 1, $headers);

        $row = 2;
        foreach ($failures as $failure) {
            $this->writeRow($sheet, $row++, [
                $failure->created_at->format('d/m/Y'),
                $failure->created_at->format('H:i'),
                $failure->id,
                $failure->title ?: 'Generación sin título #'.$failure->id,
                $failure->model ?: '—',
                $failure->generation_error ?: 'Sin detalle',
            ]);
        }

        $this->table($sheet, 1, max(1, $row - 1), count($headers));
        $sheet->freezePane('A2');
        $sheet->getStyle('D2:F'.max(2, $row - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $this->widths($sheet, [13, 9, 9, 48, 22, 72]);
    }

    /** @param list<mixed> $values */
    private function writeRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach (array_values($values) as $index => $value) {
            $cell = Coordinate::stringFromColumnIndex($index + 1).$row;
            if (is_int($value) || is_float($value)) {
                $sheet->setCellValue($cell, $value);
            } else {
                $this->setText($sheet, $cell, (string) ($value ?? ''));
            }
        }
    }

    private function setText(Worksheet $sheet, string $cell, string $value): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
    }

    private function table(Worksheet $sheet, int $headerRow, int $lastRow, int $lastColumn): void
    {
        $lastCell = Coordinate::stringFromColumnIndex($lastColumn).$lastRow;
        $headerRange = 'A'.$headerRow.':'.Coordinate::stringFromColumnIndex($lastColumn).$headerRow;
        $this->header($sheet, $headerRange);

        if ($lastRow > $headerRow) {
            $sheet->setAutoFilter('A'.$headerRow.':'.$lastCell);
            $sheet->getStyle('A'.$headerRow.':'.$lastCell)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_HAIR)
                ->getColor()->setARGB('FFD8DEE9');
        }
    }

    private function header(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2234');
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    /** @param list<int> $widths */
    private function widths(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }
    }
}
