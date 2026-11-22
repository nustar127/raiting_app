<?php

namespace App\Services\Export;

use Symfony\Component\HttpFoundation\Response;

interface ReportExporter
{
    /**
     * @param array $data  Данные отчёта (тот же массив, что уходит в JSON)
     * @param string $reportType  rating_list|student_card|period_summary
     * @param string $filename  Имя файла без расширения
     */
    public function export(array $data, string $reportType, string $filename): Response;
}
