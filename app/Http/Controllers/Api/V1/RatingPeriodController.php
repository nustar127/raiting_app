<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RatingPeriod\StoreRatingPeriodRequest;
use App\Http\Requests\RatingPeriod\UpdateRatingPeriodRequest;
use App\Http\Resources\RatingPeriodResource;
use App\Models\RatingPeriod;
use App\Services\Rating\PeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use App\Services\Rating\WeightService;

class RatingPeriodController extends Controller
{
    public function __construct(
        private readonly PeriodService $periodService,
        private readonly WeightService $weightService,
    ) {}

    public function approveWeights(Request $request, RatingPeriod $period): JsonResponse
    {
        // Только декан своего факультета или админ
        $user = $request->user();
        $allowed = $user->isAdmin()
            || ($user->isDean() && $user->deanEmployee?->faculty_id === $period->faculty_id);

        if (!$allowed) {
            return response()->json(['message' => 'Недостаточно прав.'], 403);
        }

        if ($period->isClosed()) {
            return response()->json(['message' => 'Период закрыт — утверждать веса нельзя.'], 409);
        }

        try {
            $this->weightService->approveAll($period, $user->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Не удалось утвердить веса.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json(['message' => 'Веса утверждены.']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', RatingPeriod::class);

        $query = RatingPeriod::query()->with('faculty');

        // Scoping для декана
        if ($facultyScope = $request->attributes->get('faculty_scope')) {
            $query->where('faculty_id', $facultyScope);
        }

        if ($facultyId = $request->query('faculty_id')) {
            $query->where('faculty_id', $facultyId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($year = $request->query('academic_year')) {
            $query->where('academic_year', $year);
        }

        if ($semester = $request->query('semester')) {
            $query->where('semester', $semester);
        }

        $query->orderByDesc('academic_year')->orderByDesc('semester');

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return RatingPeriodResource::collection($query->paginate($perPage));
    }

    public function store(StoreRatingPeriodRequest $request): JsonResponse
    {
        Gate::authorize('create', RatingPeriod::class);

        $period = RatingPeriod::create($request->validated());

        return (new RatingPeriodResource($period->fresh()->load('faculty')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RatingPeriod $period): RatingPeriodResource
    {
        Gate::authorize('view', $period);

        $period->load('faculty');

        return new RatingPeriodResource($period);
    }

    public function update(UpdateRatingPeriodRequest $request, RatingPeriod $period): RatingPeriodResource
    {
        Gate::authorize('update', $period);

        $period->update($request->validated());

        return new RatingPeriodResource($period->fresh()->load('faculty'));
    }

    public function destroy(RatingPeriod $period): JsonResponse
    {
        Gate::authorize('delete', $period);

        $period->delete();

        return response()->json(null, 204);
    }

    public function open(RatingPeriod $period): RatingPeriodResource|JsonResponse
    {
        Gate::authorize('open', $period);

        try {
            $this->periodService->open($period);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Не удалось открыть период.',
                'errors' => $e->errors(),
            ], 422);
        }

        return new RatingPeriodResource($period->fresh()->load('faculty'));
    }

    public function close(RatingPeriod $period): RatingPeriodResource|JsonResponse
    {
        Gate::authorize('close', $period);

        try {
            $this->periodService->close($period);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Не удалось закрыть период.',
                'errors' => $e->errors(),
            ], 422);
        }

        return new RatingPeriodResource($period->fresh()->load('faculty'));
    }
}
