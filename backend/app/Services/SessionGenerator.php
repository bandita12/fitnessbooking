<?php

namespace App\Services;

use App\Models\Course;

/**
 * Generates concrete `course_sessions` from a `courses` template.
 *
 * Inheritance: new sessions take `room_id` from `courses.room_id`.
 * Idempotency: dates that already have a session are skipped, so a
 * manually overridden session room is never overwritten by a later
 * generate run (same pattern as the trainer_id/substitute override).
 */
class SessionGenerator
{
    /**
     * Create the sessions for the coming weeks (default 4).
     *
     * @return int Number of sessions created.
     */
    public function generate(Course $course, int $weeks = 4): int
    {
        $created = 0;
        $today = now()->startOfDay();
        for ($offset = 0; $offset < $weeks * 7; $offset++) {
            $date = $today->copy()->addDays($offset);
            if ($date->dayOfWeekIso !== (int) $course->day_of_week) {
                continue;
            }
            if ($course->sessions()->whereDate('date', $date)->exists()) {
                continue;
            }
            $course->sessions()->create([
                'date' => $date->format('Y-m-d'),
                'room_id' => $course->room_id,
            ]);
            $created++;
        }

        return $created;
    }
}
