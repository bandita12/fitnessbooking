<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CourseSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Booking::visibleTo($request->user())->with('session')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_session_id' => ['required', 'integer', 'exists:course_sessions,id'],
        ]);

        $session = CourseSession::findOrFail($data['course_session_id']);

        if ($session->status === 'cancelled') {
            return response()->json(['message' => 'Termin ist nicht buchbar.'], 422);
        }

        if (Booking::where('user_id', $request->user()->id)
            ->where('course_session_id', $session->id)->exists()) {
            return response()->json(['message' => 'Termin bereits gebucht.'], 422);
        }

        $capacity = $session->effectiveCapacity();
        if ($capacity !== null && $session->bookings()->count() >= $capacity) {
            return response()->json(['message' => 'Kurs ausgebucht.'], 422);
        }

        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'course_session_id' => $session->id,
        ]);

        return response()->json($booking, 201);
    }

    public function destroy(Request $request, int $booking): JsonResponse
    {
        $model = Booking::visibleTo($request->user())->findOrFail($booking);
        $this->authorize('delete', $model);

        if (! $model->isCancellable()) {
            return response()->json(['message' => 'Stornofrist abgelaufen (bis 2 Stunden vor Beginn).'], 422);
        }

        $model->delete();

        return response()->json(null, 204);
    }
}
