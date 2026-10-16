import deLocale from '@fullcalendar/core/locales/de'
import type {
  DatesSetArg,
  EventContentArg,
  EventInput,
} from '@fullcalendar/core'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import FullCalendar from '@fullcalendar/react'
import timeGridPlugin from '@fullcalendar/timegrid'
import { useEffect, useMemo, useState } from 'react'
import { listRooms, type Room } from '../api/rooms'
import {
  listCatalog,
  listMySessions,
  type CatalogSession,
} from '../api/sessions'
import { listAllStudios, type Studio } from '../api/studios'

export type CoursesCalendarMode = 'global' | 'personalized'

type StudioSortKey = 'name-asc' | 'name-desc' | 'trainers' | 'customers'
type RoomSortKey = 'name-asc' | 'name-desc' | 'size' | 'capacity'

interface DateRange {
  from: string
  to: string
}

const COLOR_CANCELLED = 'rgb(107, 114, 128)'
const COLOR_FULL = 'rgb(239, 68, 68)'
const COLOR_LOW = 'rgb(234, 179, 8)'
const COLOR_FREE = 'rgb(34, 197, 94)'

/**
 * Ampelfarbe je Session: grau bei Absage, rot wenn ausgebucht, gelb bei
 * höchstens 3 Restplätzen, sonst grün.
 */
function trafficLightColor(session: CatalogSession): string {
  if (session.status === 'cancelled') {
    return COLOR_CANCELLED
  }
  // free_spots === null bedeutet: keine Kapazitätsgrenze bekannt (z. B.
  // unbegrenzte Outdoor-Fläche). Wird als buchbar gewertet und grün
  // dargestellt — analog zur CoursesPage-Anzeige „Anmeldung möglich“.
  if (session.free_spots === null) {
    return COLOR_FREE
  }
  if (session.free_spots <= 0) {
    return COLOR_FULL
  }
  if (session.free_spots <= 3) {
    return COLOR_LOW
  }
  return COLOR_FREE
}

function sortStudios(studios: Studio[], sort: StudioSortKey): Studio[] {
  const copy = [...studios]
  switch (sort) {
    case 'name-desc':
      return copy.sort((a, b) => b.name.localeCompare(a.name, 'de'))
    case 'trainers':
      return copy.sort(
        (a, b) => (b.trainers_count ?? 0) - (a.trainers_count ?? 0),
      )
    case 'customers':
      return copy.sort(
        (a, b) => (b.customers_count ?? 0) - (a.customers_count ?? 0),
      )
    case 'name-asc':
    default:
      return copy.sort((a, b) => a.name.localeCompare(b.name, 'de'))
  }
}

function sortRooms(rooms: Room[], sort: RoomSortKey): Room[] {
  const copy = [...rooms]
  switch (sort) {
    case 'name-desc':
      return copy.sort((a, b) => b.name.localeCompare(a.name, 'de'))
    case 'size':
      return copy.sort((a, b) => (b.size_sqm ?? 0) - (a.size_sqm ?? 0))
    case 'capacity':
      return copy.sort((a, b) => b.capacity - a.capacity)
    case 'name-asc':
    default:
      return copy.sort((a, b) => a.name.localeCompare(b.name, 'de'))
  }
}

function toEvent(session: CatalogSession): EventInput {
  const color = trafficLightColor(session)
  return {
    id: String(session.id),
    title: session.course.title,
    start: `${session.date}T${session.course.start_time}`,
    end: `${session.date}T${session.course.end_time}`,
    backgroundColor: color,
    borderColor: color,
    extendedProps: { session },
  }
}

