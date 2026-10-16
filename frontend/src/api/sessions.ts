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
