<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherAssignment\StoreTeacherAssignmentRequest;
use App\Http\Resources\TeacherAssignmentResource;
use App\Models\TeacherAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TeacherAssignmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TeacherAssignment::class);

        $query = TeacherAssignment::query()
            ->with(['teacher.user', 'curriculum', 'period']);

        // Scoping для декана
        if ($facultyScope = $request->attributes->get('faculty_scope')) {
            $query->whereHas('curriculum.group', fn($q) => $q->where('faculty_id', $facultyScope));
        }

        // Scoping для преподавателя — только свои привязки
        if ($teacherScope = $request->attributes->get('teacher_scope')) {
            $query->where('teacher_id', $teacherScope);
        }

        if ($teacherId = $request->query('teacher_id')) {
            $query->where('teacher_id', $teacherId);
        }

        if ($curriculumId = $request->query('curriculum_id')) {
            $query->where('curriculum_id', $curriculumId);
        }

        if ($periodId = $request->query('period_id')) {
            $query->where('period_id', $periodId);
        }

        $query->orderByDesc('id');

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return TeacherAssignmentResource::collection($query->paginate($perPage));
    }

    public function store(StoreTeacherAssignmentRequest $request): JsonResponse
    {
        Gate::authorize('create', TeacherAssignment::class);

        $assignment = TeacherAssignment::create($request->validated());

        return (new TeacherAssignmentResource(
            $assignment->load(['teacher.user', 'curriculum', 'period'])
        ))->response()->setStatusCode(201);
    }

    public function show(TeacherAssignment $teacherAssignment): TeacherAssignmentResource
    {
        Gate::authorize('view', $teacherAssignment);

        $teacherAssignment->load(['teacher.user', 'curriculum', 'period']);

        return new TeacherAssignmentResource($teacherAssignment);
    }

    public function destroy(TeacherAssignment $teacherAssignment): JsonResponse
    {
        Gate::authorize('delete', $teacherAssignment);

        // TODO(5.1): проверить, что по привязке нет оценок
        $teacherAssignment->delete();

        return response()->json(null, 204);
    }
}
