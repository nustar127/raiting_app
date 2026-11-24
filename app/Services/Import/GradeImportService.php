<?php

namespace App\Services\Import;

use App\Models\Grade;
use App\Models\ImportLog;
use App\Models\Module;
use App\Models\RatingPeriod;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\WorkType;
use App\Services\Grade\GradeAccessValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeImportService
{
    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly GradeAccessValidator $accessValidator,
    ) {}

    /**
     * @throws ValidationException
     */
    public function import(
        string $filePath,
        string $originalName,
        int $teacherId,
        int $initiatorId,
        int $moduleId,
        int $workTypeId,
        int $periodId,
        string $dateAssigned,
    ): ImportResult {
        $result = new ImportResult();

        // Контекст импорта
        $teacher = Teacher::find($teacherId);
        $module = Module::with('curriculum')->find($moduleId);
        $workType = WorkType::find($workTypeId);
        $period = RatingPeriod::find($periodId);

        if (!$teacher || !$module || !$workType || !$period) {
            $result->addError(0, null, 'Не найден контекст импорта (преподаватель/модуль/вид работы/период).');
            $this->log($originalName, $filePath, $initiatorId, $result);
            return $result;
        }

        if ($period->isClosed()) {
            $result->addError(0, null, 'Период закрыт — импорт оценок запрещён.');
            $this->log($originalName, $filePath, $initiatorId, $result);
            return $result;
        }

        $rows = $this->reader->read($filePath, $originalName);

        if (empty($rows)) {
            $result->addError(0, null, 'Файл пуст или не содержит данных.');
            $this->log($originalName, $filePath, $initiatorId, $result);
            return $result;
        }

        foreach ($rows as $row) {
            $rowNum = (int) ($row['_row'] ?? 0);

            $recordBook = trim((string) ($row['record_book_number'] ?? $row['номер_зачётки'] ?? $row['зачетка'] ?? ''));
            $valueRaw = $row['value'] ?? $row['оценка'] ?? null;
            $weeksLateRaw = $row['weeks_late'] ?? $row['недель_просрочки'] ?? '0';

            if ($recordBook === '') {
                $result->addError($rowNum, 'record_book_number', 'Номер зачётки обязателен.');
                continue;
            }

            if ($valueRaw === null || trim((string) $valueRaw) === '') {
                $result->addError($rowNum, 'value', 'Оценка обязательна.');
                continue;
            }

            $value = (int) $valueRaw;
            if ($value < 0 || $value > 10) {
                $result->addError($rowNum, 'value', "Оценка '{$valueRaw}' вне диапазона 0–10.");
                continue;
            }

            $weeksLate = (int) ($weeksLateRaw ?: 0);
            if ($weeksLate < 0) {
                $result->addError($rowNum, 'weeks_late', 'Недель просрочки не может быть отрицательным.');
                continue;
            }

            $student = Student::where('record_book_number', $recordBook)->first();
            if (!$student) {
                $result->addError($rowNum, 'record_book_number', "Студент с зачёткой '{$recordBook}' не найден.");
                continue;
            }

            try {
                DB::transaction(function () use (
                    $teacher,
                    $student,
                    $module,
                    $workType,
                    $period,
                    $value,
                    $weeksLate,
                    $dateAssigned,
                    $rowNum,
                    $result
                ) {
                    $this->accessValidator->validate($teacher, $student, $module, $workType, $period);

                    Grade::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'module_id' => $module->id,
                            'work_type_id' => $workType->id,
                            'period_id' => $period->id,
                        ],
                        [
                            'teacher_id' => $teacher->id,
                            'value' => $value,
                            'date_assigned' => $dateAssigned,
                            'weeks_late' => $weeksLate,
                        ]
                    );

                    $result->addCreated(
                        $rowNum,
                        $student->record_book_number,
                        (string) $value,
                        trim("{$student->user->last_name} {$student->user->first_name} {$student->user->middle_name}"),
                        'grade'
                    );
                });
            } catch (ValidationException $e) {
                $messages = collect($e->errors())->flatten()->implode(' ');
                $result->addError($rowNum, null, $messages);
            } catch (\Throwable $e) {
                $result->addError($rowNum, null, 'Ошибка БД: ' . $e->getMessage());
            }
        }

        $this->log($originalName, $filePath, $initiatorId, $result);

        return $result;
    }

    private function log(string $originalName, string $filePath, int $userId, ImportResult $result): void
    {
        ImportLog::create([
            'user_id' => $userId,
            'import_type' => ImportLog::TYPE_GRADES,
            'file_name' => $originalName,
            'file_path' => $filePath,
            'file_size' => @filesize($filePath) ?: null,
            'status' => $result->status(),
            'saved_count' => $result->saved,
            'failed_count' => $result->failed,
            'error_details' => $result->errors,
        ]);
    }
}
