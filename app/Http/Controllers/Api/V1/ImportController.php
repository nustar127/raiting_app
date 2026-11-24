<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Import\ImportTemplateService;
use App\Services\Import\GradeImportService;
use App\Services\Import\UserImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ImportController extends Controller
{
    public function __construct(
        private readonly UserImportService $userImportService,
        private readonly GradeImportService $gradeImportService,
        private readonly ImportTemplateService $templateService,
    ) {}

    /**
     * POST /import/grades
     * multipart/form-data: file + module_id + work_type_id + period_id + date_assigned
     */
    public function grades(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isTeacher() && !$user->isAdmin()) {
            return response()->json(['message' => 'Только преподаватель или администратор может импортировать оценки.'], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls,txt', 'max:10240'],
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'work_type_id' => ['required', 'integer', 'exists:work_types,id'],
            'period_id' => ['required', 'integer', 'exists:rating_periods,id'],
            'date_assigned' => ['required', 'date'],
        ], [
            'file.required' => 'Файл обязателен.',
            'file.mimes' => 'Допустимые форматы: CSV, XLSX, XLS.',
            'file.max' => 'Максимальный размер файла — 10 МБ.',
            'module_id.required' => 'Модуль обязателен.',
            'work_type_id.required' => 'Вид работы обязателен.',
            'period_id.required' => 'Период обязателен.',
            'date_assigned.required' => 'Дата выставления обязательна.',
        ]);

        $teacher = $user->teacher;
        if (!$teacher) {
            return response()->json(['message' => 'У пользователя нет профиля преподавателя.'], 403);
        }

        $file = $request->file('file');

        $result = $this->gradeImportService->import(
            filePath: $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
            teacherId: $teacher->id,        // ← Teacher ID, а не User ID
            initiatorId: $user->id,         // ← User ID для лога
            moduleId: (int) $request->input('module_id'),
            workTypeId: (int) $request->input('work_type_id'),
            periodId: (int) $request->input('period_id'),
            dateAssigned: $request->input('date_assigned'),
        );

        return response()->json($result->toArray());
    }

    /**
     * POST /import/users
     * multipart/form-data с полем 'file' (csv или xlsx).
     */
    public function users(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Только администратор может импортировать пользователей.'], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls,txt', 'max:10240'],
        ], [
            'file.required' => 'Файл обязателен.',
            'file.mimes' => 'Допустимые форматы: CSV, XLSX, XLS.',
            'file.max' => 'Максимальный размер файла — 10 МБ.',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $result = $this->userImportService->import(
            filePath: $path,
            originalName: $file->getClientOriginalName(),
            initiatorId: $request->user()->id,
        );

        return response()->json($result->toArray());
    }

    /**
     * GET /import/users/template?format=csv|xlsx
     */
    public function usersTemplate(Request $request): Response|JsonResponse
    {
        $format = $request->query('format', 'csv');
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            return response()->json(['message' => 'Допустимые форматы: csv, xlsx.'], 422);
        }

        return $this->templateService->usersTemplate($format);
    }

    /**
     * GET /import/grades/template?format=csv|xlsx
     */
    public function gradesTemplate(Request $request): Response|JsonResponse
    {
        $format = $request->query('format', 'csv');
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            return response()->json(['message' => 'Допустимые форматы: csv, xlsx.'], 422);
        }

        return $this->templateService->gradesTemplate($format);
    }
}
