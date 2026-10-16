<?php

use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseSessionController;
use App\Http\Controllers\Api\FitnessTestController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\StudioController;
use App\Http\Controllers\Api\StudioTrainerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/fitness-test', FitnessTestController::class);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('web')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/studios', [StudioController::class, 'index'])->can('courses.view');
    Route::get('/admin/studios', [StudioController::class, 'index'])->can('studios.manage');
    Route::post('/admin/studios', [StudioController::class, 'store'])->can('studios.manage');
    Route::post('/admin/users', [AdminUserController::class, 'store'])->can('users.assign-roles');
    Route::put('/admin/studios/{studio}', [StudioController::class, 'update'])->can('studios.manage');
    Route::delete('/admin/studios/{studio}', [StudioController::class, 'destroy'])->can('studios.manage');
    Route::put('/studios/{studio}/details', [StudioController::class, 'update'])->can('studios.update');

    Route::get('/studios/{studio}/rooms', [RoomController::class, 'index'])->can('courses.view');
    Route::post('/studios/{studio}/rooms', [RoomController::class, 'store'])->can('rooms.manage');
    Route::put('/studios/{studio}/rooms/{room}', [RoomController::class, 'update'])->can('rooms.manage');
    Route::delete('/studios/{studio}/rooms/{room}', [RoomController::class, 'destroy'])->can('rooms.manage');

    Route::get('/studios/{studio}/trainers', [StudioTrainerController::class, 'index'])->can('courses.view');
    Route::post('/studios/{studio}/trainers', [StudioTrainerController::class, 'store'])->can('courses.manage');
    Route::post('/studios/{studio}/trainers/attach', [StudioTrainerController::class, 'attach'])->can('courses.manage');
    Route::delete('/studios/{studio}/trainers/{trainer}', [StudioTrainerController::class, 'destroy'])->can('courses.manage');

    Route::get('/studios/{studio}/courses', [CourseController::class, 'index'])->can('courses.view');
    Route::post('/studios/{studio}/courses', [CourseController::class, 'store'])->can('courses.manage');
    Route::put('/studios/{studio}/courses/{course}', [CourseController::class, 'update'])->can('courses.manage');
    Route::delete('/studios/{studio}/courses/{course}', [CourseController::class, 'destroy'])->can('courses.manage');

    Route::get('/studios/{studio}/courses/{course}/sessions', [CourseSessionController::class, 'index'])->can('courses.view');
    Route::post('/studios/{studio}/courses/{course}/sessions', [CourseSessionController::class, 'store'])->can('courses.manage');
    Route::delete('/studios/{studio}/courses/{course}/sessions/{session}', [CourseSessionController::class, 'destroy'])->can('courses.manage');

    Route::get('/bookings', [BookingController::class, 'index'])->can('bookings.manage');
    Route::post('/bookings', [BookingController::class, 'store'])->can('bookings.manage');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->can('bookings.manage');
});
