<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkType\StoreWorkTypeRequest;
use App\Http\Requests\WorkType\UpdateWorkTypeRequest;
use App\Http\Resources\WorkTypeResource;
use App\Models\WorkType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', WorkType::class);

        $query = WorkType::query();

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

        return WorkTypeResource::collection($query->paginate($perPage));
    }

    public function store(StoreWorkTypeRequest $request): JsonResponse
    {
        Gate::authorize('create', WorkType::class);

        $workType = WorkType::create($request->validated());

        return (new WorkTypeResource($workType))
            ->response()
            ->setStatusCode(201);
    }

    public function show(WorkType $workType): WorkTypeResource
    {
        Gate::authorize('view', $workType);

        return new WorkTypeResource($workType);
    }

    public function update(UpdateWorkTypeRequest $request, WorkType $workType): WorkTypeResource
    {
        Gate::authorize('update', $workType);

        $workType->update($request->validated());

        return new WorkTypeResource($workType->fresh());
    }

    public function destroy(WorkType $workType): JsonResponse
    {
        Gate::authorize('delete', $workType);

        // TODO(3.9/5): проверить использование в weight_coefficients и grades
        $workType->delete();

        return response()->json(null, 204);
    }
}
