<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFacultyScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Не аутентифицирован.'], 401);
        }

        if ($user->isDean()) {
            $dean = $user->deanEmployee;

            if (!$dean) {
                return response()->json([
                    'message' => 'Сотрудник деканата не привязан к факультету.',
                ], 403);
            }

            // Кладём в request — контроллеры будут читать это значение
            // и автоматически ограничивать выборку.
            $request->attributes->set('faculty_scope', $dean->faculty_id);
        }

        return $next($request);
    }
}
