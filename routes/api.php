<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\FacultyController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\DisciplineController;
use App\Http\Controllers\Api\V1\WorkTypeController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\DeanEmployeeController;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn() => response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]));

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1'); // 10 попыток в минуту

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/change-password', [AuthController::class, 'changePassword'])
                ->middleware('password.changed');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('faculties', FacultyController::class);
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('groups', GroupController::class);
        Route::apiResource('disciplines', DisciplineController::class);
        Route::apiResource('work-types', WorkTypeController::class);
        Route::apiResource('students', StudentController::class)
            ->middleware('faculty.scope');
        Route::apiResource('teachers', TeacherController::class)
            ->middleware('faculty.scope');
        Route::apiResource('dean-employees', DeanEmployeeController::class)
            ->middleware('faculty.scope')
            ->parameters(['dean-employees' => 'deanEmployee']);
    });
});
