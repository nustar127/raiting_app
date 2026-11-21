<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Http\Resources\TeacherResource;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use App\Services\User\TranslitLoginGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeacherController extends Controller
{
    public function __construct(private readonly TranslitLoginGenerator $loginGenerator) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Teacher::class);

        $query = Teacher::query()
            ->with(['user', 'department'])
            ->join('users', 'users.id', '=', 'teachers.user_id')
            ->select('teachers.*');

        // Scoping для декана — преподаватели его факультета
        if ($facultyScope = $request->attributes->get('faculty_scope')) {
            $query->whereHas('department', fn($q) => $q->where('faculty_id', $facultyScope));
        }

        if ($departmentId = $request->query('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($facultyId = $request->query('faculty_id')) {
            $query->whereHas('department', fn($q) => $q->where('faculty_id', $facultyId));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('users.last_name', 'ilike', "%{$search}%")
                    ->orWhere('users.first_name', 'ilike', "%{$search}%")
                    ->orWhere('users.middle_name', 'ilike', "%{$search}%");
            });
        }

        $sortBy = $request->query('sort_by', 'users.last_name');
        $sortDir = strtolower($request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['users.last_name', 'users.first_name'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'users.last_name';
        }
        $query->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return TeacherResource::collection($query->paginate($perPage));
    }

    public function store(StoreTeacherRequest $request): JsonResponse
    {
        Gate::authorize('create', Teacher::class);

        $validated = $request->validated();

        [$teacher, $temporaryPassword, $login] = DB::transaction(function () use ($validated) {
            $login = $this->loginGenerator->fromFullName(
                $validated['last_name'],
                $validated['first_name'],
                $validated['middle_name'] ?? null
            );
            $temporaryPassword = Str::random(12);

            $role = Role::where('code', 'teacher')->firstOrFail();

            $user = User::create([
                'login' => $login,
                'password_hash' => Hash::make($temporaryPassword),
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'role_id' => $role->id,
                'is_blocked' => false,
                'must_change_password' => true,
            ]);

            $teacher = Teacher::create([
                'user_id' => $user->id,
                'department_id' => $validated['department_id'],
            ]);

            return [$teacher, $temporaryPassword, $login];
        });

        return response()->json([
            'data' => new TeacherResource($teacher->load(['user', 'department'])),
            'credentials' => [
                'login' => $login,
                'temporary_password' => $temporaryPassword,
            ],
        ], 201);
    }

    public function show(Teacher $teacher): TeacherResource
    {
        Gate::authorize('view', $teacher);

        $teacher->load(['user', 'department']);

        return new TeacherResource($teacher);
    }

    public function update(UpdateTeacherRequest $request, Teacher $teacher): TeacherResource
    {
        Gate::authorize('update', $teacher);

        $validated = $request->validated();

        DB::transaction(function () use ($request, $teacher, $validated) {
            if ($request->has('department_id')) {
                $teacher->update(['department_id' => $validated['department_id']]);
            }

            $userData = [];
            foreach (['last_name', 'first_name', 'middle_name'] as $field) {
                if ($request->has($field)) {
                    $userData[$field] = $validated[$field];
                }
            }
            if (!empty($userData)) {
                $teacher->user->update($userData);
            }
        });

        return new TeacherResource($teacher->fresh()->load(['user', 'department']));
    }

    public function destroy(Teacher $teacher): JsonResponse
    {
        Gate::authorize('delete', $teacher);

        // TODO(4): проверить teacher_assignments и grades
        DB::transaction(fn() => $teacher->user->delete());

        return response()->json(null, 204);
    }
}
