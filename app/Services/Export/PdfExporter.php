<?php

namespace App\Services\Export;

use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfExporter implements ReportExporter
{
    public function export(array $data, string $reportType, string $filename): Response
    {
        $view = match ($reportType) {
            'rating_list' => 'reports.pdf.rating-list',
            'student_card' => 'reports.pdf.student-card',
            'period_summary' => 'reports.pdf.period-summary',
            default => throw new \InvalidArgumentException("Неизвестный тип отчёта: {$reportType}"),
        };

        $html = view($view, ['data' => $data])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'default_font' => 'dejavusans',
            'default_font_size' => 10,
            'tempDir' => storage_path('app/mpdf'),
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 14,
        ]);

        $mpdf->WriteHTML($html);

        return new StreamedResponse(function () use ($mpdf, $filename) {
            $mpdf->Output("{$filename}.pdf", \Mpdf\Output\Destination::DOWNLOAD);
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
        ]);
    }
}
