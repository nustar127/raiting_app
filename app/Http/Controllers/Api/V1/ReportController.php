<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PeriodSummaryReportResource;
use App\Http\Resources\RatingListReportResource;
use App\Http\Resources\StudentCardReportResource;
use App\Models\CumulativeRating;
use App\Models\Curriculum;
use App\Models\Faculty;
use App\Models\FinalRating;
use App\Models\Group;
use App\Models\Grade;
use App\Models\ModuleRating;
use App\Models\RatingPeriod;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    /**
     * GET /reports/rating-list
     * Фильтры: faculty_id, group_id, period_id, course, sort_by, sort_dir, per_page
     */
    public function ratingList(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Student::class);

        $user = $request->user();
        $facultyScope = $request->attributes->get('faculty_scope');

        $period = $this->resolvePeriod($request, $user, $facultyScope);
        if (!$period) {
            return response()->json(['message' => 'Нет доступных периодов.'], 404);
        }

        $facultyId = $facultyScope ?? $request->query('faculty_id');
        $groupId = $request->query('group_id');
        $course = $request->query('course');

        $query = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('groups as g', 'g.id', '=', 's.group_id')
            ->where('cr.period_id', $period->id)
            ->where('s.status', 'active');

        if ($facultyId) {
            $query->where('g.faculty_id', $facultyId);
        }
        if ($groupId) {
            $query->where('s.group_id', $groupId);
        }
        if ($course) {
            $targetYear = (int) date('Y') - (int) $course + 1;
            $query->where('g.enrollment_year', $targetYear);
        }

        $sortBy = $request->query('sort_by', 'calculated_value');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['calculated_value', 'last_name', 'record_book_number'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'calculated_value';
        }
        if ($sortBy === 'calculated_value') {
            $query->orderBy('cr.calculated_value', $sortDir);
        } elseif ($sortBy === 'last_name') {
            $query->orderBy('u.last_name', $sortDir);
        } else {
            $query->orderBy('s.record_book_number', $sortDir);
        }

        $perPage = max(1, min((int) $request->query('per_page', 100), 500));

        $rows = $query
            ->selectRaw('
                s.id as student_id,
                s.record_book_number,
                u.last_name,
                u.first_name,
                u.middle_name,
                g.id as group_id,
                g.name as group_name,
                cr.calculated_value as cumulative_value,
                cr.disciplines_count
            ')
            ->paginate($perPage);

        // Нумерация на лету: rank = offset + idx + 1
        $offset = ($rows->currentPage() - 1) * $rows->perPage();
        $items = $rows->getCollection()->values()->map(function ($r, $idx) use ($offset) {
            return [
                'rank' => $offset + $idx + 1,
                'student_id' => (int) $r->student_id,
                'record_book_number' => $r->record_book_number,
                'full_name' => trim("{$r->last_name} {$r->first_name} {$r->middle_name}"),
                'group_id' => (int) $r->group_id,
                'group_name' => $r->group_name,
                'cumulative_value' => round((float) $r->cumulative_value, 2),
                'disciplines_count' => (int) $r->disciplines_count,
            ];
        })->all();

        return response()->json([
            'data' => new RatingListReportResource((object) [
                'period' => $period,
                'filters' => [
                    'faculty_id' => $facultyId ? (int) $facultyId : null,
                    'group_id' => $groupId ? (int) $groupId : null,
                    'course' => $course ? (int) $course : null,
                ],
                'items' => $items,
                'pagination' => [
                    'current_page' => $rows->currentPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                    'last_page' => $rows->lastPage(),
                ],
            ]),
        ]);
    }

    /**
     * GET /reports/student-card?student_id=X&period_id=Y
     * Полная карточка для печати: дисциплины, модули, оценки, места.
     */
    public function studentCard(Request $request): JsonResponse
    {
        $studentId = $request->query('student_id');
        if (!$studentId) {
            return response()->json(['message' => 'Параметр student_id обязателен.'], 422);
        }

        $student = Student::with(['user', 'group.faculty'])->find($studentId);
        if (!$student) {
            return response()->json(['message' => 'Студент не найден.'], 404);
        }

        Gate::authorize('view', $student);

        $user = $request->user();
        $facultyScope = $request->attributes->get('faculty_scope');

        // Декан — только свой факультет
        if ($facultyScope !== null && (int) $facultyScope !== $student->group->faculty_id) {
            return response()->json(['message' => 'Доступ к чужому факультету запрещён.'], 403);
        }

        $period = $this->resolvePeriod($request, $user, $student->group->faculty_id);
        if (!$period) {
            return response()->json(['message' => 'Нет доступных периодов.'], 404);
        }

        $cumulative = CumulativeRating::where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->first();

        // Все дисциплины с ИР
        $finals = FinalRating::where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->with('curriculum.discipline')
            ->get();

        // Все модульные рейтинги
        $moduleRatings = ModuleRating::where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->with('module')
            ->get()
            ->groupBy('module.curriculum_id');

        // Все оценки с деталями
        $grades = Grade::where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->with(['module', 'workType', 'teacher.user'])
            ->get();

        // Места
        $places = $this->calculatePlaces($student, $period);

        return response()->json([
            'data' => new StudentCardReportResource((object) [
                'student' => $student,
                'period' => $period,
                'cumulative' => $cumulative,
                'finals' => $finals,
                'moduleRatings' => $moduleRatings,
                'grades' => $grades,
                'places' => $places,
            ]),
        ]);
    }

    /**
     * GET /reports/period-summary?period_id=Y
     * Сводка по периоду: сколько студентов, средний R_сов, распределение.
     */
    public function periodSummary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Student::class);

        $user = $request->user();
        $facultyScope = $request->attributes->get('faculty_scope');

        $period = $this->resolvePeriod($request, $user, $facultyScope);
        if (!$period) {
            return response()->json(['message' => 'Нет доступных периодов.'], 404);
        }

        $facultyId = $facultyScope ?? $period->faculty_id;

        // Общие цифры
        $totals = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->join('groups as g', 'g.id', '=', 's.group_id')
            ->where('cr.period_id', $period->id)
            ->where('s.status', 'active')
            ->where('g.faculty_id', $facultyId)
            ->selectRaw('
                COUNT(cr.student_id) as students_count,
                AVG(cr.calculated_value) as avg_rating,
                MAX(cr.calculated_value) as max_rating,
                MIN(cr.calculated_value) as min_rating
            ')
            ->first();

        // Распределение по диапазонам (0-59, 60-69, 70-79, 80-89, 90-100)
        $distribution = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->join('groups as g', 'g.id', '=', 's.group_id')
            ->where('cr.period_id', $period->id)
            ->where('s.status', 'active')
            ->where('g.faculty_id', $facultyId)
            ->selectRaw("
    CASE
        WHEN cr.calculated_value < 60 THEN '0-59'
        WHEN cr.calculated_value < 70 THEN '60-69'
        WHEN cr.calculated_value < 80 THEN '70-79'
        WHEN cr.calculated_value < 90 THEN '80-89'
        ELSE '90-100'
    END as bucket,
    COUNT(*) as cnt
")
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $buckets = ['0-59', '60-69', '70-79', '80-89', '90-100'];
        $distributionArr = [];
        foreach ($buckets as $b) {
            $distributionArr[$b] = (int) ($distribution->get($b)->cnt ?? 0);
        }

        // По группам
        $groups = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->join('groups as g', 'g.id', '=', 's.group_id')
            ->where('cr.period_id', $period->id)
            ->where('s.status', 'active')
            ->where('g.faculty_id', $facultyId)
            ->groupBy('g.id', 'g.name')
            ->selectRaw('
                g.id as group_id,
                g.name as group_name,
                COUNT(cr.student_id) as students_count,
                AVG(cr.calculated_value) as avg_rating
            ')
            ->orderBy('g.name')
            ->get()
            ->map(fn($r) => [
                'group_id' => (int) $r->group_id,
                'group_name' => $r->group_name,
                'students_count' => (int) $r->students_count,
                'avg_rating' => round((float) $r->avg_rating, 2),
            ])->all();

        // По дисциплинам
        $disciplines = DB::table('final_ratings as fr')
            ->join('students as s', 's.id', '=', 'fr.student_id')
            ->join('groups as g', 'g.id', '=', 's.group_id')
            ->join('curricula as c', 'c.id', '=', 'fr.curriculum_id')
            ->join('disciplines as d', 'd.id', '=', 'c.discipline_id')
            ->where('fr.period_id', $period->id)
            ->where('s.status', 'active')
            ->where('g.faculty_id', $facultyId)
            ->groupBy('d.id', 'd.name', 'd.code')
            ->selectRaw('
                d.id as discipline_id,
                d.name as discipline_name,
                d.code as discipline_code,
                COUNT(DISTINCT fr.student_id) as students_count,
                AVG(fr.calculated_value) as avg_rating
            ')
            ->orderBy('d.name')
            ->get()
            ->map(fn($r) => [
                'discipline_id' => (int) $r->discipline_id,
                'discipline_name' => $r->discipline_name,
                'discipline_code' => $r->discipline_code,
                'students_count' => (int) $r->students_count,
                'avg_rating' => round((float) $r->avg_rating, 2),
            ])->all();

        return response()->json([
            'data' => new PeriodSummaryReportResource((object) [
                'period' => $period,
                'faculty_id' => $facultyId,
                'totals' => [
                    'students_count' => (int) ($totals->students_count ?? 0),
                    'avg_rating' => $totals && $totals->avg_rating !== null ? round((float) $totals->avg_rating, 2) : 0.0,
                    'max_rating' => $totals && $totals->max_rating !== null ? round((float) $totals->max_rating, 2) : 0.0,
                    'min_rating' => $totals && $totals->min_rating !== null ? round((float) $totals->min_rating, 2) : 0.0,
                ],
                'distribution' => $distributionArr,
                'groups' => $groups,
                'disciplines' => $disciplines,
            ]),
        ]);
    }

    // ---------------------------------------------------------------
    // Общие методы
    // ---------------------------------------------------------------

    private function resolvePeriod(Request $request, $user, ?int $facultyId = null): ?RatingPeriod
    {
        if ($periodId = $request->query('period_id')) {
            return RatingPeriod::find($periodId);
        }

        if (!$facultyId) {
            if ($user->isDean() && $user->deanEmployee) {
                $facultyId = $user->deanEmployee->faculty_id;
            } elseif ($user->isStudent() && $user->student?->group) {
                $facultyId = $user->student->group->faculty_id;
            }
        }

        $q = RatingPeriod::query();
        if ($facultyId) {
            $q->where('faculty_id', $facultyId);
        }

        return $q->orderByDesc('academic_year')->orderByDesc('semester')->first();
    }

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
