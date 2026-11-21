<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grade\BulkGradeRequest;
use App\Http\Requests\Grade\StoreGradeRequest;
use App\Http\Requests\Grade\UpdateGradeRequest;
use App\Http\Resources\GradeResource;
use App\Models\Grade;
use App\Models\Module;
use App\Models\RatingPeriod;
use App\Models\Student;
use App\Models\WorkType;
use App\Services\Grade\GradeAccessValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GradeController extends Controller
{
    public function __construct(private readonly GradeAccessValidator $accessValidator) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Grade::class);

        $query = Grade::query()
            ->with(['student.user', 'module', 'workType', 'teacher.user']);

        // Scoping
        if ($facultyScope = $request->attributes->get('faculty_scope')) {
            $query->whereHas('student.group', fn($q) => $q->where('faculty_id', $facultyScope));
        }
        if ($teacherScope = $request->attributes->get('teacher_scope')) {
            $query->where('teacher_id', $teacherScope);
        }

        if ($studentId = $request->query('student_id')) {
            $query->where('student_id', $studentId);
        }
        if ($moduleId = $request->query('module_id')) {
            $query->where('module_id', $moduleId);
        }
        if ($workTypeId = $request->query('work_type_id')) {
            $query->where('work_type_id', $workTypeId);
        }
        if ($periodId = $request->query('period_id')) {
            $query->where('period_id', $periodId);
        }

        $query->orderByDesc('date_assigned')->orderByDesc('id');

        $perPage = max(1, min((int) $request->query('per_page', 50), 200));

        return GradeResource::collection($query->paginate($perPage));
    }

    public function store(StoreGradeRequest $request): JsonResponse
    {
        Gate::authorize('create', Grade::class);

        $teacher = $request->user()->teacher;

        $grade = Grade::create([
            'student_id' => $request->validated('student_id'),
            'module_id' => $request->validated('module_id'),
            'work_type_id' => $request->validated('work_type_id'),
            'period_id' => $request->validated('period_id'),
            'teacher_id' => $teacher->id,
            'value' => $request->validated('value'),
            'date_assigned' => $request->validated('date_assigned'),
            'weeks_late' => $request->validated('weeks_late', 0),
        ]);

        // TODO(6): dispatch RecalculateModuleRatingJob

        return (new GradeResource($grade->load(['student.user', 'module', 'workType', 'teacher.user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Grade $grade): GradeResource
    {
        Gate::authorize('view', $grade);

        $grade->load(['student.user', 'module', 'workType', 'teacher.user']);

        return new GradeResource($grade);
    }

    public function update(UpdateGradeRequest $request, Grade $grade): GradeResource
    {
        Gate::authorize('update', $grade);

        $grade->update($request->validated());

        // TODO(6): dispatch RecalculateModuleRatingJob

        return new GradeResource($grade->fresh()->load(['student.user', 'module', 'workType', 'teacher.user']));
    }

    public function destroy(Grade $grade): JsonResponse
    {
        Gate::authorize('delete', $grade);

        $grade->delete();

        // TODO(6): dispatch RecalculateModuleRatingJob

        return response()->json(null, 204);
    }

    /**
     * Массовый ввод оценок (U-R-11).
     */
    public function bulk(BulkGradeRequest $request): JsonResponse
    {
        $teacher = $request->user()->teacher;
        if (!$teacher) {
            return response()->json(['message' => 'Нет профиля преподавателя.'], 403);
        }

        $module = Module::with('curriculum')->findOrFail($request->validated('module_id'));
        $workType = WorkType::findOrFail($request->validated('work_type_id'));
        $period = RatingPeriod::findOrFail($request->validated('period_id'));

        $saved = 0;
        $errors = [];

        DB::transaction(function () use ($request, $teacher, $module, $workType, $period, &$saved, &$errors) {
            foreach ($request->validated('grades') as $row) {
                $student = Student::find($row['student_id']);
                if (!$student) {
                    $errors[] = [
                        'student_id' => $row['student_id'],
                        'message' => 'Студент не найден',
                    ];
                    continue;
                }

                try {
                    $this->accessValidator->validate($teacher, $student, $module, $workType, $period);
                } catch (ValidationException $e) {
                    $errors[] = [
                        'student_id' => $row['student_id'],
                        'message' => implode(' ', collect($e->errors())->flatten()->all()),
                    ];
                    continue;
                }

                Grade::updateOrCreate(
                    [
                        'student_id' => $row['student_id'],
                        'module_id' => $module->id,
                        'work_type_id' => $workType->id,
                        'period_id' => $period->id,
                    ],
                    [
                        'teacher_id' => $teacher->id,
                        'value' => $row['value'],
                        'date_assigned' => $request->validated('date_assigned'),
                        'weeks_late' => $row['weeks_late'] ?? 0,
                    ]
                );
                $saved++;
            }
        });

        // TODO(6): dispatch RecalculateModuleRatingJob

        return response()->json([
            'saved' => $saved,
            'failed' => count($errors),
            'errors' => $errors,
        ]);
    }
}
