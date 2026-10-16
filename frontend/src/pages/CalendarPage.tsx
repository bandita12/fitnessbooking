import { useState } from 'react'
import CoursesCalendar, {
  type CoursesCalendarMode,
} from '../components/CoursesCalendar'

export default function CalendarPage() {
  const [mode, setMode] = useState<CoursesCalendarMode>('global')

  return (
    <main className="mx-auto max-w-7xl p-8">
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-3xl font-semibold text-gray-900">Kurskalender</h1>
        <div className="flex items-center gap-2" role="group" aria-label="Kalenderansicht">
          <button
            type="button"
            onClick={() => setMode('global')}
            aria-pressed={mode === 'global'}
            className={`rounded-md px-3 py-2 text-sm font-medium ${mode === 'global' ? 'bg-indigo-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-100'}`}
          >
            Alle Kurse
          </button>
          <button
            type="button"
            onClick={() => setMode('personalized')}
            aria-pressed={mode === 'personalized'}
            className={`rounded-md px-3 py-2 text-sm font-medium ${mode === 'personalized' ? 'bg-indigo-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-100'}`}
          >
            Meine Buchungen
          </button>
        </div>
      </div>
      <CoursesCalendar mode={mode} />
    </main>
  )
}
