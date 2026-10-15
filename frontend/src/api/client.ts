import axios, { type AxiosInstance } from 'axios'

const apiBaseUrl =
  import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000'

/**
 * Canonical Sanctum SPA client. `withCredentials` sends the session cookie,
 * `withXSRFToken` attaches the XSRF token. Base URL has no `/api` suffix on
 * purpose: the client also calls `/sanctum/csrf-cookie`.
 */
const client: AxiosInstance = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 10000,
})

export async function getCsrfCookie(): Promise<void> {
  await client.get('/sanctum/csrf-cookie')
}

export default client
