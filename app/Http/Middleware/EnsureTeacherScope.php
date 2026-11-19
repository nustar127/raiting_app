<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeacherScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Не аутентифицирован.'], 401);
        }

        if ($user->isTeacher()) {
            $teacher = $user->teacher;

            if (!$teacher) {
                return response()->json([
                    'message' => 'Преподаватель не привязан к кафедре.',
                ], 403);
            }

            $request->attributes->set('teacher_scope', $teacher->id);
            $request->attributes->set('department_scope', $teacher->department_id);
        }

        return $next($request);
    }
}
