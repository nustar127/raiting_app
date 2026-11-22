<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CumulativeRatingResource;
use App\Http\Resources\FinalRatingResource;
use App\Http\Resources\ModuleRatingResource;
use App\Http\Resources\StudentRatingCardResource;
use App\Models\CumulativeRating;
use App\Models\FinalRating;
use App\Models\Group;
use App\Models\ModuleRating;
use App\Models\RatingPeriod;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RatingController extends Controller
{
    /**
     * GET /ratings/students/{student}
     * Карточка студента: R_сов, разбивка по дисциплинам и модулям, места.
     */
    public function student(Request $request, Student $student): JsonResponse
    {
        Gate::authorize('view', $student);

        $period = $this->resolvePeriod($request);
        if (!$period) {
            return response()->json(['message' => 'Период не найден.'], 404);
        }

        $cumulative = CumulativeRating::where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->first();

        $finals = FinalRating::where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->with(['curriculum.discipline', 'curriculum.group'])
            ->get();

        $moduleRatings = ModuleRating::where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->with('module')
            ->get();

        $places = $this->calculatePlaces($student, $period);

        return response()->json([
            'data' => new StudentRatingCardResource((object) [
                'student' => $student->load('user', 'group'),
                'period' => $period,
                'cumulative' => $cumulative,
                'finals' => $finals,
                'modules' => $moduleRatings,
                'places' => $places,
            ]),
        ]);
    }

    /**
     * GET /ratings/groups/{group}
     * Рейтинговый список группы за период.
     */
    public function group(Request $request, Group $group): JsonResponse
    {
        Gate::authorize('viewAny', \App\Models\Student::class);

        $period = $this->resolvePeriod($request);
        if (!$period) {
            return response()->json(['message' => 'Период не найден.'], 404);
        }

        $rows = CumulativeRating::query()
            ->where('period_id', $period->id)
            ->whereHas('student', fn($q) => $q->where('group_id', $group->id))
            ->with('student.user')
            ->orderByDesc('calculated_value')
            ->get();

        // Нумерация на лету
        $ranked = $rows->values()->map(function ($row, $idx) {
            return [
                'rank' => $idx + 1,
                'student_id' => $row->student_id,
                'full_name' => trim(
                    "{$row->student->user->last_name} {$row->student->user->first_name} {$row->student->user->middle_name}"
                ),
                'record_book_number' => $row->student->record_book_number,
                'cumulative_value' => (float) $row->calculated_value,
                'disciplines_count' => $row->disciplines_count,
            ];
        });

        return response()->json([
            'data' => [
                'group' => ['id' => $group->id, 'name' => $group->name],
                'period' => ['id' => $period->id, 'name' => $period->name],
                'students' => $ranked,
            ],
        ]);
    }

    /**
     * GET /ratings/faculties/{facultyId}
     * Рейтинговый список факультета (по группам или общий).
     */
    public function faculty(Request $request, int $facultyId): JsonResponse
    {
        Gate::authorize('viewAny', \App\Models\Student::class);

        $period = $this->resolvePeriod($request);
        if (!$period) {
            return response()->json(['message' => 'Период не найден.'], 404);
        }

        if ($period->faculty_id !== $facultyId) {
            return response()->json(['message' => 'Период относится к другому факультету.'], 422);
        }

        $query = CumulativeRating::query()
            ->where('period_id', $period->id)
            ->whereHas('student.group', fn($q) => $q->where('faculty_id', $facultyId))
            ->with(['student.user', 'student.group'])
            ->orderByDesc('calculated_value');

        if ($groupId = $request->query('group_id')) {
            $query->whereHas('student', fn($q) => $q->where('group_id', $groupId));
        }

        $rows = $query->limit(500)->get(); // sanity limit

        $ranked = $rows->values()->map(function ($row, $idx) {
            return [
                'rank' => $idx + 1,
                'student_id' => $row->student_id,
                'full_name' => trim(
                    "{$row->student->user->last_name} {$row->student->user->first_name} {$row->student->user->middle_name}"
                ),
                'group_name' => $row->student->group->name,
                'cumulative_value' => (float) $row->calculated_value,
            ];
        });

        return response()->json([
            'data' => [
                'faculty_id' => $facultyId,
                'period' => ['id' => $period->id, 'name' => $period->name],
                'students' => $ranked,
            ],
        ]);
    }

    /**
     * GET /ratings/top?scope=faculty&limit=10
     */
    public function top(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', \App\Models\Student::class);

        $period = $this->resolvePeriod($request);
        if (!$period) {
            return response()->json(['message' => 'Период не найден.'], 404);
        }

        $limit = max(1, min((int) $request->query('limit', 10), 100));

        $rows = CumulativeRating::query()
            ->where('period_id', $period->id)
            ->with(['student.user', 'student.group'])
            ->orderByDesc('calculated_value')
            ->limit($limit)
            ->get();

        $ranked = $rows->values()->map(fn($row, $idx) => [
            'rank' => $idx + 1,
            'student_id' => $row->student_id,
            'full_name' => trim(
                "{$row->student->user->last_name} {$row->student->user->first_name} {$row->student->user->middle_name}"
            ),
            'group_name' => $row->student->group->name,
            'cumulative_value' => (float) $row->calculated_value,
        ]);

        return response()->json([
            'data' => [
                'period' => ['id' => $period->id, 'name' => $period->name],
                'top' => $ranked,
            ],
        ]);
    }

    /**
     * Разрешение периода: ?period_id=N или активный период факультета студента.
     */
    private function resolvePeriod(Request $request): ?RatingPeriod
    {
        if ($periodId = $request->query('period_id')) {
            return RatingPeriod::find($periodId);
        }

        $user = $request->user();

        // Для студента — факультет его группы
        if ($user->isStudent() && $user->student?->group) {
            return RatingPeriod::where('faculty_id', $user->student->group->faculty_id)
                ->orderByDesc('academic_year')
                ->orderByDesc('semester')
                ->first();
        }

        // Для декана — его факультет
        if ($user->isDean() && $user->deanEmployee) {
            return RatingPeriod::where('faculty_id', $user->deanEmployee->faculty_id)
                ->orderByDesc('academic_year')
                ->orderByDesc('semester')
                ->first();
        }

        // Для остальных — последний период
        return RatingPeriod::orderByDesc('academic_year')->orderByDesc('semester')->first();
    }

    /**
     * Места студента в группе/курсе/факультете.
     */
    private function calculatePlaces(Student $student, RatingPeriod $period): array
    {
        $studentValue = (float) (CumulativeRating::where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->value('calculated_value') ?? 0);

        if ($studentValue <= 0) {
            return ['group' => null, 'course' => null, 'faculty' => null];
        }

        $groupRank = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->where('cr.period_id', $period->id)
            ->where('s.group_id', $student->group_id)
            ->where('cr.calculated_value', '>', $studentValue)
            ->count() + 1;

        $groupTotal = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->where('cr.period_id', $period->id)
            ->where('s.group_id', $student->group_id)
            ->count();

        // Курс: группы с тем же enrollment_year
        $enrollmentYear = $student->group->enrollment_year;

        $courseRank = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->join('groups as g', 'g.id', '=', 's.group_id')
            ->where('cr.period_id', $period->id)
            ->where('g.enrollment_year', $enrollmentYear)
            ->where('cr.calculated_value', '>', $studentValue)
            ->count() + 1;

        $courseTotal = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->join('groups as g', 'g.id', '=', 's.group_id')
            ->where('cr.period_id', $period->id)
            ->where('g.enrollment_year', $enrollmentYear)
            ->count();

        // Факультет
        $facultyRank = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->join('groups as g', 'g.id', '=', 's.group_id')
            ->where('cr.period_id', $period->id)
            ->where('g.faculty_id', $student->group->faculty_id)
            ->where('cr.calculated_value', '>', $studentValue)
            ->count() + 1;

        $facultyTotal = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->join('groups as g', 'g.id', '=', 's.group_id')
            ->where('cr.period_id', $period->id)
            ->where('g.faculty_id', $student->group->faculty_id)
            ->count();

        return [
            'group' => ['rank' => $groupRank, 'total' => $groupTotal],
            'course' => ['rank' => $courseRank, 'total' => $courseTotal],
            'faculty' => ['rank' => $facultyRank, 'total' => $facultyTotal],
        ];
    }
}
