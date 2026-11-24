<?php

namespace App\Services\Export;

use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter implements ReportExporter
{
    public function export(array $data, string $reportType, string $filename): Response
    {
        $rows = $this->flatten($data, $reportType);

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');

            // BOM для корректного открытия в Excel
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                // Точка с запятой — стандарт для русского Excel
                fputcsv($out, $row, ';', '"', '\\');

                // fputcsv не поддерживает ; с экранированием, но для наших данных ок
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    /**
     * Преобразует структуру отчёта в плоские строки CSV.
     */
    private function flatten(array $data, string $reportType): array
    {
        return match ($reportType) {
            'rating_list' => $this->flattenRatingList($data),
            'student_card' => $this->flattenStudentCard($data),
            'period_summary' => $this->flattenPeriodSummary($data),
            default => [],
        };
    }

    private function flattenRatingList(array $data): array
    {
        $rows = [
            ['Рейтинговый список'],
            ['Период', $data['period']['name'] ?? ''],
            ['Сформирован', $data['generated_at'] ?? ''],
            [],
            ['Место', 'ФИО', 'Номер зачётки', 'Группа', 'Совокупный рейтинг', 'Дисциплин'],
        ];

        foreach ($data['items'] ?? [] as $item) {
            $rows[] = [
                $item['rank'],
                $item['full_name'],
                $item['record_book_number'],
                $item['group_name'],
                number_format($item['cumulative_value'], 2, ',', ''),
                $item['disciplines_count'],
            ];
        }

        return $rows;
    }

    private function flattenStudentCard(array $data): array
    {
        $s = $data['student'] ?? [];

        $rows = [
            ['Карточка студента'],
            ['ФИО', $s['full_name'] ?? ''],
            ['Номер зачётки', $s['record_book_number'] ?? ''],
            ['Группа', $s['group']['name'] ?? ''],
            ['Факультет', $s['faculty']['short_name'] ?? ''],
            ['Период', $data['period']['name'] ?? ''],
            ['Совокупный рейтинг', $data['cumulative_rating'] !== null ? number_format($data['cumulative_rating'], 2, ',', '') : '—'],
            [],
            ['Дисциплина', 'Шифр', 'Итоговый рейтинг', 'Допущен'],
        ];

        foreach ($data['disciplines'] ?? [] as $d) {
            $rows[] = [
                $d['discipline_name'],
                $d['discipline_code'],
                number_format($d['final_rating'], 2, ',', ''),
                $d['is_admitted'] ? 'да' : 'нет',
            ];
        }

        $rows[] = [];
        $rows[] = ['Модуль', 'Порядковый номер', 'Модульный рейтинг'];
        foreach ($data['disciplines'] ?? [] as $d) {
            foreach ($d['modules'] ?? [] as $m) {
                $rows[] = [
                    $m['module_name'],
                    $m['order_number'],
                    number_format($m['module_rating'], 2, ',', ''),
                ];
            }
        }

        return $rows;
    }

    private function flattenPeriodSummary(array $data): array
    {
        $rows = [
            ['Сводка по периоду'],
            ['Период', $data['period']['name'] ?? ''],
            ['Студентов', $data['totals']['students_count'] ?? 0],
            ['Средний рейтинг', number_format($data['totals']['avg_rating'] ?? 0, 2, ',', '')],
            ['Максимум', number_format($data['totals']['max_rating'] ?? 0, 2, ',', '')],
            ['Минимум', number_format($data['totals']['min_rating'] ?? 0, 2, ',', '')],
            [],
            ['Диапазон', 'Студентов'],
        ];

        foreach ($data['distribution'] ?? [] as $bucket => $count) {
            $rows[] = [$bucket, $count];
        }

        $rows[] = [];
        $rows[] = ['Группа', 'Студентов', 'Средний рейтинг'];
        foreach ($data['groups'] ?? [] as $g) {
            $rows[] = [
                $g['group_name'],
                $g['students_count'],
                number_format($g['avg_rating'], 2, ',', ''),
            ];
        }

        $rows[] = [];
        $rows[] = ['Дисциплина', 'Шифр', 'Студентов', 'Средний рейтинг'];
        foreach ($data['disciplines'] ?? [] as $d) {
            $rows[] = [
                $d['discipline_name'],
                $d['discipline_code'],
                $d['students_count'],
                number_format($d['avg_rating'], 2, ',', ''),
            ];
        }

        return $rows;
    }
}
