import client from './client'

export interface Room {
  id: number
  studio_id: number
  name: string
  capacity: number
  description: string | null
  thumbnail_url: string | null
  image_url: string | null
  color_code: string | null
  location_type: string
  lighting: string | null
  size_sqm: number | null
  width_meters: string | null
  length_meters: string | null
  height_meters: string | null
}

export interface NewRoom {
  name: string
  capacity: number
  description?: string
  thumbnail_url?: string
  image_url?: string
  color_code?: string
  location_type?: string
  lighting?: string
  size_sqm?: number
  width_meters?: string
  length_meters?: string
  height_meters?: string
}

export async function listRooms(studioId: number): Promise<Room[]> {
  const { data } = await client.get<Room[]>(`/api/studios/${studioId}/rooms`)
  return data
}

export async function createRoom(
  studioId: number,
  room: NewRoom,
): Promise<Room> {
  const { data } = await client.post<Room>(
    `/api/studios/${studioId}/rooms`,
    room,
  )
  return data
}

export async function updateRoom(
  studioId: number,
  roomId: number,
  room: Partial<NewRoom>,
): Promise<Room> {
  const { data } = await client.put<Room>(
    `/api/studios/${studioId}/rooms/${roomId}`,
    room,
  )
  return data
}

export async function deleteRoom(
  studioId: number,
  roomId: number,
): Promise<void> {
  await client.delete(`/api/studios/${studioId}/rooms/${roomId}`)
}
