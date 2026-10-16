import {
  useCallback,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { fetchMe, loginRequest, logoutRequest, registerRequest, type AuthUser } from '../api/auth'
import { AuthContext } from './auth-context'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true
    fetchMe()
      .then((me) => {
        if (active) setUser(me)
      })
      .catch(() => {
        if (active) setUser(null)
      })
      .finally(() => {
        if (active) setLoading(false)
      })
    return () => {
      active = false
    }
  }, [])

  const login = useCallback(async (email: string, password: string) => {
    const authenticated = await loginRequest({ email, password })
    setUser(authenticated)
    return authenticated
  }, [])

  const logout = useCallback(async () => {
    await logoutRequest()
    setUser(null)
  }, [])

  const register = useCallback(
    async (name: string, email: string, password: string, passwordConfirmation: string) => {
      const authenticated = await registerRequest({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
      setUser(authenticated)
      return authenticated
    },
    [],
  )

  const value = useMemo(
    () => ({ user, loading, login, logout, register }),
    [user, loading, login, logout, register],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
