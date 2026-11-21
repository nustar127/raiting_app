<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GradeComment\StoreGradeCommentRequest;
use App\Http\Requests\GradeComment\UpdateGradeCommentRequest;
use App\Http\Resources\GradeCommentResource;
use App\Models\Grade;
use App\Models\GradeComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class GradeCommentController extends Controller
{
    /**
     * Все комментарии к оценке.
     */
    public function indexForGrade(Request $request, Grade $grade): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', GradeComment::class);

        // Проверяем, что пользователь имеет право видеть саму оценку
        Gate::authorize('view', $grade);

        $comments = GradeComment::where('grade_id', $grade->id)
            ->with('teacher.user')
            ->orderBy('created_at')
            ->get();

        return GradeCommentResource::collection($comments);
    }

    /**
     * Создать комментарий к оценке.
     */
    public function store(StoreGradeCommentRequest $request, Grade $grade): JsonResponse
    {
        Gate::authorize('create', GradeComment::class);

        $comment = GradeComment::create([
            'grade_id' => $grade->id,
            'teacher_id' => $request->user()->teacher->id,
            'comment_text' => $request->validated('comment_text'),
        ]);

        return (new GradeCommentResource($comment->load('teacher.user')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(GradeComment $comment): GradeCommentResource
    {
        Gate::authorize('view', $comment);

        $comment->load('teacher.user');

        return new GradeCommentResource($comment);
    }

    public function update(UpdateGradeCommentRequest $request, GradeComment $comment): GradeCommentResource
    {
        Gate::authorize('update', $comment);

        $comment->update($request->validated());

        return new GradeCommentResource($comment->fresh()->load('teacher.user'));
    }

    public function destroy(GradeComment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->json(null, 204);
    }
}
