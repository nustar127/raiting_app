<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeanEmployee\StoreDeanEmployeeRequest;
use App\Http\Requests\DeanEmployee\UpdateDeanEmployeeRequest;
use App\Http\Resources\DeanEmployeeResource;
use App\Models\DeanEmployee;
use App\Models\Role;
use App\Models\User;
use App\Services\User\TranslitLoginGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeanEmployeeController extends Controller
{
    public function __construct(private readonly TranslitLoginGenerator $loginGenerator) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', DeanEmployee::class);

        $query = DeanEmployee::query()
            ->with(['user', 'faculty'])
            ->join('users', 'users.id', '=', 'dean_employees.user_id')
            ->select('dean_employees.*');

        // Декан видит только свой факультет
        if ($facultyScope = $request->attributes->get('faculty_scope')) {
            $query->where('faculty_id', $facultyScope);
        }

        if ($facultyId = $request->query('faculty_id')) {
            $query->where('faculty_id', $facultyId);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('users.last_name', 'ilike', "%{$search}%")
                    ->orWhere('users.first_name', 'ilike', "%{$search}%")
                    ->orWhere('users.middle_name', 'ilike', "%{$search}%");
            });
        }

        $query->orderBy('users.last_name', 'asc');

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return DeanEmployeeResource::collection($query->paginate($perPage));
    }

    public function store(StoreDeanEmployeeRequest $request): JsonResponse
    {
        Gate::authorize('create', DeanEmployee::class);

        $validated = $request->validated();

        [$dean, $temporaryPassword, $login] = DB::transaction(function () use ($validated) {
            $login = $this->loginGenerator->fromFullName(
                $validated['last_name'],
                $validated['first_name'],
                $validated['middle_name'] ?? null
            );
            $temporaryPassword = Str::random(12);

            $role = Role::where('code', 'dean')->firstOrFail();

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

            $dean = DeanEmployee::create([
                'user_id' => $user->id,
                'faculty_id' => $validated['faculty_id'],
            ]);

            return [$dean, $temporaryPassword, $login];
        });

        return response()->json([
            'data' => new DeanEmployeeResource($dean->load(['user', 'faculty'])),
            'credentials' => [
                'login' => $login,
                'temporary_password' => $temporaryPassword,
            ],
        ], 201);
    }

    public function show(DeanEmployee $deanEmployee): DeanEmployeeResource
    {
        Gate::authorize('view', $deanEmployee);

        $deanEmployee->load(['user', 'faculty']);

        return new DeanEmployeeResource($deanEmployee);
    }

    public function update(UpdateDeanEmployeeRequest $request, DeanEmployee $deanEmployee): DeanEmployeeResource
    {
        Gate::authorize('update', $deanEmployee);

        $validated = $request->validated();

        DB::transaction(function () use ($request, $deanEmployee, $validated) {
            if ($request->has('faculty_id')) {
                $deanEmployee->update(['faculty_id' => $validated['faculty_id']]);
            }

            $userData = [];
            foreach (['last_name', 'first_name', 'middle_name'] as $field) {
                if ($request->has($field)) {
                    $userData[$field] = $validated[$field];
                }
            }
            if (!empty($userData)) {
                $deanEmployee->user->update($userData);
            }
        });

        return new DeanEmployeeResource($deanEmployee->fresh()->load(['user', 'faculty']));
    }

    public function destroy(DeanEmployee $deanEmployee): JsonResponse
    {
        Gate::authorize('delete', $deanEmployee);

        DB::transaction(fn() => $deanEmployee->user->delete());

        return response()->json(null, 204);
    }
}
