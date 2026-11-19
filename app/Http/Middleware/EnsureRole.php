<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Использование: ->middleware('role:admin') или 'role:admin,dean'
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Не аутентифицирован.'], 401);
        }

        if (!$user->role || !in_array($user->role->code, $roles, true)) {
            return response()->json([
                'message' => 'Недостаточно прав для выполнения операции.',
                'required_roles' => $roles,
            ], 403);
        }

        return $next($request);
    }
}
