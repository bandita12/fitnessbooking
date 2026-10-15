import type { ReactNode } from 'react'
import type { UserRole } from '../api/auth'
import { useAuth } from '../context/useAuth'
import Login from './Login'

interface ProtectedRouteProps {
  allowedRoles: UserRole[]
  children: ReactNode
}

export default function ProtectedRoute({
  allowedRoles,
  children,
}: ProtectedRouteProps) {
  const { user, loading } = useAuth()

  if (loading) {
    return <p className="p-8 text-center text-gray-600">Wird geladen …</p>
  }

  if (user === null || !allowedRoles.includes(user.role)) {
    return <Login />
  }

  return <>{children}</>
}
