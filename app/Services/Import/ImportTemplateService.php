<?php

namespace App\Services\Import;

use Symfony\Component\HttpFoundation\Response;

class ImportTemplateService
{
    public function usersTemplate(string $format): Response
    {
        $headers = ['role', 'last_name', 'first_name', 'middle_name', 'group_name', 'record_book_number', 'department_name', 'faculty_name'];
        $sample = [
            ['student', 'Коростелёва', 'Юлия', 'Александровна', '514371', '100001', '', ''],
            ['teacher', 'Иванов', 'Иван', 'Иванович', '', '', 'ПИКС', ''],
            ['dean', 'Сидорова', 'Анна', 'Александровна', '', '', '', 'ФКП'],
        ];

        return $this->build($format, $headers, $sample, 'users_template');
    }

    public function gradesTemplate(string $format): Response
    {
        $headers = ['record_book_number', 'value', 'weeks_late'];
        $sample = [
            ['100001', '9', '0'],
            ['100002', '8', '0'],
            ['100003', '7', '2'],
        ];

        return $this->build($format, $headers, $sample, 'grades_template');
    }

    private function build(string $format, array $headers, array $sample, string $filename): Response
    {
        if ($format === 'xlsx') {
            return $this->buildXlsx($headers, $sample, $filename);
        }

        return $this->buildCsv($headers, $sample, $filename);
    }

    private function buildCsv(array $headers, array $sample, string $filename): Response
    {
        return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($headers, $sample) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($sample as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    private function buildXlsx(array $headers, array $sample, string $filename): Response
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray($headers, null, 'A1');
        $rowNum = 2;
        foreach ($sample as $row) {
            $sheet->fromArray($row, null, "A{$rowNum}");
            $rowNum++;
        }

        foreach ($sheet->getColumnDimensions() as $col) {
            $sheet->getColumnDimension($col->getColumnIndex())->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}.xlsx\"",
        ]);
    }
}
