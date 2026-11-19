<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Faculty\StoreFacultyRequest;
use App\Http\Requests\Faculty\UpdateFacultyRequest;
use App\Http\Resources\FacultyResource;
use App\Models\Faculty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FacultyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Faculty::class);

        $query = Faculty::query()->withCount('departments');

        // Поиск по name/short_name
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('short_name', 'ilike', "%{$search}%");
            });
        }

        // Сортировка (whitelist)
        $sortBy = $request->query('sort_by', 'name');
        $sortDir = strtolower($request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'short_name', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }
        $query->orderBy($sortBy, $sortDir);

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        return FacultyResource::collection($query->paginate($perPage));
    }

    public function store(StoreFacultyRequest $request): JsonResponse
    {
        Gate::authorize('create', Faculty::class);

        $faculty = Faculty::create($request->validated());

        return (new FacultyResource($faculty))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Faculty $faculty): FacultyResource
    {
        Gate::authorize('view', $faculty);

        $faculty->loadCount('departments');

        return new FacultyResource($faculty);
    }

    public function update(UpdateFacultyRequest $request, Faculty $faculty): FacultyResource
    {
        Gate::authorize('update', $faculty);

        $faculty->update($request->validated());

        return new FacultyResource($faculty->fresh()->loadCount('departments'));
    }

    public function destroy(Faculty $faculty): JsonResponse
    {
        Gate::authorize('delete', $faculty);

        if ($faculty->departments()->exists()) {
            return response()->json([
                'message' => 'Невозможно удалить факультет: есть связанные кафедры.',
            ], 409);
        }

        if ($faculty->deanEmployees()->exists()) {
            return response()->json([
                'message' => 'Невозможно удалить факультет: за ним закреплены сотрудники деканата.',
            ], 409);
        }

        $faculty->delete();

        return response()->json(null, 204);
    }
}
