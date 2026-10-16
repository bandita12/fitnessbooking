import client from './client'

export interface Studio {
  id: number
  name: string
  street: string
  zip: string
  city: string
  description: string | null
  thumbnail_url: string | null
  image_url: string | null
  web_link: string | null
  /**
   * Additive sort helpers from the studio list (calendar dropdowns).
   * Optional because older responses and other endpoints may omit them.
   */
  trainers_count?: number
  customers_count?: number
}

export interface NewStudio {
  name: string
  street: string
  zip: string
  city: string
  description?: string
  thumbnail_url?: string
  image_url?: string
  web_link?: string
}

export async function listStudios(): Promise<Studio[]> {
  const { data } = await client.get<Studio[]>('/api/admin/studios')
  return data
}

export async function listAllStudios(): Promise<Studio[]> {
  const { data } = await client.get<Studio[]>('/api/studios')
  return data
}

export async function createStudio(studio: NewStudio): Promise<Studio> {
  const { data } = await client.post<Studio>('/api/admin/studios', studio)
  return data
}

export async function updateStudio(
  studioId: number,
  studio: Partial<NewStudio>,
): Promise<Studio> {
  const { data } = await client.put<Studio>(
    `/api/admin/studios/${studioId}`,
    studio,
  )
  return data
}

export async function deleteStudio(studioId: number): Promise<void> {
  await client.delete(`/api/admin/studios/${studioId}`)
}
