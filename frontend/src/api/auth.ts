import axios from 'axios'
import client, { getCsrfCookie } from './client'

export type UserRole = 'admin' | 'studio_admin' | 'trainer' | 'customer'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: UserRole
  studio_id: number | null
}

export interface LoginCredentials {
  email: string
  password: string
}

export async function loginRequest(
  credentials: LoginCredentials,
): Promise<AuthUser> {
  await getCsrfCookie()
  const { data } = await client.post<AuthUser>(
    '/api/auth/login',
    credentials,
  )
  return data
}

export async function logoutRequest(): Promise<void> {
  await client.post('/api/auth/logout')
}

export async function fetchMe(): Promise<AuthUser | null> {
  try {
    const { data } = await client.get<AuthUser>('/api/me')
    return data
  } catch (error: unknown) {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      return null
    }
    throw error
  }
}
