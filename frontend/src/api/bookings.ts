import client from './client'

export interface Booking {
  id: number
  user_id: number
  course_session_id: number
}

export async function bookSession(courseSessionId: number): Promise<Booking> {
  const { data } = await client.post<Booking>('/api/bookings', {
    course_session_id: courseSessionId,
  })
  return data
}

export async function cancelBooking(bookingId: number): Promise<void> {
  await client.delete(`/api/bookings/${bookingId}`)
}
