<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discipline\StoreDisciplineRequest;
use App\Http\Requests\Discipline\UpdateDisciplineRequest;
use App\Http\Resources\DisciplineResource;
use App\Models\Discipline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DisciplineController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Discipline::class);

        $query = Discipline::query()->with('department');

        if ($departmentId = $request->query('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($facultyId = $request->query('faculty_id')) {
            $query->whereHas('department', fn($q) => $q->where('faculty_id', $facultyId));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        $sortBy = $request->query('sort_by', 'name');
        $sortDir = strtolower($request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'code', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }
        $query->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return DisciplineResource::collection($query->paginate($perPage));
    }

    public function store(StoreDisciplineRequest $request): JsonResponse
    {
        Gate::authorize('create', Discipline::class);

        $discipline = Discipline::create($request->validated());

        return (new DisciplineResource($discipline->load('department')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Discipline $discipline): DisciplineResource
    {
        Gate::authorize('view', $discipline);

        $discipline->load('department');

        return new DisciplineResource($discipline);
    }

    public function update(UpdateDisciplineRequest $request, Discipline $discipline): DisciplineResource
    {
        Gate::authorize('update', $discipline);

        $discipline->update($request->validated());

        return new DisciplineResource($discipline->fresh()->load('department'));
    }

    public function destroy(Discipline $discipline): JsonResponse
    {
        Gate::authorize('delete', $discipline);

        // TODO(3.9): раскомментировать, когда появится Curriculum
        // if ($discipline->curricula()->exists()) {
        //     return response()->json([
        //         'message' => 'Невозможно удалить дисциплину: она используется в учебных планах.',
        //     ], 409);
        // }

        $discipline->delete();

        return response()->json(null, 204);
    }
}
