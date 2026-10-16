import client from './client'

export type SessionStatus = 'scheduled' | 'cancelled' | 'substituted'

export interface CourseSession {
  id: number
  course_id: number
  room_id: number | null
  trainer_id: number | null
  date: string
  status: SessionStatus
  session_info: string | null
  capacity: number | null
}

export interface SessionUpdate {
  trainer_id?: number | null
  status?: SessionStatus
  session_info?: string | null
}

export interface CatalogCourse {
  id: number
  title: string
  start_time: string
  end_time: string
}

export interface CatalogRoom {
  id: number
  name: string
  capacity: number
  size_sqm: number | null
  lighting: string | null
}

export interface CatalogTrainer {
  id: number
  name: string
}

export interface CatalogSession {
  id: number
  date: string
  status: SessionStatus
  session_info: string | null
  capacity: number | null
  free_spots: number | null
  is_substitute: boolean
  my_booking_id: number | null
  course: CatalogCourse
  room: CatalogRoom | null
  trainer: CatalogTrainer | null
}

export async function listCatalog(): Promise<CatalogSession[]> {
  const { data } = await client.get<CatalogSession[]>('/api/course_sessions')
  return data
}

export async function listSessions(
  studioId: number,
  courseId: number,
): Promise<CourseSession[]> {
  const { data } = await client.get<CourseSession[]>(
    `/api/studios/${studioId}/courses/${courseId}/sessions`,
  )
  return data
}

export async function updateSession(
  studioId: number,
  courseId: number,
  sessionId: number,
  update: SessionUpdate,
): Promise<CourseSession> {
  const { data } = await client.put<CourseSession>(
    `/api/studios/${studioId}/courses/${courseId}/sessions/${sessionId}`,
    update,
  )
  return data
}
