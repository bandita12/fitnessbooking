import { useAuth } from '../context/useAuth'

export default function CoursesPage() {
  const { user } = useAuth()

  return (
    <main className="mx-auto max-w-3xl p-8">
      <h1 className="mb-2 text-3xl font-semibold text-gray-900">
        Kursübersicht
      </h1>
      <p className="text-gray-600">
        Willkommen, {user?.name}! Hier siehst du bald alle buchbaren Kurse
        {user?.studio_id !== null && user?.studio_id !== undefined
          ? ` deines Studios (ID ${user.studio_id})`
          : ' aller Studios'}
        .
      </p>
    </main>
  )
}
