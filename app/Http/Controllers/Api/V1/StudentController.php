<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\User\LoginGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function __construct(private readonly LoginGenerator $loginGenerator) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Student::class);

        $query = Student::query()
            ->with(['user', 'group'])
            ->join('users', 'users.id', '=', 'students.user_id')
            ->select('students.*');

        // Scoping для декана
        if ($facultyScope = $request->attributes->get('faculty_scope')) {
            $query->whereHas('group', fn($q) => $q->where('faculty_id', $facultyScope));
        }

        if ($groupId = $request->query('group_id')) {
            $query->where('group_id', $groupId);
        }

        if ($facultyId = $request->query('faculty_id')) {
            $query->whereHas('group', fn($q) => $q->where('faculty_id', $facultyId));
        }

        if ($course = $request->query('course')) {
            $targetYear = (int) date('Y') - (int) $course + 1;
            $query->whereHas('group', fn($q) => $q->where('enrollment_year', $targetYear));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('users.last_name', 'ilike', "%{$search}%")
                    ->orWhere('users.first_name', 'ilike', "%{$search}%")
                    ->orWhere('users.middle_name', 'ilike', "%{$search}%")
                    ->orWhere('students.record_book_number', 'ilike', "%{$search}%");
            });
        }

        $sortBy = $request->query('sort_by', 'users.last_name');
        $sortDir = strtolower($request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['users.last_name', 'users.first_name', 'students.record_book_number', 'students.status'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'users.last_name';
        }
        $query->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return StudentResource::collection($query->paginate($perPage));
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        Gate::authorize('create', Student::class);

        $validated = $request->validated();

        $student = DB::transaction(function () use ($validated) {
            $login = $this->loginGenerator->fromRecordBook($validated['record_book_number']);
            $temporaryPassword = \Str::random(12);

            $userRole = Role::where('code', 'student')->firstOrFail();

            $user = User::create([
                'login' => $login,
                'password_hash' => Hash::make($temporaryPassword),
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'role_id' => $userRole->id,
                'is_blocked' => false,
                'must_change_password' => true,
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'group_id' => $validated['group_id'],
                'record_book_number' => $validated['record_book_number'],
                'status' => $validated['status'] ?? Student::STATUS_ACTIVE,
            ]);

            return [$student, $temporaryPassword, $login];
        });

        [$student, $temporaryPassword, $login] = $student;

        return response()->json([
            'data' => new StudentResource($student->load(['user', 'group'])),
            'credentials' => [
                'login' => $login,
                'temporary_password' => $temporaryPassword,
            ],
        ], 201);
    }

    public function show(Request $request, Student $student): StudentResource
    {
        Gate::authorize('view', $student);

        $student->load(['user', 'group']);

        return new StudentResource($student);
    }

    public function update(UpdateStudentRequest $request, Student $student): StudentResource
    {
        Gate::authorize('update', $student);

        $validated = $request->validated();

        DB::transaction(function () use ($request, $student, $validated) {
            $studentData = [];
            foreach (['group_id', 'record_book_number', 'status'] as $field) {
                if ($request->has($field)) {
                    $studentData[$field] = $validated[$field];
                }
            }
            if (!empty($studentData)) {
                $student->update($studentData);
            }

            $userData = [];
            foreach (['last_name', 'first_name', 'middle_name'] as $field) {
                if ($request->has($field)) {
                    $userData[$field] = $validated[$field];
                }
            }
            if (!empty($userData)) {
                $student->user->update($userData);
            }
        });

        return new StudentResource($student->fresh()->load(['user', 'group']));
    }

    public function destroy(Student $student): JsonResponse
    {
        Gate::authorize('delete', $student);

        // TODO(5): проверить grades, когда появится модель Grade
        // if ($student->grades()->exists()) { ... 409 ... }

        DB::transaction(fn() => $student->user->delete());

        return response()->json(null, 204);
    }
}
