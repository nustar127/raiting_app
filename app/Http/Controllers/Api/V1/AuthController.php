<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->attempt(
            $request->validated('login'),
            $request->validated('password')
        );

        // Один активный токен на устройство.
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
            'must_change_password' => $user->must_change_password,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('role')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Удаляем текущий токен
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Выход выполнен.']);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('new_password')
        );

        return response()->json([
            'message' => 'Пароль изменён. Выполните повторный вход.',
        ]);
    }
}
