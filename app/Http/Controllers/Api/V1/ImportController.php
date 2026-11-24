<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Import\ImportTemplateService;
use App\Services\Import\UserImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ImportController extends Controller
{
    public function __construct(
        private readonly UserImportService $userImportService,
        private readonly ImportTemplateService $templateService,
    ) {}

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
