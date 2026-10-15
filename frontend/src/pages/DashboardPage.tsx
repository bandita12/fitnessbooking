import { useAuth } from '../context/useAuth'

export default function DashboardPage() {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'

  return (
    <main className="mx-auto max-w-3xl p-8">
      <h1 className="mb-2 text-3xl font-semibold text-gray-900">Dashboard</h1>
      <p className="text-gray-600">
        {isAdmin
          ? 'Systemverwaltung: Studios anlegen und Studio-Admins zuweisen.'
          : `Studioverwaltung für Studio-ID ${user?.studio_id ?? '–'}: Stammdaten, Räume und Kurse pflegen.`}
      </p>
    </main>
  )
}
