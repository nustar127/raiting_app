<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Module\StoreModuleRequest;
use App\Http\Requests\Module\UpdateModuleRequest;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ModuleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Module::class);

        $query = Module::query()->with('curriculum');

        // Scoping для декана
        if ($facultyScope = $request->attributes->get('faculty_scope')) {
            $query->whereHas('curriculum.group', fn($q) => $q->where('faculty_id', $facultyScope));
        }

        if ($curriculumId = $request->query('curriculum_id')) {
            $query->where('curriculum_id', $curriculumId);
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $query->orderBy('curriculum_id')->orderBy('order_number');

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return ModuleResource::collection($query->paginate($perPage));
    }

    public function store(StoreModuleRequest $request): JsonResponse
    {
        Gate::authorize('create', Module::class);

        $module = Module::create($request->validated());

        return (new ModuleResource($module->load('curriculum')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Module $module): ModuleResource
    {
        Gate::authorize('view', $module);

        $module->load('curriculum');

        return new ModuleResource($module);
    }

    public function update(UpdateModuleRequest $request, Module $module): ModuleResource
    {
        Gate::authorize('update', $module);

        $module->update($request->validated());

        return new ModuleResource($module->fresh()->load('curriculum'));
    }

    public function destroy(Module $module): JsonResponse
    {
        Gate::authorize('delete', $module);

        // TODO(5): проверить grades, когда появится модель Grade
        $module->delete();

        return response()->json(null, 204);
    }
}
