import type { AuthUser } from './auth'
import client from './client'

export interface NewStudioAdmin {
  name: string
  email: string
  password: string
  studio_id: number
}

export async function createStudioAdmin(
  admin: NewStudioAdmin,
): Promise<AuthUser> {
  const { data } = await client.post<AuthUser>('/api/admin/users', admin)
  return data
}
