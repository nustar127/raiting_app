<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class XlsxExporter implements ReportExporter
{
    public function export(array $data, string $reportType, string $filename): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $rowNum = 1;

        match ($reportType) {
            'rating_list' => $this->fillRatingList($sheet, $data, $rowNum),
            'student_card' => $this->fillStudentCard($sheet, $data, $rowNum),
            'period_summary' => $this->fillPeriodSummary($sheet, $data, $rowNum),
            default => null,
        };

        // Автоширина
        foreach ($sheet->getColumnDimensions() as $col) {
            $sheet->getColumnDimension($col->getColumnIndex())->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}.xlsx\"",
        ]);
    }

    private function fillRatingList($sheet, array $data, int &$r): void
    {
        $sheet->setCellValue("A{$r}", 'Рейтинговый список');
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(14);
        $r++;

        $sheet->setCellValue("A{$r}", 'Период');
        $sheet->setCellValue("B{$r}", $data['period']['name'] ?? '');
        $r++;

        $sheet->setCellValue("A{$r}", 'Сформирован');
        $sheet->setCellValue("B{$r}", $data['generated_at'] ?? '');
        $r += 2;

        $headers = ['Место', 'ФИО', 'Номер зачётки', 'Группа', 'Совокупный рейтинг', 'Дисциплин'];
        $this->writeHeaders($sheet, $r, $headers);

        foreach ($data['items'] ?? [] as $item) {
            $r++;
            $sheet->setCellValue("A{$r}", $item['rank']);
            $sheet->setCellValue("B{$r}", $item['full_name']);
            $sheet->setCellValue("C{$r}", $item['record_book_number']);
            $sheet->setCellValue("D{$r}", $item['group_name']);
            $sheet->setCellValue("E{$r}", $item['cumulative_value']);
            $sheet->setCellValue("F{$r}", $item['disciplines_count']);
        }
    }

    private function fillStudentCard($sheet, array $data, int &$r): void
    {
        $s = $data['student'] ?? [];

        $sheet->setCellValue("A{$r}", 'Карточка студента');
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(14);
        $r++;

        $meta = [
            ['ФИО', $s['full_name'] ?? ''],
            ['Номер зачётки', $s['record_book_number'] ?? ''],
            ['Группа', $s['group']['name'] ?? ''],
            ['Факультет', $s['faculty']['short_name'] ?? ''],
            ['Период', $data['period']['name'] ?? ''],
            ['Совокупный рейтинг', $data['cumulative_rating']],
        ];
        foreach ($meta as $m) {
            $sheet->setCellValue("A{$r}", $m[0]);
            $sheet->setCellValue("B{$r}", $m[1]);
            $r++;
        }
        $r++;

        $this->writeHeaders($sheet, $r, ['Дисциплина', 'Шифр', 'Итоговый рейтинг', 'Допущен']);
        foreach ($data['disciplines'] ?? [] as $d) {
            $r++;
            $sheet->setCellValue("A{$r}", $d['discipline_name']);
            $sheet->setCellValue("B{$r}", $d['discipline_code']);
            $sheet->setCellValue("C{$r}", $d['final_rating']);
            $sheet->setCellValue("D{$r}", $d['is_admitted'] ? 'да' : 'нет');
        }
        $r += 2;

        $this->writeHeaders($sheet, $r, ['Модуль', 'Порядковый номер', 'Модульный рейтинг']);
        foreach ($data['disciplines'] ?? [] as $d) {
            foreach ($d['modules'] ?? [] as $m) {
                $r++;
                $sheet->setCellValue("A{$r}", $m['module_name']);
                $sheet->setCellValue("B{$r}", $m['order_number']);
                $sheet->setCellValue("C{$r}", $m['module_rating']);
            }
        }
    }

    private function fillPeriodSummary($sheet, array $data, int &$r): void
    {
        $sheet->setCellValue("A{$r}", 'Сводка по периоду');
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(14);
        $r++;

        $meta = [
            ['Период', $data['period']['name'] ?? ''],
            ['Студентов', $data['totals']['students_count'] ?? 0],
            ['Средний рейтинг', $data['totals']['avg_rating'] ?? 0],
            ['Максимум', $data['totals']['max_rating'] ?? 0],
            ['Минимум', $data['totals']['min_rating'] ?? 0],
        ];
        foreach ($meta as $m) {
            $sheet->setCellValue("A{$r}", $m[0]);
            $sheet->setCellValue("B{$r}", $m[1]);
            $r++;
        }
        $r++;

        $this->writeHeaders($sheet, $r, ['Диапазон', 'Студентов']);
        foreach ($data['distribution'] ?? [] as $bucket => $count) {
            $r++;
            $sheet->setCellValue("A{$r}", $bucket);
            $sheet->setCellValue("B{$r}", $count);
        }
        $r += 2;

        $this->writeHeaders($sheet, $r, ['Группа', 'Студентов', 'Средний рейтинг']);
        foreach ($data['groups'] ?? [] as $g) {
            $r++;
            $sheet->setCellValue("A{$r}", $g['group_name']);
            $sheet->setCellValue("B{$r}", $g['students_count']);
            $sheet->setCellValue("C{$r}", $g['avg_rating']);
        }
        $r += 2;

        $this->writeHeaders($sheet, $r, ['Дисциплина', 'Шифр', 'Студентов', 'Средний рейтинг']);
        foreach ($data['disciplines'] ?? [] as $d) {
            $r++;
            $sheet->setCellValue("A{$r}", $d['discipline_name']);
            $sheet->setCellValue("B{$r}", $d['discipline_code']);
            $sheet->setCellValue("C{$r}", $d['students_count']);
            $sheet->setCellValue("D{$r}", $d['avg_rating']);
        }
    }

    private function writeHeaders($sheet, int $row, array $headers): void
    {
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue("{$col}{$row}", $h);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true);
            $col++;
        }
    }
}
