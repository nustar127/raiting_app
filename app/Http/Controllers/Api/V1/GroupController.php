<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\StoreGroupRequest;
use App\Http\Requests\Group\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class GroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Group::class);

        $query = Group::query()
            ->with('faculty')
            ->withCount('students');

        if ($facultyId = $request->query('faculty_id')) {
            $query->where('faculty_id', $facultyId);
        }

        if ($course = $request->query('course')) {
            // курс → enrollment_year: year = current_year - course + 1
            $targetYear = (int) date('Y') - (int) $course + 1;
            $query->where('enrollment_year', $targetYear);
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $sortBy = $request->query('sort_by', 'name');
        $sortDir = strtolower($request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'enrollment_year', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }
        $query->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return GroupResource::collection($query->paginate($perPage));
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        Gate::authorize('create', Group::class);

        $group = Group::create($request->validated());

        return (new GroupResource($group->load('faculty')->loadCount('students')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Group $group): GroupResource
    {
        Gate::authorize('view', $group);

        $group->load('faculty')->loadCount('students');

        return new GroupResource($group);
    }

    public function update(UpdateGroupRequest $request, Group $group): GroupResource
    {
        Gate::authorize('update', $group);

        $group->update($request->validated());

        return new GroupResource($group->fresh()->load('faculty')->loadCount('students'));
    }

    public function destroy(Group $group): JsonResponse
    {
        Gate::authorize('delete', $group);

        if ($group->students()->exists()) {
            return response()->json([
                'message' => 'Невозможно удалить группу: в ней есть студенты.',
            ], 409);
        }

        $group->delete();

        return response()->json(null, 204);
    }
}
