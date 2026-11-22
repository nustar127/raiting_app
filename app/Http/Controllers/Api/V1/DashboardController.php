<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FacultyDashboardResource;
use App\Http\Resources\GroupDashboardResource;
use App\Http\Resources\SummaryDashboardResource;
use App\Models\Faculty;
use App\Models\Group;
use App\Models\RatingPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /dashboard/summary
     * Сводка: либо по факультету (декан), либо по вузу (админ).
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $period = $this->resolvePeriod($request, $user);
        if (!$period) {
            return response()->json(['message' => 'Нет доступных периодов.'], 404);
        }

        // Декан видит только свой факультет
        $facultyScope = $request->attributes->get('faculty_scope');
        $facultyId = $facultyScope ?? $request->query('faculty_id');

        $data = $this->buildFacultyStats($period, $facultyId);

        return response()->json([
            'data' => new SummaryDashboardResource((object) [
                'period' => $period,
                'faculty_id' => $facultyId,
                'stats' => $data,
            ]),
        ]);
    }

    /**
     * GET /dashboard/faculty/{faculty}
     */
    public function faculty(Request $request, Faculty $faculty): JsonResponse
    {
        $user = $request->user();

        // Декан — только свой факультет
        $facultyScope = $request->attributes->get('faculty_scope');
        if ($facultyScope !== null && (int) $facultyScope !== $faculty->id) {
            return response()->json(['message' => 'Доступ к другому факультету запрещён.'], 403);
        }

        $period = $this->resolvePeriod($request, $user, $faculty->id);
        if (!$period) {
            return response()->json(['message' => 'Нет доступных периодов.'], 404);
        }

        $stats = $this->buildFacultyStats($period, $faculty->id);
        $groupsBreakdown = $this->buildGroupsBreakdown($period, $faculty->id);

        return response()->json([
            'data' => new FacultyDashboardResource((object) [
                'faculty' => $faculty,
                'period' => $period,
                'stats' => $stats,
                'groups' => $groupsBreakdown,
            ]),
        ]);
    }

    /**
     * GET /dashboard/group/{group}
     */
    public function group(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        // Декан — только группы своего факультета
        $facultyScope = $request->attributes->get('faculty_scope');
        if ($facultyScope !== null && (int) $facultyScope !== $group->faculty_id) {
            return response()->json(['message' => 'Доступ к группе другого факультета запрещён.'], 403);
        }

        // Студент — только свою группу
        if ($user->isStudent() && $user->student?->group_id !== $group->id) {
            return response()->json(['message' => 'Доступ к чужой группе запрещён.'], 403);
        }

        $period = $this->resolvePeriod($request, $user, $group->faculty_id);
        if (!$period) {
            return response()->json(['message' => 'Нет доступных периодов.'], 404);
        }

        $stats = $this->buildGroupStats($period, $group->id);
        $disciplinesBreakdown = $this->buildGroupDisciplinesBreakdown($period, $group->id);
        $topStudents = $this->buildGroupTop($period, $group->id, 10);

        return response()->json([
            'data' => new GroupDashboardResource((object) [
                'group' => $group,
                'period' => $period,
                'stats' => $stats,
                'disciplines' => $disciplinesBreakdown,
                'top_students' => $topStudents,
            ]),
        ]);
    }

    // ---------------------------------------------------------------
    // Приватные методы
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

    /**
     * @return array{avg_rating: float, students_count: int, admitted_count: int, groups_count: int}
     */
    private function buildFacultyStats(RatingPeriod $period, ?int $facultyId): array
    {
        $query = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->join('groups as g', 'g.id', '=', 's.group_id')
            ->where('cr.period_id', $period->id)
            ->where('s.status', 'active');

        if ($facultyId) {
            $query->where('g.faculty_id', $facultyId);
        }

        $row = $query->selectRaw('
            COUNT(DISTINCT cr.student_id) as students_count,
            AVG(cr.calculated_value) as avg_rating,
            COUNT(DISTINCT g.id) as groups_count
        ')->first();

        // admitted_count — сколько студентов имеют все is_admitted = true
        $admittedQuery = DB::table('final_ratings as fr')
            ->join('students as s', 's.id', '=', 'fr.student_id')
            ->join('groups as g', 'g.id', '=', 's.group_id')
            ->where('fr.period_id', $period->id)
            ->where('s.status', 'active')
            ->where('fr.is_admitted', false);

        if ($facultyId) {
            $admittedQuery->where('g.faculty_id', $facultyId);
        }

        $notAdmitted = $admittedQuery->distinct()->count('fr.student_id');
        $studentsCount = (int) ($row->students_count ?? 0);

        return [
            'avg_rating' => $row && $row->avg_rating !== null ? round((float) $row->avg_rating, 2) : 0.0,
            'students_count' => $studentsCount,
            'admitted_count' => max(0, $studentsCount - $notAdmitted),
            'not_admitted_count' => $notAdmitted,
            'groups_count' => (int) ($row->groups_count ?? 0),
        ];
    }

    /**
     * @return array<array{group_id: int, group_name: string, students_count: int, avg_rating: float}>
     */
    private function buildGroupsBreakdown(RatingPeriod $period, int $facultyId): array
    {
        // LEFT JOIN: все группы факультета, даже без студентов
        $rows = DB::table('groups as g')
            ->leftJoin('students as s', function ($join) {
                $join->on('s.group_id', '=', 'g.id')
                    ->where('s.status', 'active');
            })
            ->leftJoin('cumulative_ratings as cr', function ($join) use ($period) {
                $join->on('cr.student_id', '=', 's.id')
                    ->where('cr.period_id', '=', $period->id);
            })
            ->where('g.faculty_id', $facultyId)
            ->groupBy('g.id', 'g.name')
            ->selectRaw('
            g.id as group_id,
            g.name as group_name,
            COUNT(DISTINCT s.id) as students_count,
            AVG(cr.calculated_value) as avg_rating
        ')
            ->orderBy('g.name')
            ->get();

        return $rows->map(fn($r) => [
            'group_id' => (int) $r->group_id,
            'group_name' => $r->group_name,
            'students_count' => (int) $r->students_count,
            'avg_rating' => $r->avg_rating !== null ? round((float) $r->avg_rating, 2) : 0.0,
        ])->all();
    }

    /**
     * @return array{avg_rating: float, students_count: int, admitted_count: int, best_rating: float, worst_rating: float}
     */
    private function buildGroupStats(RatingPeriod $period, int $groupId): array
    {
        $row = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->where('cr.period_id', $period->id)
            ->where('s.group_id', $groupId)
            ->where('s.status', 'active')
            ->selectRaw('
                COUNT(cr.student_id) as students_count,
                AVG(cr.calculated_value) as avg_rating,
                MAX(cr.calculated_value) as best_rating,
                MIN(cr.calculated_value) as worst_rating
            ')
            ->first();

        $studentsCount = (int) ($row->students_count ?? 0);

        $notAdmitted = DB::table('final_ratings as fr')
            ->join('students as s', 's.id', '=', 'fr.student_id')
            ->where('fr.period_id', $period->id)
            ->where('s.group_id', $groupId)
            ->where('fr.is_admitted', false)
            ->distinct()
            ->count('fr.student_id');

        return [
            'avg_rating' => $row && $row->avg_rating !== null ? round((float) $row->avg_rating, 2) : 0.0,
            'students_count' => $studentsCount,
            'admitted_count' => max(0, $studentsCount - $notAdmitted),
            'not_admitted_count' => $notAdmitted,
            'best_rating' => $row && $row->best_rating !== null ? round((float) $row->best_rating, 2) : 0.0,
            'worst_rating' => $row && $row->worst_rating !== null ? round((float) $row->worst_rating, 2) : 0.0,
        ];
    }

    /**
     * @return array<array{discipline_id: int, discipline_name: string, avg_rating: float, students_count: int}>
     */
    private function buildGroupDisciplinesBreakdown(RatingPeriod $period, int $groupId): array
    {
        $rows = DB::table('final_ratings as fr')
            ->join('students as s', 's.id', '=', 'fr.student_id')
            ->join('curricula as c', 'c.id', '=', 'fr.curriculum_id')
            ->join('disciplines as d', 'd.id', '=', 'c.discipline_id')
            ->where('fr.period_id', $period->id)
            ->where('s.group_id', $groupId)
            ->where('s.status', 'active')
            ->groupBy('d.id', 'd.name')
            ->selectRaw('
                d.id as discipline_id,
                d.name as discipline_name,
                AVG(fr.calculated_value) as avg_rating,
                COUNT(DISTINCT fr.student_id) as students_count
            ')
            ->orderBy('d.name')
            ->get();

        return $rows->map(fn($r) => [
            'discipline_id' => (int) $r->discipline_id,
            'discipline_name' => $r->discipline_name,
            'avg_rating' => round((float) $r->avg_rating, 2),
            'students_count' => (int) $r->students_count,
        ])->all();
    }

    /**
     * @return array<array{rank: int, student_id: int, full_name: string, rating: float}>
     */
    private function buildGroupTop(RatingPeriod $period, int $groupId, int $limit): array
    {
        $rows = DB::table('cumulative_ratings as cr')
            ->join('students as s', 's.id', '=', 'cr.student_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('cr.period_id', $period->id)
            ->where('s.group_id', $groupId)
            ->where('s.status', 'active')
            ->orderByDesc('cr.calculated_value')
            ->limit($limit)
            ->selectRaw('
                s.id as student_id,
                u.last_name,
                u.first_name,
                u.middle_name,
                cr.calculated_value as rating
            ')
            ->get();

        return $rows->values()->map(fn($r, $idx) => [
            'rank' => $idx + 1,
            'student_id' => (int) $r->student_id,
            'full_name' => trim("{$r->last_name} {$r->first_name} {$r->middle_name}"),
            'rating' => round((float) $r->rating, 2),
        ])->all();
    }
}
