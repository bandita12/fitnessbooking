import axios from 'axios'
import { useEffect, useState } from 'react'
import { bookSession, cancelBooking } from '../api/bookings'
import { listCatalog, type CatalogSession } from '../api/sessions'

function formatDate(isoDate: string): string {
  const [year, month, day] = isoDate.split('-').map(Number)
  return new Date(year, month - 1, day).toLocaleDateString('de-DE', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  })
}

function availabilityText(session: CatalogSession): string {
  if (session.free_spots === null) return 'Anmeldung möglich'
  if (session.free_spots === 0) return 'Ausgebucht'
  if (session.free_spots === 1) return 'Noch 1 Platz frei'
  return `Noch ${session.free_spots} Plätze frei`
}

function SessionCard({
  session,
  onChanged,
}: {
  session: CatalogSession
  onChanged: () => void
}) {
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const booked = session.my_booking_id !== null
  const soldOut = session.free_spots === 0

  async function handleBook() {
    setError(null)
    setBusy(true)
    try {
      await bookSession(session.id)
      onChanged()
    } catch (unknownError: unknown) {
      setError(bookingErrorMessage(unknownError))
    } finally {
      setBusy(false)
    }
  }

  async function handleCancel() {
    if (session.my_booking_id === null) return
    setError(null)
    setBusy(true)
    try {
      await cancelBooking(session.my_booking_id)
      onChanged()
    } catch (unknownError: unknown) {
      setError(bookingErrorMessage(unknownError))
    } finally {
      setBusy(false)
    }
  }

  return (
    <article className="rounded-lg bg-white p-6 shadow-md">
      <h2 className="mb-1 text-xl font-semibold text-gray-900">
        {session.course.title}
      </h2>
      <p className="mb-3 text-sm text-gray-600">
        {formatDate(session.date)} · {session.course.start_time}–
        {session.course.end_time} Uhr
      </p>

      <dl className="mb-3 space-y-1 text-sm text-gray-700">
        <div className="flex gap-2">
          <dt className="font-medium">Raum:</dt>
          <dd>
            {session.room === null
              ? 'Wird noch bekannt gegeben'
              : `${session.room.name}${session.room.size_sqm !== null ? ` (${session.room.size_sqm} m²)` : ''}${session.room.lighting !== null ? ` · ${session.room.lighting}` : ''}`}
          </dd>
        </div>
        <div className="flex gap-2">
          <dt className="font-medium">Trainer:</dt>
          <dd>
            {session.trainer === null ? (
              'Wird noch bekannt gegeben'
            ) : (
              <>
                {session.trainer.name}
                {session.is_substitute && (
                  <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                    Vertretung
                  </span>
                )}
              </>
            )}
          </dd>
        </div>
        <div className="flex gap-2">
          <dt className="font-medium">Freie Plätze:</dt>
          <dd>{availabilityText(session)}</dd>
        </div>
      </dl>

      {session.session_info !== null && session.session_info !== '' && (
        <p className="mb-3 rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-900">
          {session.session_info}
        </p>
      )}

      {error !== null && (
        <p role="alert" className="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
          {error}
        </p>
      )}

      {booked ? (
        <button
          type="button"
          disabled={busy}
          onClick={() => void handleCancel()}
          className="w-full rounded-md bg-red-600 px-4 py-2 font-medium text-white hover:bg-red-700 disabled:opacity-50"
        >
          {busy ? 'Wird storniert …' : 'Buchung stornieren'}
        </button>
      ) : (
        <button
          type="button"
          disabled={busy || soldOut}
          onClick={() => void handleBook()}
          className="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
        >
          {busy ? 'Wird gebucht …' : soldOut ? 'Ausgebucht' : 'Jetzt buchen'}
        </button>
      )}
    </article>
  )
}

function bookingErrorMessage(unknownError: unknown): string {
  if (axios.isAxiosError(unknownError)) {
    const message = unknownError.response?.data?.message
    if (typeof message === 'string' && message !== '') return message
    if (unknownError.response?.status === 422) {
      return 'Buchung nicht möglich (ausgebucht, bereits gebucht oder Stornofrist abgelaufen).'
    }
  }
  return 'Vorgang fehlgeschlagen. Bitte versuche es erneut.'
}

export default function CoursesPage() {
  const [sessions, setSessions] = useState<CatalogSession[]>([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const refresh = () => {
    listCatalog()
      .then(setSessions)
      .catch(() => setLoadError('Kurse konnten nicht geladen werden.'))
      .finally(() => setLoading(false))
  }

  useEffect(refresh, [])

  return (
    <main className="mx-auto max-w-5xl p-8">
      <h1 className="mb-6 text-3xl font-semibold text-gray-900">
        Kursübersicht
      </h1>
      {loading && <p className="text-gray-600">Wird geladen …</p>}
      {loadError !== null && (
        <p role="alert" className="text-sm text-red-600">
          {loadError}
        </p>
      )}
      {!loading && loadError === null && sessions.length === 0 && (
        <p className="text-gray-600">
          Aktuell sind keine Termine verfügbar. Schau bald wieder vorbei!
        </p>
      )}
      <div className="grid gap-6 md:grid-cols-2">
        {sessions.map((session) => (
          <SessionCard key={session.id} session={session} onChanged={refresh} />
        ))}
      </div>
    </main>
  )
}
