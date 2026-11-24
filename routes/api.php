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
use App\Http\Controllers\Api\V1\CurriculumController;
use App\Http\Controllers\Api\V1\ModuleController;
use App\Http\Controllers\Api\V1\RatingPeriodController;
use App\Http\Controllers\Api\V1\WeightCoefficientController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\TeacherAssignmentController;
use App\Http\Controllers\Api\V1\GradeCommentController;
use App\Http\Controllers\Api\V1\RatingController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ImportController;

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
        Route::apiResource('curricula', CurriculumController::class)
            ->middleware('faculty.scope');
        Route::apiResource('modules', ModuleController::class)
            ->middleware('faculty.scope');
        Route::apiResource('periods', RatingPeriodController::class)
            ->parameters(['periods' => 'period'])
            ->middleware('faculty.scope');

        Route::post('periods/{period}/open', [RatingPeriodController::class, 'open'])
            ->name('periods.open');
        Route::post('periods/{period}/close', [RatingPeriodController::class, 'close'])
            ->name('periods.close');

        Route::apiResource('weights', WeightCoefficientController::class)
            ->parameters(['weights' => 'weight'])
            ->middleware('faculty.scope');

        Route::post('periods/{period}/weights/approve', [RatingPeriodController::class, 'approveWeights'])
            ->name('periods.weights.approve');

        Route::post('grades/bulk', [GradeController::class, 'bulk'])
            ->name('grades.bulk');

        Route::apiResource('grades', GradeController::class);
        Route::apiResource('teacher-assignments', TeacherAssignmentController::class)
            ->parameters(['teacher-assignments' => 'teacherAssignment'])
            ->except(['update'])
            ->middleware(['faculty.scope', 'teacher.scope']);

        Route::get('grades/{grade}/comments', [GradeCommentController::class, 'indexForGrade'])
            ->name('grades.comments.index');
        Route::post('grades/{grade}/comments', [GradeCommentController::class, 'store'])
            ->name('grades.comments.store');

        Route::apiResource('comments', GradeCommentController::class)
            ->parameters(['comments' => 'comment'])
            ->only(['show', 'update', 'destroy'])
            ->middleware('teacher.scope');

        Route::prefix('ratings')->group(function () {
            Route::get('students/{student}', [RatingController::class, 'student']);
            Route::get('groups/{group}', [RatingController::class, 'group']);
            Route::get('faculties/{facultyId}', [RatingController::class, 'faculty']);
            Route::get('top', [RatingController::class, 'top']);
        });

        Route::prefix('dashboard')->group(function () {
            Route::get('summary', [DashboardController::class, 'summary'])
                ->middleware('faculty.scope');
            Route::get('faculty/{faculty}', [DashboardController::class, 'faculty'])
                ->middleware('faculty.scope');
            Route::get('group/{group}', [DashboardController::class, 'group'])
                ->middleware('faculty.scope');
        });

        Route::prefix('reports')->group(function () {
            Route::get('rating-list', [ReportController::class, 'ratingList'])
                ->middleware('faculty.scope');
            Route::get('student-card', [ReportController::class, 'studentCard'])
                ->middleware('faculty.scope');
            Route::get('period-summary', [ReportController::class, 'periodSummary'])
                ->middleware('faculty.scope');
            Route::get('{type}/export', [ReportController::class, 'export'])
                ->middleware('faculty.scope')
                ->whereIn('type', ['rating-list', 'student-card', 'period-summary']);
        });

        Route::prefix('import')->group(function () {
            Route::post('users', [ImportController::class, 'users']);
            Route::get('users/template', [ImportController::class, 'usersTemplate']);
            Route::get('grades/template', [ImportController::class, 'gradesTemplate']);
            Route::post('grades', [ImportController::class, 'grades']);
        });
    });
});
