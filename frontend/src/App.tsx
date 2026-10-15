import Login from './components/Login'
import ProtectedRoute from './components/ProtectedRoute'
import { AuthProvider } from './context/AuthContext'
import { useAuth } from './context/useAuth'
import CoursesPage from './pages/CoursesPage'
import DashboardPage from './pages/DashboardPage'

function Shell() {
  const { user, loading, logout } = useAuth()

  if (loading) {
    return <p className="p-8 text-center text-gray-600">Wird geladen …</p>
  }

  if (user === null) {
    return <Login />
  }

  const isStaff = user.role === 'admin' || user.role === 'studio_admin'

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="flex items-center justify-between bg-white px-8 py-4 shadow-sm">
        <span className="font-semibold text-gray-900">Fitnessbooking</span>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-600">
            {user.name} ({user.role})
          </span>
          <button
            type="button"
            onClick={() => void logout()}
            className="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
          >
            Abmelden
          </button>
        </div>
      </header>
      {isStaff ? (
        <ProtectedRoute allowedRoles={['admin', 'studio_admin']}>
          <DashboardPage />
        </ProtectedRoute>
      ) : (
        <ProtectedRoute allowedRoles={['trainer', 'customer']}>
          <CoursesPage />
        </ProtectedRoute>
      )}
    </div>
  )
}

export default function App() {
  return (
    <AuthProvider>
      <Shell />
    </AuthProvider>
  )
}
