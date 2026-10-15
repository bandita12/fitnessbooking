<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseSessionController;
use App\Http\Controllers\Api\FitnessTestController;
use App\Http\Controllers\Api\StudioController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/fitness-test', FitnessTestController::class);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/studios', [StudioController::class, 'index'])->can('courses.view');
    Route::post('/admin/studios', [StudioController::class, 'store'])->can('studios.manage');
    Route::put('/admin/studios/{studio}', [StudioController::class, 'update'])->can('studios.manage');
    Route::delete('/admin/studios/{studio}', [StudioController::class, 'destroy'])->can('studios.manage');
    Route::put('/studios/{studio}/details', [StudioController::class, 'update'])->can('studios.update');

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
