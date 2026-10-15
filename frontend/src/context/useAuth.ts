import { useContext } from 'react'
import { AuthContext } from './auth-context'

export function useAuth() {
  const context = useContext(AuthContext)
  if (context === null) {
    throw new Error(
      'useAuth muss innerhalb von <AuthProvider> verwendet werden.',
    )
  }
  return context
}
