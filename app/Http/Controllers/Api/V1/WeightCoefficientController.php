<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeightCoefficient\StoreWeightCoefficientRequest;
use App\Http\Requests\WeightCoefficient\UpdateWeightCoefficientRequest;
use App\Http\Resources\WeightCoefficientResource;
use App\Models\WeightCoefficient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WeightCoefficientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', WeightCoefficient::class);

        $query = WeightCoefficient::query()
            ->with(['module', 'workType']);

        if ($facultyScope = $request->attributes->get('faculty_scope')) {
            $query->whereHas('curriculum.group', fn($q) => $q->where('faculty_id', $facultyScope));
        }

        if ($periodId = $request->query('period_id')) {
            $query->where('period_id', $periodId);
        }

        if ($curriculumId = $request->query('curriculum_id')) {
            $query->where('curriculum_id', $curriculumId);
        }

        if ($level = $request->query('level')) {
            $query->where('level', $level);
        }

        if ($moduleId = $request->query('module_id')) {
            $query->where('module_id', $moduleId);
        }

        $query->orderBy('curriculum_id')->orderBy('level')->orderBy('id');

        $perPage = max(1, min((int) $request->query('per_page', 100), 200));

        return WeightCoefficientResource::collection($query->paginate($perPage));
    }

    public function store(StoreWeightCoefficientRequest $request): JsonResponse
    {
        Gate::authorize('create', WeightCoefficient::class);

        $weight = WeightCoefficient::create($request->validated());

        return (new WeightCoefficientResource($weight->load(['module', 'workType'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(WeightCoefficient $weight): WeightCoefficientResource
    {
        Gate::authorize('view', $weight);

        $weight->load(['module', 'workType']);

        return new WeightCoefficientResource($weight);
    }

    public function update(UpdateWeightCoefficientRequest $request, WeightCoefficient $weight): WeightCoefficientResource
    {
        Gate::authorize('update', $weight);

        $weight->update($request->validated());

        return new WeightCoefficientResource($weight->fresh()->load(['module', 'workType']));
    }

    public function destroy(WeightCoefficient $weight): JsonResponse
    {
        Gate::authorize('delete', $weight);

        if ($weight->isApproved()) {
            return response()->json([
                'message' => 'Нельзя удалить утверждённый вес.',
            ], 409);
        }

        if ($weight->period?->isClosed()) {
            return response()->json([
                'message' => 'Нельзя удалить вес закрытого периода.',
            ], 409);
        }

        $weight->delete();

        return response()->json(null, 204);
    }
}
