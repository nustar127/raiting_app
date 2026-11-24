<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class SpreadsheetReader
{
    /**
     * Читает файл и возвращает массив ассоциативных массивов (заголовок → значение).
     *
     * @return array<int, array<string, string|null>>
     */
    public function read(string $filePath, ?string $originalName = null): array
    {
        $ext = strtolower(pathinfo($originalName ?? $filePath, PATHINFO_EXTENSION));

        // Иногда расширение пустое или tmp — определяем по MIME
        if (!in_array($ext, ['csv', 'xlsx', 'xls', 'txt'], true)) {
            $mime = mime_content_type($filePath) ?: '';
            if (str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel')) {
                return $this->readXlsx($filePath);
            }
            // По умолчанию — CSV
            return $this->readCsv($filePath);
        }

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->readXlsx($filePath);
        }

        return $this->readCsv($filePath);
    }

    private function readCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException('Не удалось открыть файл.');
        }

        // Убираем BOM
        $firstLine = fgets($handle);
        if ($firstLine !== false) {
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
            rewind($handle);
            $headers = str_getcsv($firstLine, ';');
            // Убираем BOM из первого заголовка ещё раз, если есть
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        } else {
            fclose($handle);
            return [];
        }

        // Пропускаем заголовок
        fgetcsv($handle, 0, ';');

        $rows = [];
        $rowNum = 1;
        while (($line = fgetcsv($handle, 0, ';')) !== false) {
            $rowNum++;
            if (count($line) === 1 && trim($line[0]) === '') {
                continue; // пропускаем пустые строки
            }
            $row = ['_row' => $rowNum];
            foreach ($headers as $i => $h) {
                $row[trim($h)] = isset($line[$i]) ? trim($line[$i]) : null;
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);

        if (empty($data)) {
            return [];
        }

        $headers = array_map(fn($h) => trim((string) $h), $data[0]);
        $rows = [];

        foreach (array_slice($data, 1) as $idx => $row) {
            // Пропускаем полностью пустые строки
            $nonEmpty = array_filter($row, fn($v) => $v !== null && trim((string) $v) !== '');
            if (empty($nonEmpty)) {
                continue;
            }

            $assoc = ['_row' => $idx + 2];
            foreach ($headers as $i => $h) {
                $assoc[$h] = isset($row[$i]) ? trim((string) $row[$i]) : null;
            }
            $rows[] = $assoc;
        }

        return $rows;
    }
}
