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

export async function createStudio(studio: NewStudio): Promise<Studio> {
  const { data } = await client.post<Studio>('/api/admin/studios', studio)
  return data
}
