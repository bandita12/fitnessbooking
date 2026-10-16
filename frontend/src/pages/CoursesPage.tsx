import axios from 'axios'
import { useEffect, useState } from 'react'
import { bookSession, cancelBooking } from '../api/bookings'
import {
  listCatalog,
  updateCatalogSession,
  type CatalogSession,
  type SessionStatus,
} from '../api/sessions'
import { listAllStudios, type Studio } from '../api/studios'
import { listTrainers, type Trainer } from '../api/trainers'
import { useAuth } from '../context/useAuth'

const WEEKDAYS = [
  'Montag',
  'Dienstag',
  'Mittwoch',
  'Donnerstag',
  'Freitag',
  'Samstag',
  'Sonntag',
]

function mondayOfWeek(offsetWeeks: number): Date {
  const now = new Date()
  const monday = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const day = (monday.getDay() + 6) % 7
  monday.setDate(monday.getDate() - day + offsetWeeks * 7)
  return monday
}

function toISODate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function shortDate(isoDate: string): string {
  const [year, month, day] = isoDate.split('-').map(Number)
  return new Date(year, month - 1, day).toLocaleDateString('de-DE', {
    day: 'numeric',
    month: 'numeric',
  })
}

function availabilityText(session: CatalogSession): string {
  if (session.free_spots === null) return 'Anmeldung möglich'
  if (session.free_spots === 0) return 'Ausgebucht'
  if (session.free_spots === 1) return 'Noch 1 Platz frei'
  return `Noch ${session.free_spots} Plätze frei`
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

function SessionCard({
  session,
  studioTrainers,
  onLoadTrainers,
  onChanged,
}: {
  session: CatalogSession
  studioTrainers: Trainer[]
  onLoadTrainers: (studioId: number) => void
  onChanged: () => void
}) {
  const { user } = useAuth()
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [editing, setEditing] = useState(false)
  const [editStatus, setEditStatus] = useState<SessionStatus>(session.status)
  const [editInfo, setEditInfo] = useState(session.session_info ?? '')
  const [editTrainer, setEditTrainer] = useState('')
  const booked = session.my_booking_id !== null
  const soldOut = session.free_spots === 0

  const isTrainerEdit =
    user?.role === 'trainer' &&
    (session.course.trainer_id === user.id || session.trainer_id === user.id)
  const isManagerEdit =
    user?.role === 'admin' ||
    (user?.role === 'studio_admin' && session.course.studio_id === user.studio_id)
  const mayEdit = isTrainerEdit || isManagerEdit

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

  function openEdit() {
    setEditStatus(
      session.status === 'scheduled' || session.status === 'cancelled'
        ? session.status
        : 'scheduled',
    )
    setEditInfo(session.session_info ?? '')
    setEditTrainer('')
    setEditing(true)
    if (isManagerEdit) {
      onLoadTrainers(session.course.studio_id)
    }
  }

  async function handleEditSave() {
    setError(null)
    setBusy(true)
    try {
      await updateCatalogSession(session.id, {
        ...(isManagerEdit && editTrainer !== ''
          ? { trainer_id: Number(editTrainer) }
          : {}),
        status: editStatus,
        session_info: editInfo.trim() === '' ? null : editInfo.trim(),
      })
      setEditing(false)
      onChanged()
    } catch (unknownError: unknown) {
      setError(bookingErrorMessage(unknownError))
    } finally {
      setBusy(false)
    }
  }

  return (
    <article className="rounded-lg bg-white p-4 shadow-md">
      <h3 className="mb-1 font-semibold text-gray-900">{session.course.title}</h3>
      <p className="mb-2 text-sm text-gray-600">
        {session.course.start_time}–{session.course.end_time} Uhr
      </p>

      <dl className="mb-2 space-y-1 text-sm text-gray-700">
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
          <dt className="font-medium">Frei:</dt>
          <dd>{availabilityText(session)}</dd>
        </div>
      </dl>

      {session.session_info !== null && session.session_info !== '' && (
        <p className="mb-2 rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-900">
          {session.session_info}
        </p>
      )}

      {error !== null && (
        <p role="alert" className="mb-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
          {error}
        </p>
      )}

      {mayEdit && !editing && (
        <button
          type="button"
          onClick={openEdit}
          className="mb-2 w-full rounded-md border border-indigo-600 px-4 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
        >
          Session bearbeiten
        </button>
      )}

      {mayEdit && editing && (
        <div className="mb-2 space-y-2 rounded-md border border-indigo-200 p-3">
          {isManagerEdit && (
            <select
              aria-label="Vertretungstrainer"
              value={editTrainer}
              onChange={(event) => setEditTrainer(event.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none"
            >
              <option value="">Trainer unverändert lassen</option>
              {studioTrainers.map((entry) => (
                <option key={entry.id} value={entry.id}>
                  {entry.name}
                </option>
              ))}
            </select>
          )}
          <select
            aria-label="Status"
            value={editStatus}
            onChange={(event) =>
              setEditStatus(event.target.value as SessionStatus)
            }
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none"
          >
            <option value="scheduled">Geplant</option>
            {isManagerEdit && <option value="substituted">Vertretung</option>}
            <option value="cancelled">Abgesagt</option>
          </select>
          <textarea
            aria-label="Mitteilung an Teilnehmer"
            placeholder="z. B. Bitte eigene Matte mitbringen!"
            value={editInfo}
            onChange={(event) => setEditInfo(event.target.value)}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none"
          />
          <div className="flex gap-2">
            <button
              type="button"
              disabled={busy}
              onClick={() => void handleEditSave()}
              className="flex-1 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            >
              {busy ? 'Wird gespeichert …' : 'Speichern'}
            </button>
            <button
              type="button"
              onClick={() => setEditing(false)}
              className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >
              Abbrechen
            </button>
          </div>
        </div>
      )}

      {user?.role === 'customer' &&
        (booked ? (
          <button
            type="button"
            disabled={busy}
            onClick={() => void handleCancel()}
            className="w-full rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
          >
            {busy ? 'Wird storniert …' : 'Buchung stornieren'}
          </button>
        ) : (
          <button
            type="button"
            disabled={busy || soldOut}
            onClick={() => void handleBook()}
            className="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {busy ? 'Wird gebucht …' : soldOut ? 'Ausgebucht' : 'Jetzt buchen'}
          </button>
        ))}
    </article>
  )
}

export default function CoursesPage() {
  const { user } = useAuth()
  const [weekOffset, setWeekOffset] = useState(0)
  const [sessions, setSessions] = useState<CatalogSession[]>([])
  const [studios, setStudios] = useState<Studio[]>([])
  const [studioFilter, setStudioFilter] = useState('')
  const [trainersByStudio, setTrainersByStudio] = useState<Record<number, Trainer[]>>({})
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const showStudioFilter = user?.role === 'admin' || user?.role === 'studio_admin'

  const monday = mondayOfWeek(weekOffset)
  const days = Array.from({ length: 7 }, (_, index) => {
    const date = new Date(monday)
    date.setDate(monday.getDate() + index)
    return date
  })
  const fromISO = toISODate(days[0])
  const toISO = toISODate(days[6])

  function loadWeek(from: string, to: string, studio: string) {
    listCatalog({
      from,
      to,
      studio_id: studio === '' ? null : Number(studio),
    })
      .then((data) => {
        setSessions(data)
        setLoading(false)
      })
      .catch(() => {
        setLoadError('Kurse konnten nicht geladen werden.')
        setLoading(false)
      })
  }

  function changeWeek(offset: number) {
    setLoading(true)
    setLoadError(null)
    setWeekOffset(offset)
  }

  function changeStudioFilter(value: string) {
    setLoading(true)
    setLoadError(null)
    setStudioFilter(value)
  }

  useEffect(() => {
    loadWeek(fromISO, toISO, studioFilter)
  }, [fromISO, toISO, studioFilter])

  useEffect(() => {
    if (!showStudioFilter) return
    let active = true
    listAllStudios()
      .then((data) => {
        if (active) setStudios(data)
      })
      .catch(() => {
        if (active) setStudios([])
      })
    return () => {
      active = false
    }
  }, [showStudioFilter])

  async function handleLoadTrainers(studioId: number) {
    if (trainersByStudio[studioId] !== undefined) return
    try {
      const trainers = await listTrainers(studioId)
      setTrainersByStudio((previous) => ({ ...previous, [studioId]: trainers }))
    } catch {
      setTrainersByStudio((previous) => ({ ...previous, [studioId]: [] }))
    }
  }

  function sessionsForDay(isoDate: string): CatalogSession[] {
    return sessions
      .filter((session) => session.date === isoDate)
      .sort((a, b) =>
        a.course.start_time < b.course.start_time
          ? -1
          : a.course.start_time > b.course.start_time
            ? 1
            : 0,
      )
  }

  return (
    <main className="mx-auto max-w-7xl p-8">
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-3xl font-semibold text-gray-900">Kursübersicht</h1>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => changeWeek(weekOffset - 1)}
            className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            &larr; Vorherige Woche
          </button>
          <button
            type="button"
            onClick={() => changeWeek(0)}
            className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            Aktuelle Woche
          </button>
          <button
            type="button"
            onClick={() => changeWeek(weekOffset + 1)}
            className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            Nächste Woche &rarr;
          </button>
        </div>
      </div>

      {showStudioFilter && (
        <div className="mb-6 flex items-center gap-3 rounded-lg bg-white p-4 shadow-md">
          <label htmlFor="calendar-studio" className="text-sm font-medium text-gray-700">
            Studio filtern:
          </label>
          <select
            id="calendar-studio"
            value={studioFilter}
            onChange={(event) => changeStudioFilter(event.target.value)}
            className="rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none"
          >
            <option value="">Alle Studios</option>
            {studios.map((studio) => (
              <option key={studio.id} value={studio.id}>
                {studio.name}
              </option>
            ))}
          </select>
        </div>
      )}

      {loading && <p className="text-gray-600">Wird geladen …</p>}
      {loadError !== null && (
        <p role="alert" className="text-sm text-red-600">
          {loadError}
        </p>
      )}
      {!loading && loadError === null && sessions.length === 0 && (
        <p className="text-gray-600">
          In dieser Woche sind keine Termine verfügbar.
        </p>
      )}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-7">
        {days.map((day, index) => {
          const iso = toISODate(day)
          const daySessions = sessionsForDay(iso)
          return (
            <section
              key={iso}
              aria-label={WEEKDAYS[index]}
              className="rounded-lg bg-gray-100 p-3"
            >
              <h2 className="mb-3 text-sm font-semibold text-gray-900">
                {WEEKDAYS[index]}{' '}
                <span className="font-normal text-gray-600">{shortDate(iso)}</span>
              </h2>
              <div className="space-y-3">
                {daySessions.map((session) => (
                  <SessionCard
                    key={session.id}
                    session={session}
                    studioTrainers={trainersByStudio[session.course.studio_id] ?? []}
                    onLoadTrainers={handleLoadTrainers}
                    onChanged={() => loadWeek(fromISO, toISO, studioFilter)}
                  />
                ))}
                {daySessions.length === 0 && (
                  <p className="text-xs text-gray-500">Keine Termine</p>
                )}
              </div>
            </section>
          )
        })}
      </div>
    </main>
  )
}
