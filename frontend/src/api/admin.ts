import type { AuthUser, UserRole } from './auth'
import client from './client'

export interface NewStudioAdmin {
  name: string
  email: string
  password: string
  studio_id: number
}

export interface ManagedUser {
  id: number
  name: string
  email: string
  role: UserRole
  studio_id: number | null
}

export interface UserUpdate {
  name?: string
  email?: string
  role?: UserRole
  studio_id?: number | null
  password?: string
}

export async function createStudioAdmin(
  admin: NewStudioAdmin,
): Promise<AuthUser> {
  const { data } = await client.post<AuthUser>('/api/admin/users', admin)
  return data
}

export async function listUsers(role?: UserRole): Promise<ManagedUser[]> {
  const { data } = await client.get<ManagedUser[]>('/api/admin/users', {
    params: role === undefined ? {} : { role },
  })
  return data
}

export async function updateUser(
  userId: number,
  update: UserUpdate,
): Promise<ManagedUser> {
  const { data } = await client.put<ManagedUser>(
    `/api/admin/users/${userId}`,
    update,
  )
  return data
}

export async function deleteUser(userId: number): Promise<void> {
  await client.delete(`/api/admin/users/${userId}`)
}
