import { useState } from 'react'
import Login from './components/Login'
import ProtectedRoute from './components/ProtectedRoute'
import { AuthProvider } from './context/AuthContext'
import { useAuth } from './context/useAuth'
import CoursesPage from './pages/CoursesPage'
import DashboardPage from './pages/DashboardPage'

type MainView = 'courses' | 'dashboard'

function Shell() {
  const { user, loading, logout } = useAuth()
  const [view, setView] = useState<MainView>('courses')

  if (loading) {
    return <p className="p-8 text-center text-gray-600">Wird geladen …</p>
  }

  if (user === null) {
    return <Login />
  }

  const isStaff = user.role === 'admin' || user.role === 'studio_admin'
  const showDashboard = isStaff && view === 'dashboard'

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="flex items-center justify-between bg-white px-8 py-4 shadow-sm">
        <div className="flex items-center gap-4">
          <span className="font-semibold text-gray-900">Fitnessbooking</span>
          {isStaff && (
            <nav className="flex gap-2" aria-label="Hauptmenü">
              <button
                type="button"
                onClick={() => setView('courses')}
                className={`rounded-md px-3 py-1 text-sm font-medium ${!showDashboard ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`}
              >
                Kursübersicht
              </button>
              <button
                type="button"
                onClick={() => setView('dashboard')}
                className={`rounded-md px-3 py-1 text-sm font-medium ${showDashboard ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-100'}`}
              >
                Zur Studio-Verwaltung
              </button>
            </nav>
          )}
        </div>
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
      {showDashboard ? (
        <ProtectedRoute allowedRoles={['admin', 'studio_admin']}>
          <DashboardPage />
        </ProtectedRoute>
      ) : (
        <ProtectedRoute
          allowedRoles={['admin', 'studio_admin', 'trainer', 'customer']}
        >
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
