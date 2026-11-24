<?php

namespace App\Services\Import;

use App\Models\DeanEmployee;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Group;
use App\Models\ImportLog;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\User\LoginGenerator;
use App\Services\User\TranslitLoginGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserImportService
{
    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly LoginGenerator $studentLoginGen,
        private readonly TranslitLoginGenerator $staffLoginGen,
    ) {}

    /**
     * Импорт пользователей из CSV/XLSX.
     * Каждая строка обрабатывается в отдельной транзакции:
     * успешные сохраняются, ошибочные — пропускаются с записью в отчёт.
     */
    public function import(string $filePath, string $originalName, int $initiatorId): ImportResult
    {
        $rows = $this->reader->read($filePath, $originalName);
        $result = new ImportResult();

        if (empty($rows)) {
            $result->addError(0, null, 'Файл пуст или не содержит данных.');
            $this->log($originalName, $filePath, $initiatorId, $result);
            return $result;
        }

        $roles = Role::pluck('id', 'code')->all();
        $requiredRoles = ['student', 'teacher', 'dean'];

        foreach ($rows as $row) {
            $rowNum = (int) ($row['_row'] ?? 0);

            $role = trim((string) ($row['role'] ?? $row['роль'] ?? ''));
            $lastName = trim((string) ($row['last_name'] ?? $row['фамилия'] ?? ''));
            $firstName = trim((string) ($row['first_name'] ?? $row['имя'] ?? ''));
            $middleName = trim((string) ($row['middle_name'] ?? $row['отчество'] ?? ''));

            if (!in_array($role, $requiredRoles, true)) {
                $result->addError($rowNum, 'role', "Роль '{$role}' не поддерживается (ожидается student/teacher/dean).");
                continue;
            }

            if ($lastName === '' || $firstName === '') {
                $result->addError($rowNum, 'last_name', 'Фамилия и имя обязательны.');
                continue;
            }

            if (!isset($roles[$role])) {
                $result->addError($rowNum, 'role', "Роль '{$role}' отсутствует в БД. Запустите RolesSeeder.");
                continue;
            }

            try {
                DB::transaction(function () use (
                    $role,
                    $row,
                    $rowNum,
                    $result,
                    $roles,
                    $lastName,
                    $firstName,
                    $middleName
                ) {
                    if ($role === 'student') {
                        $this->createStudent($row, $rowNum, $result, $roles['student'], $lastName, $firstName, $middleName);
                    } elseif ($role === 'teacher') {
                        $this->createTeacher($row, $rowNum, $result, $roles['teacher'], $lastName, $firstName, $middleName);
                    } else {
                        $this->createDean($row, $rowNum, $result, $roles['dean'], $lastName, $firstName, $middleName);
                    }
                });
            } catch (\Throwable $e) {
                $result->addError($rowNum, null, 'Ошибка БД: ' . $e->getMessage());
            }
        }

        $this->log($originalName, $filePath, $initiatorId, $result);

        return $result;
    }

    private function createStudent(
        array $row,
        int $rowNum,
        ImportResult $result,
        int $roleId,
        string $last,
        string $first,
        string $middle
    ): void {
        $groupName = trim((string) ($row['group_name'] ?? $row['группа'] ?? ''));
        $recordBook = trim((string) ($row['record_book_number'] ?? $row['номер_зачётки'] ?? $row['зачетка'] ?? ''));

        if ($groupName === '') {
            $result->addError($rowNum, 'group_name', 'Для студента обязательна группа.');
            return;
        }
        if ($recordBook === '') {
            $result->addError($rowNum, 'record_book_number', 'Для студента обязателен номер зачётки.');
            return;
        }

        $group = Group::where('name', $groupName)->first();
        if (!$group) {
            $result->addError($rowNum, 'group_name', "Группа '{$groupName}' не найдена.");
            return;
        }

        if (Student::where('record_book_number', $recordBook)->exists()) {
            $result->addError($rowNum, 'record_book_number', "Студент с зачёткой '{$recordBook}' уже существует.");
            return;
        }

        $login = $this->studentLoginGen->fromRecordBook($recordBook);
        $password = Str::random(12);

        $user = User::create([
            'login' => $login,
            'password_hash' => Hash::make($password),
            'last_name' => $last,
            'first_name' => $first,
            'middle_name' => $middle ?: null,
            'role_id' => $roleId,
            'must_change_password' => true,
        ]);

        Student::create([
            'user_id' => $user->id,
            'group_id' => $group->id,
            'record_book_number' => $recordBook,
            'status' => 'active',
        ]);

        $result->addCreated($rowNum, $login, $password, trim("{$last} {$first} {$middle}"), 'student');
    }

    private function createTeacher(
        array $row,
        int $rowNum,
        ImportResult $result,
        int $roleId,
        string $last,
        string $first,
        string $middle
    ): void {
        $deptName = trim((string) ($row['department_name'] ?? $row['кафедра'] ?? ''));

        if ($deptName === '') {
            $result->addError($rowNum, 'department_name', 'Для преподавателя обязательна кафедра.');
            return;
        }

        $dept = Department::where('short_name', $deptName)->orWhere('name', $deptName)->first();
        if (!$dept) {
            $result->addError($rowNum, 'department_name', "Кафедра '{$deptName}' не найдена.");
            return;
        }

        $login = $this->staffLoginGen->fromFullName($last, $first, $middle ?: null);
        $password = Str::random(12);

        $user = User::create([
            'login' => $login,
            'password_hash' => Hash::make($password),
            'last_name' => $last,
            'first_name' => $first,
            'middle_name' => $middle ?: null,
            'role_id' => $roleId,
            'must_change_password' => true,
        ]);

        Teacher::create([
            'user_id' => $user->id,
            'department_id' => $dept->id,
        ]);

        $result->addCreated($rowNum, $login, $password, trim("{$last} {$first} {$middle}"), 'teacher');
    }

    private function createDean(
        array $row,
        int $rowNum,
        ImportResult $result,
        int $roleId,
        string $last,
        string $first,
        string $middle
    ): void {
        $facultyName = trim((string) ($row['faculty_name'] ?? $row['факультет'] ?? ''));

        if ($facultyName === '') {
            $result->addError($rowNum, 'faculty_name', 'Для сотрудника деканата обязателен факультет.');
            return;
        }

        $faculty = Faculty::where('short_name', $facultyName)->orWhere('name', $facultyName)->first();
        if (!$faculty) {
            $result->addError($rowNum, 'faculty_name', "Факультет '{$facultyName}' не найден.");
            return;
        }

        $login = $this->staffLoginGen->fromFullName($last, $first, $middle ?: null);
        $password = Str::random(12);

        $user = User::create([
            'login' => $login,
            'password_hash' => Hash::make($password),
            'last_name' => $last,
            'first_name' => $first,
            'middle_name' => $middle ?: null,
            'role_id' => $roleId,
            'must_change_password' => true,
        ]);

        DeanEmployee::create([
            'user_id' => $user->id,
            'faculty_id' => $faculty->id,
        ]);

        $result->addCreated($rowNum, $login, $password, trim("{$last} {$first} {$middle}"), 'dean');
    }

    private function log(string $originalName, string $filePath, int $userId, ImportResult $result): void
    {
        ImportLog::create([
            'user_id' => $userId,
            'import_type' => ImportLog::TYPE_USERS,
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
