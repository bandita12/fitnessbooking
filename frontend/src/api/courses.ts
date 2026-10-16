import client from './client'
import type { CourseSession } from './sessions'

export interface Course {
  id: number
  studio_id: number
  trainer_id: number | null
  title: string
  description: string | null
  thumbnail_url: string | null
  image_url: string | null
  web_link: string | null
  day_of_week: number
  start_time: string
  end_time: string
  capacity: number | null
}

export interface NewCourse {
  title: string
  description?: string
  day_of_week: number
  start_time: string
  end_time: string
  trainer_id?: number | null
  thumbnail_url?: string
  image_url?: string
  web_link?: string
  capacity?: number | null
}

export interface GenerateResult {
  created: number
  sessions: CourseSession[]
}

const weekdays = [
  'Montag',
  'Dienstag',
  'Mittwoch',
  'Donnerstag',
  'Freitag',
  'Samstag',
  'Sonntag',
]

export function weekdayName(dayOfWeek: number): string {
  return weekdays[dayOfWeek - 1] ?? `Tag ${dayOfWeek}`
}

export async function listCourses(studioId: number): Promise<Course[]> {
  const { data } = await client.get<Course[]>(`/api/studios/${studioId}/courses`)
  return data
}

export async function createCourse(
  studioId: number,
  course: NewCourse,
): Promise<Course> {
  const { data } = await client.post<Course>(
    `/api/studios/${studioId}/courses`,
    course,
  )
  return data
}

export async function generateSessions(
  studioId: number,
  courseId: number,
  weeks = 4,
): Promise<GenerateResult> {
  const { data } = await client.post<GenerateResult>(
    `/api/studios/${studioId}/courses/${courseId}/sessions/generate`,
    { weeks },
  )
  return data
}
