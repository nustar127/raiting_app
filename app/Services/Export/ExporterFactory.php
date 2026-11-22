<?php

namespace App\Services\Export;

use InvalidArgumentException;

class ExporterFactory
{
    public function make(string $format): ReportExporter|InvalidArgumentException
    {
        return match ($format) {
            'csv' => app(CsvExporter::class),
            'xlsx' => app(XlsxExporter::class),
            'pdf' => app(PdfExporter::class),
            default => throw new InvalidArgumentException("Неподдерживаемый формат: {$format}"),
        };
    }
}
