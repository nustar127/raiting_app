<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Department::class);

        $query = Department::query()
            ->with('faculty')
            ->withCount('teachers');

        if ($facultyId = $request->query('faculty_id')) {
            $query->where('faculty_id', $facultyId);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('short_name', 'ilike', "%{$search}%");
            });
        }

        $sortBy = $request->query('sort_by', 'name');
        $sortDir = strtolower($request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'short_name', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }
        $query->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return DepartmentResource::collection($query->paginate($perPage));
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Department::class);

        $department = Department::create($request->validated());

        return (new DepartmentResource($department->load('faculty')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Department $department): DepartmentResource
    {
        Gate::authorize('view', $department);

        $department->load('faculty')->loadCount('teachers');

        return new DepartmentResource($department);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        Gate::authorize('update', $department);

        $department->update($request->validated());

        return new DepartmentResource($department->fresh()->load('faculty')->loadCount('teachers'));
    }

    public function destroy(Department $department): JsonResponse
    {
        Gate::authorize('delete', $department);

        if ($department->teachers()->exists()) {
            return response()->json([
                'message' => 'Невозможно удалить кафедру: за ней закреплены преподаватели.',
            ], 409);
        }

        $department->delete();

        return response()->json(null, 204);
    }
}