function renderEventContent(arg: EventContentArg) {
  const session = arg.event.extendedProps['session'] as CatalogSession
  const cancelled = session.status === 'cancelled'

  return (
    <div className="overflow-hidden text-xs leading-tight">
      <div className={cancelled ? 'font-bold line-through' : 'font-semibold'}>
        {session.course.title}
        {cancelled && <span className="ml-1 font-bold">ABGESAGT</span>}
      </div>
      <div>
        {session.room === null
          ? 'Raum: wird bekannt gegeben'
          : `Raum: ${session.room.name}`}
      </div>
      <div>
        {session.trainer === null
          ? 'Trainer: wird bekannt gegeben'
          : `Trainer: ${session.trainer.name}`}
        {session.is_substitute && (
          <span className="ml-1 animate-pulse rounded bg-blue-600 px-1 font-bold text-white">
            SUB
          </span>
        )}
      </div>
      {session.session_info !== null && session.session_info !== '' && (
        <div className="mt-0.5 rounded bg-white/25 px-1 italic">
          💬 {session.session_info}
        </div>
      )}
    </div>
  )
}

export default function CoursesCalendar({
  mode,
}: {
  mode: CoursesCalendarMode
}) {
  const [studios, setStudios] = useState<Studio[]>([])
  const [studioSort, setStudioSort] = useState<StudioSortKey>('name-asc')
  const [selectedStudioId, setSelectedStudioId] = useState<number | null>(null)
  const [studiosError, setStudiosError] = useState<string | null>(null)

  const [rooms, setRooms] = useState<Room[] | null>(null)
  const [roomSort, setRoomSort] = useState<RoomSortKey>('name-asc')
  const [selectedRoomId, setSelectedRoomId] = useState<number | null>(null)

  const [sessions, setSessions] = useState<CatalogSession[]>([])
  const [range, setRange] = useState<DateRange | null>(null)
  const [loadingSessions, setLoadingSessions] = useState(false)
  const [sessionsError, setSessionsError] = useState<string | null>(null)

  const sortedStudios = useMemo(
    () => sortStudios(studios, studioSort),
    [studios, studioSort],
  )
  const sortedRooms = useMemo(
    () => sortRooms(rooms ?? [], roomSort),
    [rooms, roomSort],
  )
  const events = useMemo(() => sessions.map(toEvent), [sessions])

  // Studios laden (nur globaler Modus).
  useEffect(() => {
    if (mode !== 'global') return
    let active = true
    listAllStudios()
      .then((data) => {
        if (active) setStudios(data)
      })
      .catch(() => {
        if (active) setStudiosError('Studios konnten nicht geladen werden.')
      })
    return () => {
      active = false
    }
  }, [mode])

  // Auswahl nach jeder (Um-)Sortierung auf das erste Element zurücksetzen.
  useEffect(() => {
    if (sortedStudios.length > 0) {
      setSelectedStudioId(sortedStudios[0].id)
    }
  }, [sortedStudios])

  // Räume des gewählten Studios laden (nur globaler Modus).
  useEffect(() => {
    if (mode !== 'global' || selectedStudioId === null) return
    let active = true
    setRooms(null)
    listRooms(selectedStudioId)
      .then((data) => {
        if (active) setRooms(data)
      })
      .catch(() => {
        if (active) setRooms([])
      })
    return () => {
      active = false
    }
  }, [mode, selectedStudioId])

  // Raumauswahl nach jeder (Um-)Sortierung auf das erste Element zurücksetzen.
  useEffect(() => {
    if (sortedRooms.length > 0) {
      setSelectedRoomId(sortedRooms[0].id)
    } else {
      setSelectedRoomId(null)
    }
  }, [sortedRooms])

  // Sichtbarer Zeitraum aus FullCalendar übernehmen.
  function handleDatesSet(arg: DatesSetArg) {
    const from = arg.startStr.slice(0, 10)
    const to = arg.endStr.slice(0, 10)
    setRange((previous) =>
      previous !== null && previous.from === from && previous.to === to
        ? previous
        : { from, to },
    )
  }

  // Sessions für Zeitraum (+ Filter bzw. eigene Buchungen) laden.
  useEffect(() => {
    if (range === null) return
    if (mode === 'global') {
      if (selectedStudioId === null || rooms === null) return
    }
    let active = true
    setLoadingSessions(true)
    setSessionsError(null)
    const request =
      mode === 'personalized'
        ? listMySessions({ from: range.from, to: range.to })
        : listCatalog({
            from: range.from,
            to: range.to,
            studio_id: selectedStudioId,
            room_id: selectedRoomId,
            include_cancelled: true,
          })
    request
      .then((data) => {
        if (active) {
          setSessions(data)
          setLoadingSessions(false)
        }
      })
      .catch(() => {
        if (active) {
          setSessionsError('Termine konnten nicht geladen werden.')
          setLoadingSessions(false)
        }
      })
    return () => {
      active = false
    }
  }, [mode, range, selectedStudioId, selectedRoomId, rooms])

  return (
    <div className="space-y-4">
      {mode === 'global' && (
        <div className="flex flex-wrap items-end gap-4 rounded-lg bg-white p-4 shadow-md">
          <div className="flex flex-col gap-1">
            <label
              htmlFor="calendar-studio-select"
              className="text-sm font-medium text-gray-700"
            >
              Studio:
            </label>
            <select
              id="calendar-studio-select"
              value={selectedStudioId ?? ''}
              onChange={(event) =>
                setSelectedStudioId(
                  event.target.value === '' ? null : Number(event.target.value),
                )
              }
              className="rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none"
            >
              {sortedStudios.map((studio) => (
                <option key={studio.id} value={studio.id}>
                  {studio.name}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label
              htmlFor="calendar-studio-sort"
              className="text-sm font-medium text-gray-700"
            >
              Studios sortieren:
            </label>
            <select
              id="calendar-studio-sort"
              value={studioSort}
              onChange={(event) =>
                setStudioSort(event.target.value as StudioSortKey)
              }
              className="rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none"
            >
              <option value="name-asc">Name A–Z</option>
              <option value="name-desc">Name Z–A</option>
              <option value="trainers">Traineranzahl</option>
              <option value="customers">Teilnehmeranzahl</option>
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label
              htmlFor="calendar-room-select"
              className="text-sm font-medium text-gray-700"
            >
              Raum:
            </label>
            <select
              id="calendar-room-select"
              value={selectedRoomId ?? ''}
              onChange={(event) =>
                setSelectedRoomId(
                  event.target.value === '' ? null : Number(event.target.value),
                )
              }
              disabled={rooms === null || sortedRooms.length === 0}
              className="rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none disabled:opacity-50"
            >
              {sortedRooms.map((room) => (
                <option key={room.id} value={room.id}>
                  {room.name}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label
              htmlFor="calendar-room-sort"
              className="text-sm font-medium text-gray-700"
            >
              Räume sortieren:
            </label>
            <select
              id="calendar-room-sort"
              value={roomSort}
              onChange={(event) =>
                setRoomSort(event.target.value as RoomSortKey)
              }
              className="rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none"
            >
              <option value="name-asc">Name A–Z</option>
              <option value="name-desc">Name Z–A</option>
              <option value="size">Größe (m²)</option>
              <option value="capacity">Kapazität</option>
            </select>
          </div>
          {rooms !== null && sortedRooms.length === 0 && (
            <p className="text-sm text-gray-600">
              Keine Räume vorhanden — es werden alle Termine des Studios
              angezeigt.
            </p>
          )}
        </div>
      )}

      {studiosError !== null && mode === 'global' && (
        <p role="alert" className="text-sm text-red-600">
          {studiosError}
        </p>
      )}

      {mode === 'personalized' && (
        <p className="rounded-lg bg-white p-4 text-sm text-gray-700 shadow-md">
          Meine gebuchten Kurstermine — inklusive abgesagter Termine (grau).
        </p>
      )}

      {loadingSessions && <p className="text-gray-600">Wird geladen …</p>}
      {sessionsError !== null && (
        <p role="alert" className="text-sm text-red-600">
          {sessionsError}
        </p>
      )}

      <div className="rounded-lg bg-white p-4 shadow-md">
        <FullCalendar
          plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
          initialView="timeGridWeek"
          headerToolbar={{
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay',
          }}
          locales={[deLocale]}
          locale="de"
          firstDay={1}
          slotMinTime="06:00:00"
          slotMaxTime="23:00:00"
          events={events}
          eventContent={renderEventContent}
          datesSet={handleDatesSet}
          height="auto"
        />
      </div>
    </div>
  )
}
