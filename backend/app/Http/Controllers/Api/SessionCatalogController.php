<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CourseSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionCatalogController extends Controller
{
    /**
     * Public catalog of upcoming scheduled sessions with computed
     * availability and the current user's booking state.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'studio_id' => ['sometimes', 'integer', 'exists:studios,id'],
        ]);

        $from = $data['from'] ?? now()->format('Y-m-d');
        $to = $data['to'] ?? now()->addWeeks(4)->format('Y-m-d');

        $sessions = CourseSession::with(['course', 'room', 'trainer', 'course.leadTrainer'])
            ->withCount('bookings')
            ->whereIn('status', ['scheduled', 'substituted'])
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->when(isset($data['studio_id']), fn ($query) => $query->whereHas(
                'course',
                fn ($q) => $q->where('studio_id', $data['studio_id'])
            ))
            ->orderBy('date')
            ->limit(100)
            ->get();

        $bookingIds = Booking::where('user_id', $request->user()->id)
            ->whereIn('course_session_id', $sessions->pluck('id')->all())
            ->pluck('id', 'course_session_id')
            ->mapWithKeys(fn (int $id, string|int $sessionId): array => [(int) $sessionId => $id]);

        return response()->json($sessions->map(fn (CourseSession $session): array => [
            'id' => $session->id,
            'date' => $session->date->format('Y-m-d'),
            'status' => $session->status,
            'session_info' => $session->session_info,
            'capacity' => $session->effectiveCapacity(),
            'free_spots' => $session->effectiveCapacity() === null
                ? null
                : max(0, $session->effectiveCapacity() - $session->bookings_count),
            'is_substitute' => $session->trainer_id !== null
                && $session->trainer_id !== $session->course->trainer_id,
            'trainer_id' => $session->trainer_id,
            'my_booking_id' => $bookingIds->get($session->id),
            'course' => [
                'id' => $session->course->id,
                'studio_id' => $session->course->studio_id,
                'trainer_id' => $session->course->trainer_id,
                'title' => $session->course->title,
                'start_time' => substr((string) $session->course->start_time, 0, 5),
                'end_time' => substr((string) $session->course->end_time, 0, 5),
            ],
            'room' => $session->room === null ? null : [
                'id' => $session->room->id,
                'name' => $session->room->name,
                'capacity' => $session->room->capacity,
                'size_sqm' => $session->room->size_sqm,
                'lighting' => $session->room->lighting,
            ],
            'trainer' => ($session->trainer ?? $session->course->leadTrainer) === null ? null : [
                'id' => ($session->trainer ?? $session->course->leadTrainer)->id,
                'name' => ($session->trainer ?? $session->course->leadTrainer)->name,
            ],
        ])->all());
    }
}
