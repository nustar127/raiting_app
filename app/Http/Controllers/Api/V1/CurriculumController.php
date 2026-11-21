<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Curriculum\StoreCurriculumRequest;
use App\Http\Requests\Curriculum\UpdateCurriculumRequest;
use App\Http\Resources\CurriculumResource;
use App\Models\Curriculum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CurriculumController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Curriculum::class);

        $query = Curriculum::query()
            ->with(['discipline', 'group']);

        // Scoping для декана
        if ($facultyScope = $request->attributes->get('faculty_scope')) {
            $query->whereHas('group', fn($q) => $q->where('faculty_id', $facultyScope));
        }

        if ($groupId = $request->query('group_id')) {
            $query->where('group_id', $groupId);
        }

        if ($disciplineId = $request->query('discipline_id')) {
            $query->where('discipline_id', $disciplineId);
        }

        if ($facultyId = $request->query('faculty_id')) {
            $query->whereHas('group', fn($q) => $q->where('faculty_id', $facultyId));
        }

        if ($course = $request->query('course_number')) {
            $query->where('course_number', $course);
        }

        if ($semester = $request->query('semester_number')) {
            $query->where('semester_number', $semester);
        }

        $sortBy = $request->query('sort_by', 'course_number');
        $sortDir = strtolower($request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['course_number', 'semester_number', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'course_number';
        }
        $query->orderBy($sortBy, $sortDir)->orderBy('semester_number', 'asc');

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return CurriculumResource::collection($query->paginate($perPage));
    }

    public function store(StoreCurriculumRequest $request): JsonResponse
    {
        Gate::authorize('create', Curriculum::class);

        $curriculum = Curriculum::create($request->validated());

        return (new CurriculumResource($curriculum->load(['discipline', 'group'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Curriculum $curriculum): CurriculumResource
    {
        Gate::authorize('view', $curriculum);

        $curriculum->load(['discipline', 'group']);

        return new CurriculumResource($curriculum);
    }

    public function update(UpdateCurriculumRequest $request, Curriculum $curriculum): CurriculumResource
    {
        Gate::authorize('update', $curriculum);

        $curriculum->update($request->validated());

        return new CurriculumResource($curriculum->fresh()->load(['discipline', 'group']));
    }

    public function destroy(Curriculum $curriculum): JsonResponse
    {
        Gate::authorize('delete', $curriculum);

        // TODO(3.10): раскомментировать, когда появится Module
        // if ($curriculum->modules()->exists()) {
        //     return response()->json([
        //         'message' => 'Невозможно удалить запись учебного плана: по ней уже созданы модули.',
        //     ], 409);
        // }

        $curriculum->delete();

        return response()->json(null, 204);
    }
}
