<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CourseSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SessionCatalogController extends Controller
{
    /**
     * Public catalog of upcoming scheduled sessions with computed
     * availability and the current user's booking state.
     *
     * Cancelled sessions are excluded by default (booking view) and only
     * included with `include_cancelled=1` (calendar view, greyed out).
     * `room_id` matches the session's effective room (session room with
     * fallback to the course room).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'studio_id' => ['sometimes', 'integer', 'exists:studios,id'],
            'room_id' => ['sometimes', 'integer', 'exists:rooms,id'],
            'include_cancelled' => ['sometimes', 'boolean'],
        ]);

        $from = $data['from'] ?? now()->format('Y-m-d');
        $to = $data['to'] ?? now()->addWeeks(4)->format('Y-m-d');

        $sessions = CourseSession::with(['course', 'course.room', 'room', 'trainer', 'course.leadTrainer'])
            ->withCount('bookings')
            ->when(
                ! ($data['include_cancelled'] ?? false),
                fn ($query) => $query->whereIn('status', ['scheduled', 'substituted'])
            )
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->when(isset($data['studio_id']), fn ($query) => $query->whereHas(
                'course',
                fn ($q) => $q->where('studio_id', $data['studio_id'])
            ))
            ->when(isset($data['room_id']), fn ($query) => $query->where(
                fn ($q) => $q
                    ->where('course_sessions.room_id', $data['room_id'])
                    ->orWhere(
                        fn ($qq) => $qq->whereNull('course_sessions.room_id')->whereHas(
                            'course',
                            fn ($c) => $c->where('room_id', $data['room_id'])
                        )
                    )
            ))
            ->orderBy('date')
            ->limit(100)
            ->get();

        $bookingIds = Booking::where('user_id', $request->user()->id)
            ->whereIn('course_session_id', $sessions->pluck('id')->all())
            ->pluck('id', 'course_session_id')
            ->mapWithKeys(fn (int $id, string|int $sessionId): array => [(int) $sessionId => $id]);

        return response()->json($sessions->map(
            fn (CourseSession $session): array => $this->toCatalogArray($session, $bookingIds)
        )->all());
    }

    /**
     * Sessions the current user has booked (all statuses, including
     * cancelled ones), in catalog shape. Powers the personalized calendar.
     */
    public function mySessions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        $userId = $request->user()->id;
        $from = $data['from'] ?? now()->format('Y-m-d');
        $to = $data['to'] ?? now()->addWeeks(4)->format('Y-m-d');

        $sessions = CourseSession::with(['course', 'course.room', 'room', 'trainer', 'course.leadTrainer'])
            ->withCount('bookings')
            ->whereHas('bookings', fn ($query) => $query->where('user_id', $userId))
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->limit(100)
            ->get();

        $bookingIds = Booking::where('user_id', $userId)
            ->whereIn('course_session_id', $sessions->pluck('id')->all())
            ->pluck('id', 'course_session_id')
            ->mapWithKeys(fn (int $id, string|int $sessionId): array => [(int) $sessionId => $id]);

        return response()->json($sessions->map(
            fn (CourseSession $session): array => $this->toCatalogArray($session, $bookingIds)
        )->all());
    }

    /**
     * @param Collection<int, int> $bookingIds Maps session id → booking id.
     * @return array<string, mixed>
     */
    private function toCatalogArray(CourseSession $session, Collection $bookingIds): array
    {
        $effectiveRoom = $session->room ?? $session->course->room;

        return [
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
            'room' => $effectiveRoom === null ? null : [
                'id' => $effectiveRoom->id,
                'name' => $effectiveRoom->name,
                'capacity' => $effectiveRoom->capacity,
                'size_sqm' => $effectiveRoom->size_sqm,
                'lighting' => $effectiveRoom->lighting,
            ],
            'trainer' => ($session->trainer ?? $session->course->leadTrainer) === null ? null : [
                'id' => ($session->trainer ?? $session->course->leadTrainer)->id,
                'name' => ($session->trainer ?? $session->course->leadTrainer)->name,
            ],
        ];
    }
}
