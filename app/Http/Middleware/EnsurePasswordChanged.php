<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Разрешаем доступ к смене пароля, логауту и me, иначе получится замкнутый круг.
        $allowedRoutes = [
            'api/v1/auth/change-password',
            'api/v1/auth/logout',
            'api/v1/auth/me',
        ];

        if (
            $user && $user->must_change_password
            && !in_array($request->path(), $allowedRoutes, true)
        ) {
            return response()->json([
                'message' => 'Требуется смена временного пароля.',
                'code' => 'password_change_required',
            ], 423); // 423 Locked
        }

        return $next($request);
    }
}
