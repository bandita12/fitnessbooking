import axios from 'axios'
import { useEffect, useState, type FormEvent } from 'react'
import {
  createStudioAdmin,
  deleteUser,
  listUsers,
  updateUser,
  type ManagedUser,
} from '../api/admin'
import {
  createRoom,
  deleteRoom,
  listRooms,
  updateRoom,
  type NewRoom,
  type Room,
} from '../api/rooms'
import {
  createStudio,
  deleteStudio,
  listStudios,
  updateStudio,
  type NewStudio,
  type Studio,
} from '../api/studios'
import {
  attachTrainer,
  detachTrainer,
  listTrainers,
  registerTrainer,
  updateTrainer,
  type Trainer,
} from '../api/trainers'
import {
  createCourse,
  generateSessions,
  listCourses,
  updateCourse,
  weekdayName,
  type Course,
  type NewCourse,
} from '../api/courses'
import {
  listSessions,
  updateSession,
  type CourseSession,
  type SessionStatus,
} from '../api/sessions'
import { useAuth } from '../context/useAuth'

type Tab = 'studios' | 'admins' | 'rooms' | 'courses'

const emptyStudio: NewStudio = {
  name: '',
  street: '',
  zip: '',
  city: '',
  description: '',
  thumbnail_url: '',
  image_url: '',
  web_link: '',
}

function errorMessage(unknownError: unknown, fallback: string): string {
  if (axios.isAxiosError(unknownError)) {
    const errors = unknownError.response?.data?.errors
    if (errors !== null && typeof errors === 'object') {
      const first = Object.values(errors as Record<string, unknown>)[0]
      if (Array.isArray(first) && typeof first[0] === 'string') {
        return first[0]
      }
    }
    if (unknownError.response?.status === 422) {
      return 'Eingaben ungültig. Bitte alle Pflichtfelder prüfen.'
    }
  }
  return fallback
}

export default function DashboardPage() {
  const { user } = useAuth()
  const [tab, setTab] = useState<Tab>('studios')
  const [studios, setStudios] = useState<Studio[]>([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [studioForm, setStudioForm] = useState<NewStudio>(emptyStudio)
  const [studioNotice, setStudioNotice] = useState<string | null>(null)
  const [studioError, setStudioError] = useState<string | null>(null)
  const [savingStudio, setSavingStudio] = useState(false)
  const [editingStudioId, setEditingStudioId] = useState<number | null>(null)

  const [adminName, setAdminName] = useState('')
  const [adminEmail, setAdminEmail] = useState('')
  const [adminPassword, setAdminPassword] = useState('')
  const [adminStudioId, setAdminStudioId] = useState('')
  const [adminNotice, setAdminNotice] = useState<string | null>(null)
  const [adminError, setAdminError] = useState<string | null>(null)
  const [savingAdmin, setSavingAdmin] = useState(false)
  const [admins, setAdmins] = useState<ManagedUser[]>([])
  const [editingAdminId, setEditingAdminId] = useState<number | null>(null)
  const [managedStudioId, setManagedStudioId] = useState<number | null>(null)

  useEffect(() => {
    let active = true
    Promise.all([listStudios(), listUsers('studio_admin')])
      .then(([studioData, adminData]) => {
        if (active) {
          setStudios(studioData)
          setAdmins(adminData)
          if (studioData.length > 0) {
            setManagedStudioId((previous) =>
              previous === null ? studioData[0].id : previous,
            )
          }
        }
      })
      .catch(() => {
        if (active) setLoadError('Daten konnten nicht geladen werden.')
      })
      .finally(() => {
        if (active) setLoading(false)
      })
    return () => {
      active = false
    }
  }, [])

  function setStudioField(field: keyof NewStudio, value: string) {
    setStudioForm((previous) => ({ ...previous, [field]: value }))
  }

  function studioPayload(): NewStudio {
    return {
      ...studioForm,
      name: studioForm.name.trim(),
      description: studioForm.description?.trim() === '' ? undefined : studioForm.description?.trim(),
      thumbnail_url:
        studioForm.thumbnail_url?.trim() === '' ? undefined : studioForm.thumbnail_url?.trim(),
      image_url: studioForm.image_url?.trim() === '' ? undefined : studioForm.image_url?.trim(),
      web_link: studioForm.web_link?.trim() === '' ? undefined : studioForm.web_link?.trim(),
    }
  }

  function startStudioEdit(studio: Studio) {
    setStudioNotice(null)
    setStudioError(null)
    setEditingStudioId(studio.id)
    setStudioForm({
      name: studio.name,
      street: studio.street,
      zip: studio.zip,
      city: studio.city,
      description: studio.description ?? '',
      thumbnail_url: studio.thumbnail_url ?? '',
      image_url: studio.image_url ?? '',
      web_link: studio.web_link ?? '',
    })
  }

  function cancelStudioEdit() {
    setEditingStudioId(null)
    setStudioForm(emptyStudio)
    setStudioNotice(null)
    setStudioError(null)
  }

  async function handleStudioSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setStudioNotice(null)
    setStudioError(null)
    setSavingStudio(true)
    try {
      if (editingStudioId === null) {
        const created = await createStudio(studioPayload())
        setStudios((previous) => [...previous, created])
        setManagedStudioId((previous) => (previous === null ? created.id : previous))
        setStudioForm(emptyStudio)
        setStudioNotice(`Studio „${created.name}“ wurde angelegt.`)
      } else {
        const updated = await updateStudio(editingStudioId, studioPayload())
        setStudios((previous) =>
          previous.map((entry) => (entry.id === updated.id ? updated : entry)),
        )
        setStudioForm(emptyStudio)
        setEditingStudioId(null)
        setStudioNotice(`Studio „${updated.name}“ wurde gespeichert.`)
      }
    } catch (unknownError: unknown) {
      setStudioError(errorMessage(unknownError, 'Studio konnte nicht gespeichert werden.'))
    } finally {
      setSavingStudio(false)
    }
  }

  async function handleStudioDelete(studio: Studio) {
    if (
      !window.confirm(
        `Studio „${studio.name}“ wirklich löschen? Dabei werden kaskadierend alle Räume, Kurse, Termine und Buchungen entfernt.`,
      )
    ) {
      return
    }
    setStudioNotice(null)
    setStudioError(null)
    try {
      await deleteStudio(studio.id)
      setStudios((previous) => previous.filter((entry) => entry.id !== studio.id))
      if (editingStudioId === studio.id) {
        setEditingStudioId(null)
        setStudioForm(emptyStudio)
      }
      setStudioNotice(`Studio „${studio.name}“ wurde gelöscht.`)
    } catch (unknownError: unknown) {
      setStudioError(errorMessage(unknownError, 'Studio konnte nicht gelöscht werden.'))
    }
  }

  async function handleAdminDelete(admin: ManagedUser) {
    if (
      !window.confirm(`Studio-Admin „${admin.name}“ (${admin.email}) wirklich löschen?`)
    ) {
      return
    }
    setAdminNotice(null)
    setAdminError(null)
    try {
      await deleteUser(admin.id)
      setAdmins((previous) => previous.filter((entry) => entry.id !== admin.id))
      if (editingAdminId === admin.id) {
        resetAdminForm()
      }
      setAdminNotice(`Studio-Admin „${admin.name}“ wurde gelöscht.`)
    } catch (unknownError: unknown) {
      setAdminError(errorMessage(unknownError, 'Studio-Admin konnte nicht gelöscht werden.'))
    }
  }

  function resetAdminForm() {
    setAdminName('')
    setAdminEmail('')
    setAdminPassword('')
    setAdminStudioId('')
    setEditingAdminId(null)
    setAdminNotice(null)
    setAdminError(null)
  }

  function startAdminEdit(admin: ManagedUser) {
    setAdminNotice(null)
    setAdminError(null)
    setEditingAdminId(admin.id)
    setAdminName(admin.name)
    setAdminEmail(admin.email)
    setAdminPassword('')
    setAdminStudioId(admin.studio_id === null ? '' : String(admin.studio_id))
  }

  function studioName(studioId: number | null): string {
    if (studioId === null) return '–'
    return studios.find((studio) => studio.id === studioId)?.name ?? `ID ${studioId}`
  }

  async function handleAdminSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setAdminNotice(null)
    setAdminError(null)
    setSavingAdmin(true)
    try {
      if (editingAdminId === null) {
        const created = await createStudioAdmin({
          name: adminName,
          email: adminEmail,
          password: adminPassword,
          studio_id: Number(adminStudioId),
        })
        setAdmins((previous) => [
          ...previous,
          {
            id: created.id,
            name: created.name,
            email: created.email,
            role: created.role,
            studio_id: created.studio_id,
          },
        ])
        resetAdminForm()
        setAdminNotice(`Studio-Admin „${created.name}“ wurde angelegt.`)
      } else {
        const updated = await updateUser(editingAdminId, {
          name: adminName,
          email: adminEmail,
          studio_id: adminStudioId === '' ? null : Number(adminStudioId),
          ...(adminPassword !== '' ? { password: adminPassword } : {}),
        })
        setAdmins((previous) =>
          previous.map((entry) => (entry.id === updated.id ? updated : entry)),
        )
        resetAdminForm()
        setAdminNotice(`Studio-Admin „${updated.name}“ wurde gespeichert.`)
      }
    } catch (unknownError: unknown) {
      setAdminError(
        errorMessage(unknownError, 'Studio-Admin konnte nicht gespeichert werden.'),
      )
    } finally {
      setSavingAdmin(false)
    }
  }

  if (user?.role === 'studio_admin') {
    if (user.studio_id === null) {
      return (
        <main className="mx-auto max-w-3xl p-8">
          <h1 className="mb-2 text-3xl font-semibold text-gray-900">
            Dashboard
          </h1>
          <p className="text-gray-600">
            Deinem Konto ist kein Studio zugeordnet. Bitte wende dich an einen
            Administrator.
          </p>
        </main>
      )
    }
    return <StudioAdminDashboard studioId={user.studio_id} />
  }

  if (user?.role !== 'admin') {
    return null
  }

  const inputClass =
    'w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none'

  return (
    <main className="mx-auto max-w-5xl p-8">
      <h1 className="mb-6 text-3xl font-semibold text-gray-900">Dashboard</h1>
      <div className="mb-6 flex gap-2">
        <button
          type="button"
          onClick={() => setTab('studios')}
          className={`rounded-md px-4 py-2 font-medium ${tab === 'studios' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-300'}`}
        >
          Studios verwalten
        </button>
        <button
          type="button"
          onClick={() => setTab('admins')}
          className={`rounded-md px-4 py-2 font-medium ${tab === 'admins' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-300'}`}
        >
          Studio-Admins
        </button>
        <button
          type="button"
          onClick={() => setTab('rooms')}
          className={`rounded-md px-4 py-2 font-medium ${tab === 'rooms' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-300'}`}
        >
          Räume verwalten
        </button>
        <button
          type="button"
          onClick={() => setTab('courses')}
          className={`rounded-md px-4 py-2 font-medium ${tab === 'courses' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-300'}`}
        >
          Kurse planen
        </button>
      </div>

      {(tab === 'rooms' || tab === 'courses') && (
        <div className="mb-6 flex items-center gap-3 rounded-lg bg-white p-4 shadow-md">
          <label htmlFor="managed-studio" className="text-sm font-medium text-gray-700">
            Studio verwalten:
          </label>
          <select
            id="managed-studio"
            value={managedStudioId === null ? '' : managedStudioId}
            onChange={(event) =>
              setManagedStudioId(
                event.target.value === '' ? null : Number(event.target.value),
              )
            }
            className="rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none"
          >
            <option value="">Studio wählen …</option>
            {studios.map((studio) => (
              <option key={studio.id} value={studio.id}>
                {studio.name}
              </option>
            ))}
          </select>
        </div>
      )}
      {tab === 'rooms' &&
        (managedStudioId === null ? (
          <p className="rounded-lg bg-white p-6 text-gray-600 shadow-md">
            Bitte oben ein Studio auswählen, um dessen Räume zu verwalten.
          </p>
        ) : (
          <RoomsSection key={`rooms-${managedStudioId}`} studioId={managedStudioId} />
        ))}
      {tab === 'courses' &&
        (managedStudioId === null ? (
          <p className="rounded-lg bg-white p-6 text-gray-600 shadow-md">
            Bitte oben ein Studio auswählen, um dessen Kurse zu planen.
          </p>
        ) : (
          <CoursesSection key={`courses-${managedStudioId}`} studioId={managedStudioId} />
        ))}

      {tab === 'studios' && (
        <div className="grid gap-8 md:grid-cols-2">
          <section className="rounded-lg bg-white p-6 shadow-md">
            <h2 className="mb-4 text-xl font-semibold text-gray-900">
              Vorhandene Studios
            </h2>
            {loading && <p className="text-gray-600">Wird geladen …</p>}
            {loadError !== null && (
              <p role="alert" className="text-sm text-red-600">
                {loadError}
              </p>
            )}
            {!loading && loadError === null && studios.length === 0 && (
              <p className="text-gray-600">Noch keine Studios vorhanden.</p>
            )}
            <ul className="space-y-2">
              {studios.map((studio) => (
                <li
                  key={studio.id}
                  className="flex items-center justify-between gap-2 rounded-md border border-gray-200 px-3 py-2"
                >
                  <div>
                    <span className="font-medium text-gray-900">{studio.name}</span>
                    <span className="block text-sm text-gray-600">
                      {studio.zip} {studio.city}
                    </span>
                  </div>
                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={() => startStudioEdit(studio)}
                      className="rounded-md border border-gray-300 px-2 py-1 text-sm text-gray-700 hover:bg-gray-100"
                    >
                      Bearbeiten
                    </button>
                    <button
                      type="button"
                      onClick={() => void handleStudioDelete(studio)}
                      className="rounded-md border border-gray-300 px-2 py-1 text-sm text-red-600 hover:bg-gray-100"
                    >
                      Löschen
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          </section>

          <section className="rounded-lg bg-white p-6 shadow-md">
            <div className="mb-4 flex items-center justify-between gap-2">
              <h2 className="text-xl font-semibold text-gray-900">
                {editingStudioId === null ? 'Neues Studio' : 'Studio bearbeiten'}
              </h2>
              <button
                type="button"
                onClick={cancelStudioEdit}
                title="Bearbeitungsmodus beenden und leeres Formular öffnen"
                className="rounded-md border border-indigo-600 px-3 py-1 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
              >
                + Neues Studio
              </button>
            </div>
            <form onSubmit={handleStudioSubmit} className="space-y-3">
              <input
                aria-label="Name"
                placeholder="Name *"
                required
                value={studioForm.name}
                onChange={(event) => setStudioField('name', event.target.value)}
                className={inputClass}
              />
              <input
                aria-label="Straße"
                placeholder="Straße *"
                required
                value={studioForm.street}
                onChange={(event) => setStudioField('street', event.target.value)}
                className={inputClass}
              />
              <div className="grid grid-cols-2 gap-3">
                <input
                  aria-label="PLZ"
                  placeholder="PLZ *"
                  required
                  value={studioForm.zip}
                  onChange={(event) => setStudioField('zip', event.target.value)}
                  className={inputClass}
                />
                <input
                  aria-label="Stadt"
                  placeholder="Stadt *"
                  required
                  value={studioForm.city}
                  onChange={(event) => setStudioField('city', event.target.value)}
                  className={inputClass}
                />
              </div>
              <textarea
                aria-label="Beschreibung"
                placeholder="Beschreibung"
                value={studioForm.description ?? ''}
                onChange={(event) => setStudioField('description', event.target.value)}
                className={inputClass}
              />
              <input
                aria-label="Web-Link"
                placeholder="Web-Link (https://…)"
                value={studioForm.web_link ?? ''}
                onChange={(event) => setStudioField('web_link', event.target.value)}
                className={inputClass}
              />
              <input
                aria-label="Vorschaubild-URL"
                placeholder="Vorschaubild-URL"
                value={studioForm.thumbnail_url ?? ''}
                onChange={(event) => setStudioField('thumbnail_url', event.target.value)}
                className={inputClass}
              />
              <input
                aria-label="Bild-URL"
                placeholder="Bild-URL"
                value={studioForm.image_url ?? ''}
                onChange={(event) => setStudioField('image_url', event.target.value)}
                className={inputClass}
              />
              {studioNotice !== null && (
                <p role="status" className="text-sm text-green-700">
                  {studioNotice}
                </p>
              )}
              {studioError !== null && (
                <p role="alert" className="text-sm text-red-600">
                  {studioError}
                </p>
              )}
              <div className="flex gap-2">
                <button
                  type="submit"
                  disabled={savingStudio}
                  className="flex-1 rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                  {savingStudio
                    ? 'Wird gespeichert …'
                    : editingStudioId === null
                      ? 'Studio anlegen'
                      : 'Änderungen speichern'}
                </button>
                {editingStudioId !== null && (
                  <button
                    type="button"
                    onClick={cancelStudioEdit}
                    className="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100"
                  >
                    Abbrechen
                  </button>
                )}
              </div>
            </form>
          </section>
        </div>
      )}

      {tab === 'admins' && (
        <div className="grid gap-8 md:grid-cols-2">
          <section className="rounded-lg bg-white p-6 shadow-md">
            <h2 className="mb-4 text-xl font-semibold text-gray-900">
              Studio-Admins
            </h2>
            {loading && <p className="text-gray-600">Wird geladen …</p>}
            {!loading && admins.length === 0 && (
              <p className="text-gray-600">Noch keine Studio-Admins vorhanden.</p>
            )}
            <ul className="space-y-2">
              {admins.map((admin) => (
                <li
                  key={admin.id}
                  className="flex items-center justify-between gap-2 rounded-md border border-gray-200 px-3 py-2"
                >
                  <div>
                    <span className="font-medium text-gray-900">{admin.name}</span>
                    <span className="block text-sm text-gray-600">
                      {admin.email} · {studioName(admin.studio_id)}
                    </span>
                  </div>
                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={() => startAdminEdit(admin)}
                      className="rounded-md border border-gray-300 px-2 py-1 text-sm text-gray-700 hover:bg-gray-100"
                    >
                      Bearbeiten
                    </button>
                    <button
                      type="button"
                      onClick={() => void handleAdminDelete(admin)}
                      className="rounded-md border border-gray-300 px-2 py-1 text-sm text-red-600 hover:bg-gray-100"
                    >
                      Löschen
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          </section>

          <section className="rounded-lg bg-white p-6 shadow-md">
            <div className="mb-4 flex items-center justify-between gap-2">
              <h2 className="text-xl font-semibold text-gray-900">
                {editingAdminId === null
                  ? 'Studio-Admin zuweisen'
                  : 'Studio-Admin bearbeiten'}
              </h2>
              <button
                type="button"
                onClick={resetAdminForm}
                title="Bearbeitungsmodus beenden und leeres Formular öffnen"
                className="rounded-md border border-indigo-600 px-3 py-1 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
              >
                + Neuen Admin anlegen
              </button>
            </div>
            <form onSubmit={handleAdminSubmit} className="space-y-3">
              <input
                aria-label="Name"
                placeholder="Name *"
                required
                value={adminName}
                onChange={(event) => setAdminName(event.target.value)}
                className={inputClass}
              />
              <input
                aria-label="E-Mail"
                placeholder="E-Mail *"
                type="email"
                required
                value={adminEmail}
                onChange={(event) => setAdminEmail(event.target.value)}
                className={inputClass}
              />
              <input
                aria-label="Passwort"
                placeholder={
                  editingAdminId === null
                    ? 'Passwort (min. 8 Zeichen) *'
                    : 'Neues Passwort (leer lassen = unverändert)'
                }
                type="password"
                autoComplete="new-password"
                required={editingAdminId === null}
                minLength={8}
                value={adminPassword}
                onChange={(event) => setAdminPassword(event.target.value)}
                className={inputClass}
              />
              <select
                aria-label="Studio"
                required={editingAdminId === null}
                value={adminStudioId}
                onChange={(event) => setAdminStudioId(event.target.value)}
                className={inputClass}
              >
                <option value="">
                  {editingAdminId === null ? 'Studio wählen *' : 'Kein Studio'}
                </option>
                {studios.map((studio) => (
                  <option key={studio.id} value={studio.id}>
                    {studio.name}
                  </option>
                ))}
              </select>
              {adminNotice !== null && (
                <p role="status" className="text-sm text-green-700">
                  {adminNotice}
                </p>
              )}
              {adminError !== null && (
                <p role="alert" className="text-sm text-red-600">
                  {adminError}
                </p>
              )}
              <div className="flex gap-2">
                <button
                  type="submit"
                  disabled={savingAdmin}
                  className="flex-1 rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                  {savingAdmin
                    ? 'Wird gespeichert …'
                    : editingAdminId === null
                      ? 'Studio-Admin anlegen'
                      : 'Änderungen speichern'}
                </button>
                {editingAdminId !== null && (
                  <button
                    type="button"
                    onClick={resetAdminForm}
                    className="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100"
                  >
                    Abbrechen
                  </button>
                )}
              </div>
            </form>
          </section>
        </div>
      )}
    </main>
  )
}

type StudioTab = 'rooms' | 'trainers' | 'courses'

function StudioAdminDashboard({ studioId }: { studioId: number }) {
  const [tab, setTab] = useState<StudioTab>('rooms')

  return (
    <main className="mx-auto max-w-5xl p-8">
      <h1 className="mb-6 text-3xl font-semibold text-gray-900">
        Studioverwaltung
      </h1>
      <div className="mb-6 flex gap-2">
        <button
          type="button"
          onClick={() => setTab('rooms')}
          className={`rounded-md px-4 py-2 font-medium ${tab === 'rooms' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-300'}`}
        >
          Räume
        </button>
        <button
          type="button"
          onClick={() => setTab('trainers')}
          className={`rounded-md px-4 py-2 font-medium ${tab === 'trainers' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-300'}`}
        >
          Trainer
        </button>
        <button
          type="button"
          onClick={() => setTab('courses')}
          className={`rounded-md px-4 py-2 font-medium ${tab === 'courses' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-300'}`}
        >
          Kurse verwalten
        </button>
      </div>
      {tab === 'rooms' && <RoomsSection studioId={studioId} />}
      {tab === 'trainers' && <TrainersSection studioId={studioId} />}
      {tab === 'courses' && <CoursesSection studioId={studioId} />}
    </main>
  )
}

interface RoomFormState {
  name: string
  capacity: string
  description: string
  thumbnail_url: string
  image_url: string
  color_code: string
  location_type: string
  lighting: string
  size_sqm: string
  width_meters: string
  length_meters: string
  height_meters: string
}

const emptyRoomForm: RoomFormState = {
  name: '',
  capacity: '',
  description: '',
  thumbnail_url: '',
  image_url: '',
  color_code: '#4f46e5',
  location_type: 'indoor',
  lighting: 'none',
  size_sqm: '',
  width_meters: '',
  length_meters: '',
  height_meters: '',
}

function roomToForm(room: Room): RoomFormState {
  return {
    name: room.name,
    capacity: String(room.capacity),
    description: room.description ?? '',
    thumbnail_url: room.thumbnail_url ?? '',
    image_url: room.image_url ?? '',
    color_code: room.color_code ?? '#4f46e5',
    location_type: room.location_type,
    lighting: room.lighting ?? 'none',
    size_sqm: room.size_sqm === null ? '' : String(room.size_sqm),
    width_meters: room.width_meters ?? '',
    length_meters: room.length_meters ?? '',
    height_meters: room.height_meters ?? '',
  }
}

function formToPayload(form: RoomFormState): NewRoom {
  const optional = (value: string): string | undefined =>
    value.trim() === '' ? undefined : value.trim()
  const optionalNumber = (value: string): number | undefined =>
    value.trim() === '' ? undefined : Number(value)
  return {
    name: form.name.trim(),
    capacity: Number(form.capacity),
    description: optional(form.description),
    thumbnail_url: optional(form.thumbnail_url),
    image_url: optional(form.image_url),
    color_code: optional(form.color_code),
    location_type: form.location_type,
    lighting: optional(form.lighting),
    size_sqm: optionalNumber(form.size_sqm),
    width_meters: optional(form.width_meters),
    length_meters: optional(form.length_meters),
    height_meters: optional(form.height_meters),
  }
}

function RoomsSection({ studioId }: { studioId: number }) {
  const [rooms, setRooms] = useState<Room[]>([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [form, setForm] = useState<RoomFormState>(emptyRoomForm)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    let active = true
    listRooms(studioId)
      .then((data) => {
        if (active) setRooms(data)
      })
      .catch(() => {
        if (active) setLoadError('Räume konnten nicht geladen werden.')
      })
      .finally(() => {
        if (active) setLoading(false)
      })
    return () => {
      active = false
    }
  }, [studioId])

  function setField(field: keyof RoomFormState, value: string) {
    setForm((previous) => ({ ...previous, [field]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setNotice(null)
    setError(null)
    setSaving(true)
    try {
      if (editingId === null) {
        const created = await createRoom(studioId, formToPayload(form))
        setRooms((previous) => [...previous, created])
        setNotice(`Raum „${created.name}“ wurde angelegt.`)
      } else {
        const updated = await updateRoom(studioId, editingId, formToPayload(form))
        setRooms((previous) =>
          previous.map((room) => (room.id === editingId ? updated : room)),
        )
        setNotice(`Raum „${updated.name}“ wurde gespeichert.`)
      }
      setForm(emptyRoomForm)
      setEditingId(null)
    } catch (unknownError: unknown) {
      setError(errorMessage(unknownError, 'Raum konnte nicht gespeichert werden.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete(room: Room) {
    setNotice(null)
    setError(null)
    try {
      await deleteRoom(studioId, room.id)
      setRooms((previous) => previous.filter((entry) => entry.id !== room.id))
      if (editingId === room.id) {
        setEditingId(null)
        setForm(emptyRoomForm)
      }
      setNotice(`Raum „${room.name}“ wurde gelöscht.`)
    } catch (unknownError: unknown) {
      setError(errorMessage(unknownError, 'Raum konnte nicht gelöscht werden.'))
    }
  }

  function startEdit(room: Room) {
    setNotice(null)
    setError(null)
    setEditingId(room.id)
    setForm(roomToForm(room))
  }

  function cancelEdit() {
    setEditingId(null)
    setForm(emptyRoomForm)
  }

  const inputClass =
    'w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none'

  return (
    <div className="grid gap-8 md:grid-cols-2">
      <section className="rounded-lg bg-white p-6 shadow-md">
        <h2 className="mb-4 text-xl font-semibold text-gray-900">
          Räume des Studios
        </h2>
        {loading && <p className="text-gray-600">Wird geladen …</p>}
        {loadError !== null && (
          <p role="alert" className="text-sm text-red-600">
            {loadError}
          </p>
        )}
        {!loading && loadError === null && rooms.length === 0 && (
          <p className="text-gray-600">Noch keine Räume vorhanden.</p>
        )}
        <ul className="space-y-2">
          {rooms.map((room) => (
            <li
              key={room.id}
              className="rounded-md border border-gray-200 px-3 py-2"
            >
              <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                  <span
                    aria-hidden="true"
                    className="inline-block h-4 w-4 rounded"
                    style={{ backgroundColor: room.color_code ?? '#4f46e5' }}
                  />
                  <span className="font-medium text-gray-900">{room.name}</span>
                </div>
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() => startEdit(room)}
                    className="rounded-md border border-gray-300 px-2 py-1 text-sm text-gray-700 hover:bg-gray-100"
                  >
                    Bearbeiten
                  </button>
                  <button
                    type="button"
                    onClick={() => void handleDelete(room)}
                    className="rounded-md border border-gray-300 px-2 py-1 text-sm text-red-600 hover:bg-gray-100"
                  >
                    Löschen
                  </button>
                </div>
              </div>
              <span className="block text-sm text-gray-600">
                {room.capacity} Plätze · {room.location_type}
                {room.size_sqm !== null && ` · ${room.size_sqm} m²`}
              </span>
            </li>
          ))}
        </ul>
      </section>

      <section className="rounded-lg bg-white p-6 shadow-md">
        <h2 className="mb-4 text-xl font-semibold text-gray-900">
          {editingId === null ? 'Neuer Raum' : 'Raum bearbeiten'}
        </h2>
        <form onSubmit={handleSubmit} className="space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <input
              aria-label="Name"
              placeholder="Name *"
              required
              value={form.name}
              onChange={(event) => setField('name', event.target.value)}
              className={inputClass}
            />
            <input
              aria-label="Kapazität"
              placeholder="Kapazität *"
              type="number"
              min={1}
              required
              value={form.capacity}
              onChange={(event) => setField('capacity', event.target.value)}
              className={inputClass}
            />
          </div>
          <textarea
            aria-label="Beschreibung"
            placeholder="Beschreibung"
            value={form.description}
            onChange={(event) => setField('description', event.target.value)}
            className={inputClass}
          />
          <div className="grid grid-cols-2 gap-3">
            <input
              aria-label="Vorschaubild-URL"
              placeholder="Vorschaubild-URL"
              value={form.thumbnail_url}
              onChange={(event) => setField('thumbnail_url', event.target.value)}
              className={inputClass}
            />
            <input
              aria-label="Bild-URL"
              placeholder="Bild-URL"
              value={form.image_url}
              onChange={(event) => setField('image_url', event.target.value)}
              className={inputClass}
            />
          </div>
          <div className="grid grid-cols-3 gap-3">
            <label className="block text-sm text-gray-700">
              Kalender-Farbe
              <input
                aria-label="Kalender-Farbe"
                type="color"
                value={form.color_code}
                onChange={(event) => setField('color_code', event.target.value)}
                className="mt-1 h-10 w-full rounded-md border border-gray-300"
              />
            </label>
            <label className="block text-sm text-gray-700">
              Lage
              <select
                aria-label="Lage"
                value={form.location_type}
                onChange={(event) => setField('location_type', event.target.value)}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900"
              >
                <option value="indoor">Indoor</option>
                <option value="outdoor">Outdoor</option>
              </select>
            </label>
            <label className="block text-sm text-gray-700">
              Beleuchtung
              <select
                aria-label="Beleuchtung"
                value={form.lighting}
                onChange={(event) => setField('lighting', event.target.value)}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900"
              >
                <option value="none">Keine</option>
                <option value="daylight">Tageslicht</option>
                <option value="warm">Warm</option>
                <option value="spotlight">Spotlight</option>
                <option value="led">LED</option>
              </select>
            </label>
          </div>
          <div className="grid grid-cols-4 gap-3">
            <input
              aria-label="Fläche in m²"
              placeholder="m²"
              type="number"
              min={0}
              value={form.size_sqm}
              onChange={(event) => setField('size_sqm', event.target.value)}
              className={inputClass}
            />
            <input
              aria-label="Breite in Metern"
              placeholder="Breite (m)"
              type="number"
              min={0}
              step="0.01"
              value={form.width_meters}
              onChange={(event) => setField('width_meters', event.target.value)}
              className={inputClass}
            />
            <input
              aria-label="Länge in Metern"
              placeholder="Länge (m)"
              type="number"
              min={0}
              step="0.01"
              value={form.length_meters}
              onChange={(event) => setField('length_meters', event.target.value)}
              className={inputClass}
            />
            <input
              aria-label="Höhe in Metern"
              placeholder="Höhe (m)"
              type="number"
              min={0}
              step="0.01"
              value={form.height_meters}
              onChange={(event) => setField('height_meters', event.target.value)}
              className={inputClass}
            />
          </div>
          {notice !== null && (
            <p role="status" className="text-sm text-green-700">
              {notice}
            </p>
          )}
          {error !== null && (
            <p role="alert" className="text-sm text-red-600">
              {error}
            </p>
          )}
          <div className="flex gap-2">
            <button
              type="submit"
              disabled={saving}
              className="flex-1 rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            >
              {saving
                ? 'Wird gespeichert …'
                : editingId === null
                  ? 'Raum anlegen'
                  : 'Änderungen speichern'}
            </button>
            {editingId !== null && (
              <button
                type="button"
                onClick={cancelEdit}
                className="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100"
              >
                Abbrechen
              </button>
            )}
          </div>
        </form>
      </section>
    </div>
  )
}

interface CourseFormState {
  title: string
  description: string
  day_of_week: string
  start_time: string
  end_time: string
  trainer_id: string
  room_id: string
  thumbnail_url: string
  image_url: string
  web_link: string
  capacity: string
  generate: boolean
}

const emptyCourseForm: CourseFormState = {
  title: '',
  description: '',
  day_of_week: '1',
  start_time: '09:00',
  end_time: '10:00',
  trainer_id: '',
  room_id: '',
  thumbnail_url: '',
  image_url: '',
  web_link: '',
  capacity: '',
  generate: true,
}

function courseToForm(course: Course): CourseFormState {
  return {
    title: course.title,
    description: course.description ?? '',
    day_of_week: String(course.day_of_week),
    start_time: course.start_time.slice(0, 5),
    end_time: course.end_time.slice(0, 5),
    trainer_id: course.trainer_id === null ? '' : String(course.trainer_id),
    room_id: course.room_id === null ? '' : String(course.room_id),
    thumbnail_url: course.thumbnail_url ?? '',
    image_url: course.image_url ?? '',
    web_link: course.web_link ?? '',
    capacity: course.capacity === null ? '' : String(course.capacity),
    generate: false,
  }
}

function CoursesSection({ studioId }: { studioId: number }) {
  const [courses, setCourses] = useState<Course[]>([])
  const [trainers, setTrainers] = useState<Trainer[]>([])
  const [rooms, setRooms] = useState<Room[]>([])
  const [loading, setLoading] = useState(true)
  const [coursesError, setCoursesError] = useState<string | null>(null)
  const [trainersError, setTrainersError] = useState<string | null>(null)
  const [roomsError, setRoomsError] = useState<string | null>(null)
  const [form, setForm] = useState<CourseFormState>(emptyCourseForm)
  const [editingCourseId, setEditingCourseId] = useState<number | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [expandedId, setExpandedId] = useState<number | null>(null)
  const [sessions, setSessions] = useState<Record<number, CourseSession[]>>({})
  const [editingSessionId, setEditingSessionId] = useState<number | null>(null)
  const [editTrainer, setEditTrainer] = useState('')
  const [editStatus, setEditStatus] = useState<SessionStatus>('scheduled')
  const [editInfo, setEditInfo] = useState('')

  useEffect(() => {
    let active = true
    // allSettled statt Promise.all: ein fehlender Teil (z. B. Trainer) darf
    // die übrigen Dropdowns (z. B. Räume) nicht mit leeren.
    Promise.allSettled([
      listCourses(studioId),
      listTrainers(studioId),
      listRooms(studioId),
    ])
      .then(([courseResult, trainerResult, roomResult]) => {
        if (!active) return
        if (courseResult.status === 'fulfilled') {
          setCourses(courseResult.value)
        } else {
          setCoursesError('Kurse konnten nicht geladen werden.')
        }
        if (trainerResult.status === 'fulfilled') {
          setTrainers(trainerResult.value)
        } else {
          setTrainersError('Trainer konnten nicht geladen werden.')
        }
        if (roomResult.status === 'fulfilled') {
          setRooms(roomResult.value)
        } else {
          setRoomsError('Räume konnten nicht geladen werden.')
        }
      })
      .finally(() => {
        if (active) setLoading(false)
      })
    return () => {
      active = false
    }
  }, [studioId])

  function setField(field: keyof Omit<CourseFormState, 'generate'>, value: string) {
    setForm((previous) => ({ ...previous, [field]: value }))
  }

  function coursePayload(): NewCourse {
    return {
      title: form.title.trim(),
      description: form.description.trim() === '' ? undefined : form.description.trim(),
      day_of_week: Number(form.day_of_week),
      start_time: form.start_time.length === 5 ? `${form.start_time}:00` : form.start_time,
      end_time: form.end_time.length === 5 ? `${form.end_time}:00` : form.end_time,
      trainer_id: form.trainer_id === '' ? null : Number(form.trainer_id),
      room_id: form.room_id === '' ? null : Number(form.room_id),
      thumbnail_url: form.thumbnail_url.trim() === '' ? undefined : form.thumbnail_url.trim(),
      image_url: form.image_url.trim() === '' ? undefined : form.image_url.trim(),
      web_link: form.web_link.trim() === '' ? undefined : form.web_link.trim(),
      capacity: form.capacity.trim() === '' ? null : Number(form.capacity),
    }
  }

  function startCourseEdit(course: Course) {
    setNotice(null)
    setError(null)
    setEditingCourseId(course.id)
    setForm(courseToForm(course))
  }

  function cancelCourseEdit() {
    setEditingCourseId(null)
    setForm(emptyCourseForm)
    setNotice(null)
    setError(null)
  }

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setNotice(null)
    setError(null)
    setSaving(true)
    try {
      if (editingCourseId === null) {
        const created = await createCourse(studioId, coursePayload())
        let suffix = ''
        if (form.generate) {
          const generated = await generateSessions(studioId, created.id, 4)
          suffix = ` ${generated.created} Termine für 4 Wochen angelegt.`
        }
        setCourses((previous) => [...previous, created])
        setForm(emptyCourseForm)
        setNotice(`Kurs „${created.title}“ wurde angelegt.${suffix}`)
      } else {
        const updated = await updateCourse(studioId, editingCourseId, coursePayload())
        setCourses((previous) =>
          previous.map((entry) => (entry.id === updated.id ? updated : entry)),
        )
        setForm(emptyCourseForm)
        setEditingCourseId(null)
        setNotice(`Kurs „${updated.title}“ wurde gespeichert.`)
      }
    } catch (unknownError: unknown) {
      setError(
        errorMessage(
          unknownError,
          editingCourseId === null
            ? 'Kurs konnte nicht angelegt werden.'
            : 'Kurs konnte nicht gespeichert werden.',
        ),
      )
    } finally {
      setSaving(false)
    }
  }

  async function toggleSessions(course: Course) {
    if (expandedId === course.id) {
      setExpandedId(null)
      return
    }
    setExpandedId(course.id)
    setEditingSessionId(null)
    try {
      const data = await listSessions(studioId, course.id)
      setSessions((previous) => ({ ...previous, [course.id]: data }))
    } catch (unknownError: unknown) {
      setError(errorMessage(unknownError, 'Termine konnten nicht geladen werden.'))
    }
  }

  async function handleGenerate(course: Course) {
    setNotice(null)
    setError(null)
    try {
      const generated = await generateSessions(studioId, course.id, 4)
      const data = await listSessions(studioId, course.id)
      setSessions((previous) => ({ ...previous, [course.id]: data }))
      if (expandedId !== course.id) setExpandedId(course.id)
      setNotice(`${generated.created} Termine für „${course.title}“ angelegt.`)
    } catch (unknownError: unknown) {
      setError(errorMessage(unknownError, 'Termine konnten nicht erzeugt werden.'))
    }
  }

  function startSessionEdit(session: CourseSession) {
    setEditingSessionId(session.id)
    setEditTrainer(session.trainer_id === null ? '' : String(session.trainer_id))
    setEditStatus(session.status)
    setEditInfo(session.session_info ?? '')
  }

  async function handleSessionSave(courseId: number) {
    if (editingSessionId === null) return
    setNotice(null)
    setError(null)
    try {
      const updated = await updateSession(studioId, courseId, editingSessionId, {
        trainer_id: editTrainer === '' ? null : Number(editTrainer),
        status: editStatus,
        session_info: editInfo.trim() === '' ? null : editInfo.trim(),
      })
      setSessions((previous) => ({
        ...previous,
        [courseId]: (previous[courseId] ?? []).map((entry) =>
          entry.id === updated.id ? updated : entry,
        ),
      }))
      setEditingSessionId(null)
      setNotice('Termin wurde gespeichert.')
    } catch (unknownError: unknown) {
      setError(errorMessage(unknownError, 'Termin konnte nicht gespeichert werden.'))
    }
  }

  function trainerName(trainerId: number | null): string {
    if (trainerId === null) return 'Lead-Trainer'
    return trainers.find((entry) => entry.id === trainerId)?.name ?? 'Unbekannt'
  }

  function roomName(roomId: number | null): string {
    if (roomId === null) return 'Kein Raum'
    return rooms.find((entry) => entry.id === roomId)?.name ?? 'Unbekannt'
  }

  const inputClass =
    'w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none'

  return (
    <div className="grid gap-8 md:grid-cols-2">
      <section className="rounded-lg bg-white p-6 shadow-md">
        <h2 className="mb-4 text-xl font-semibold text-gray-900">Kursvorlagen</h2>
        {loading && <p className="text-gray-600">Wird geladen …</p>}
        {coursesError !== null && (
          <p role="alert" className="text-sm text-red-600">
            {coursesError}
          </p>
        )}
        <ul className="space-y-2">
          {courses.map((course) => (
            <li
              key={course.id}
              className="rounded-md border border-gray-200 px-3 py-2"
            >
              <div className="flex w-full items-center justify-between gap-2 text-left">
                <button
                  type="button"
                  onClick={() => void toggleSessions(course)}
                  className="flex flex-1 items-center justify-between gap-2 text-left"
                >
                  <div>
                    <span className="font-medium text-gray-900">{course.title}</span>
                    <span className="block text-sm text-gray-600">
                      {weekdayName(course.day_of_week)} · {course.start_time.slice(0, 5)}–
                      {course.end_time.slice(0, 5)} · {trainerName(course.trainer_id)} ·{' '}
                      {roomName(course.room_id)}
                    </span>
                  </div>
                  <span className="shrink-0 text-sm text-indigo-600">
                    {expandedId === course.id ? 'Schließen' : 'Termine'}
                  </span>
                </button>
                <button
                  type="button"
                  onClick={() => startCourseEdit(course)}
                  className="shrink-0 rounded-md border border-gray-300 px-2 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                  Bearbeiten
                </button>
              </div>
              {expandedId === course.id && (
                <div className="mt-3 space-y-2 border-t border-gray-100 pt-3">
                  {(sessions[course.id] ?? []).map((session) => (
                    <div
                      key={session.id}
                      className="rounded-md bg-gray-50 px-3 py-2"
                    >
                      <div className="flex items-center justify-between gap-2">
                        <div>
                          <span className="font-medium text-gray-900">{session.date}</span>
                          <span className="block text-sm text-gray-600">
                            {session.status} · {trainerName(session.trainer_id)}
                            {session.session_info !== null &&
                              ` · ${session.session_info}`}
                          </span>
                        </div>
                        <button
                          type="button"
                          onClick={() => startSessionEdit(session)}
                          className="rounded-md border border-gray-300 px-2 py-1 text-sm text-gray-700 hover:bg-gray-100"
                        >
                          Bearbeiten
                        </button>
                      </div>
                      {editingSessionId === session.id && (
                        <div className="mt-2 space-y-2">
                          <select
                            aria-label="Vertretungstrainer"
                            value={editTrainer}
                            onChange={(event) => setEditTrainer(event.target.value)}
                            className={inputClass}
                          >
                            <option value="">Lead-Trainer</option>
                            {trainers.map((entry) => (
                              <option key={entry.id} value={entry.id}>
                                {entry.name}
                              </option>
                            ))}
                          </select>
                          <select
                            aria-label="Status"
                            value={editStatus}
                            onChange={(event) =>
                              setEditStatus(event.target.value as SessionStatus)
                            }
                            className={inputClass}
                          >
                            <option value="scheduled">Geplant</option>
                            <option value="substituted">Vertretung</option>
                            <option value="cancelled">Abgesagt</option>
                          </select>
                          <textarea
                            aria-label="Mitteilung an Teilnehmer"
                            placeholder="Mitteilung an die Teilnehmer"
                            value={editInfo}
                            onChange={(event) => setEditInfo(event.target.value)}
                            className={inputClass}
                          />
                          <button
                            type="button"
                            onClick={() => void handleSessionSave(course.id)}
                            className="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700"
                          >
                            Termin speichern
                          </button>
                        </div>
                      )}
                    </div>
                  ))}
                  {(sessions[course.id] ?? []).length === 0 && (
                    <p className="text-sm text-gray-600">Noch keine Termine.</p>
                  )}
                  <button
                    type="button"
                    onClick={() => void handleGenerate(course)}
                    className="w-full rounded-md border border-indigo-600 px-4 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
                  >
                    Termine für 4 Wochen erzeugen
                  </button>
                </div>
              )}
            </li>
          ))}
        </ul>
        {notice !== null && (
          <p role="status" className="mt-4 text-sm text-green-700">
            {notice}
          </p>
        )}
        {error !== null && (
          <p role="alert" className="mt-4 text-sm text-red-600">
            {error}
          </p>
        )}
      </section>

      <section className="rounded-lg bg-white p-6 shadow-md">
        <div className="mb-4 flex items-center justify-between gap-2">
          <h2 className="text-xl font-semibold text-gray-900">
            {editingCourseId === null ? 'Neuer wiederkehrender Kurs' : 'Kurs bearbeiten'}
          </h2>
          <button
            type="button"
            onClick={cancelCourseEdit}
            title="Bearbeitungsmodus beenden und leeres Formular öffnen"
            className="rounded-md border border-indigo-600 px-3 py-1 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
          >
            + Neuen Kurs planen
          </button>
        </div>
        <form onSubmit={handleCreate} className="space-y-3">
          <input
            aria-label="Titel"
            placeholder="Titel *"
            required
            value={form.title}
            onChange={(event) => setField('title', event.target.value)}
            className={inputClass}
          />
          <textarea
            aria-label="Beschreibung"
            placeholder="Beschreibung"
            value={form.description}
            onChange={(event) => setField('description', event.target.value)}
            className={inputClass}
          />
          <div className="grid grid-cols-3 gap-3">
            <label className="block text-sm text-gray-700">
              Wochentag
              <select
                aria-label="Wochentag"
                value={form.day_of_week}
                onChange={(event) => setField('day_of_week', event.target.value)}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900"
              >
                {[1, 2, 3, 4, 5, 6, 7].map((day) => (
                  <option key={day} value={day}>
                    {weekdayName(day)}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm text-gray-700">
              Startzeit
              <input
                aria-label="Startzeit"
                type="time"
                required
                value={form.start_time}
                onChange={(event) => setField('start_time', event.target.value)}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900"
              />
            </label>
            <label className="block text-sm text-gray-700">
              Endzeit
              <input
                aria-label="Endzeit"
                type="time"
                required
                value={form.end_time}
                onChange={(event) => setField('end_time', event.target.value)}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900"
              />
            </label>
          </div>
          <label className="block text-sm text-gray-700">
            Standard-Trainer (Lead)
            <select
              aria-label="Standard-Trainer"
              value={form.trainer_id}
              onChange={(event) => setField('trainer_id', event.target.value)}
              className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900"
            >
              <option value="">Kein Lead-Trainer</option>
              {trainers.map((entry) => (
                <option key={entry.id} value={entry.id}>
                  {entry.name}
                </option>
              ))}
            </select>
            {trainersError !== null && (
              <p role="alert" className="mt-1 text-sm text-red-600">
                {trainersError}
              </p>
            )}
          </label>
          <label className="block text-sm text-gray-700">
            Standard-Raum
            <select
              aria-label="Standard-Raum"
              value={form.room_id}
              onChange={(event) => setField('room_id', event.target.value)}
              className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900"
            >
              <option value="">Kein Raum</option>
              {rooms.map((entry) => (
                <option key={entry.id} value={entry.id}>
                  {entry.name}
                </option>
              ))}
            </select>
            {roomsError !== null && (
              <p role="alert" className="mt-1 text-sm text-red-600">
                {roomsError}
              </p>
            )}
          </label>
          <input
            aria-label="Kapazität"
            placeholder="Kapazität (leer = vom Raum erben)"
            type="number"
            min={1}
            value={form.capacity}
            onChange={(event) => setField('capacity', event.target.value)}
            className={inputClass}
          />
          <input
            aria-label="Vorschaubild-URL"
            placeholder="Vorschaubild-URL"
            value={form.thumbnail_url}
            onChange={(event) => setField('thumbnail_url', event.target.value)}
            className={inputClass}
          />
          <input
            aria-label="Bild-URL"
            placeholder="Bild-URL"
            value={form.image_url}
            onChange={(event) => setField('image_url', event.target.value)}
            className={inputClass}
          />
          <input
            aria-label="Web-Link"
            placeholder="Web-Link (https://…)"
            value={form.web_link}
            onChange={(event) => setField('web_link', event.target.value)}
            className={inputClass}
          />
          {editingCourseId === null && (
            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input
                type="checkbox"
                checked={form.generate}
                onChange={(event) =>
                  setForm((previous) => ({ ...previous, generate: event.target.checked }))
                }
              />
              Termine für die nächsten 4 Wochen erzeugen
            </label>
          )}
          <button
            type="submit"
            disabled={saving}
            className="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {saving
              ? 'Wird gespeichert …'
              : editingCourseId === null
                ? 'Kurs anlegen'
                : 'Änderungen speichern'}
          </button>
          {editingCourseId !== null && (
            <button
              type="button"
              onClick={cancelCourseEdit}
              className="w-full rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100"
            >
              Abbrechen
            </button>
          )}
        </form>
      </section>
    </div>
  )
}

function TrainersSection({ studioId }: { studioId: number }) {
  const [trainers, setTrainers] = useState<Trainer[]>([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const [regName, setRegName] = useState('')
  const [regEmail, setRegEmail] = useState('')
  const [regPassword, setRegPassword] = useState('')
  const [attachEmail, setAttachEmail] = useState('')
  const [editingTrainerId, setEditingTrainerId] = useState<number | null>(null)

  function startTrainerEdit(trainer: Trainer) {
    setNotice(null)
    setError(null)
    setEditingTrainerId(trainer.id)
    setRegName(trainer.name)
    setRegEmail(trainer.email)
    setRegPassword('')
  }

  function cancelTrainerEdit() {
    setEditingTrainerId(null)
    setRegName('')
    setRegEmail('')
    setRegPassword('')
    setNotice(null)
    setError(null)
  }

  useEffect(() => {
    let active = true
    listTrainers(studioId)
      .then((data) => {
        if (active) setTrainers(data)
      })
      .catch(() => {
        if (active) setLoadError('Trainer konnten nicht geladen werden.')
      })
      .finally(() => {
        if (active) setLoading(false)
      })
    return () => {
      active = false
    }
  }, [studioId])

  async function handleRegister(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setNotice(null)
    setError(null)
    setSaving(true)
    try {
      if (editingTrainerId === null) {
        const created = await registerTrainer(studioId, {
          name: regName,
          email: regEmail,
          password: regPassword,
        })
        setTrainers((previous) => [
          ...previous,
          { id: created.id, name: created.name, email: created.email },
        ])
        setRegName('')
        setRegEmail('')
        setRegPassword('')
        setNotice(`Trainer „${created.name}“ wurde registriert und freigeschaltet.`)
      } else {
        const updated = await updateTrainer(studioId, editingTrainerId, {
          name: regName,
          email: regEmail,
          ...(regPassword !== '' ? { password: regPassword } : {}),
        })
        setTrainers((previous) =>
          previous.map((entry) => (entry.id === updated.id ? updated : entry)),
        )
        cancelTrainerEdit()
        setNotice(`Trainer „${updated.name}“ wurde gespeichert.`)
      }
    } catch (unknownError: unknown) {
      setError(errorMessage(unknownError, 'Trainer konnte nicht gespeichert werden.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleAttach(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setNotice(null)
    setError(null)
    setSaving(true)
    try {
      const linked = await attachTrainer(studioId, attachEmail)
      setTrainers((previous) =>
        previous.some((entry) => entry.id === linked.id)
          ? previous
          : [...previous, { id: linked.id, name: linked.name, email: linked.email }],
      )
      setAttachEmail('')
      setNotice(`Trainer „${linked.name}“ wurde für dieses Studio freigeschaltet.`)
    } catch (unknownError: unknown) {
      setError(errorMessage(unknownError, 'Trainer konnte nicht freigeschaltet werden.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleDetach(trainer: Trainer) {
    setNotice(null)
    setError(null)
    try {
      await detachTrainer(studioId, trainer.id)
      setTrainers((previous) => previous.filter((entry) => entry.id !== trainer.id))
      if (editingTrainerId === trainer.id) {
        cancelTrainerEdit()
      }
      setNotice(`Trainer „${trainer.name}“ wurde entfernt.`)
    } catch (unknownError: unknown) {
      setError(errorMessage(unknownError, 'Trainer konnte nicht entfernt werden.'))
    }
  }

  const inputClass =
    'w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none'

  return (
    <div className="grid gap-8 md:grid-cols-2">
      <section className="rounded-lg bg-white p-6 shadow-md">
        <h2 className="mb-4 text-xl font-semibold text-gray-900">
          Freigeschaltete Trainer
        </h2>
        {loading && <p className="text-gray-600">Wird geladen …</p>}
        {loadError !== null && (
          <p role="alert" className="text-sm text-red-600">
            {loadError}
          </p>
        )}
        {!loading && loadError === null && trainers.length === 0 && (
          <p className="text-gray-600">Noch keine Trainer freigeschaltet.</p>
        )}
        <ul className="space-y-2">
          {trainers.map((trainer) => (
            <li
              key={trainer.id}
              className="flex items-center justify-between gap-2 rounded-md border border-gray-200 px-3 py-2"
            >
              <div>
                <span className="font-medium text-gray-900">{trainer.name}</span>
                <span className="block text-sm text-gray-600">{trainer.email}</span>
              </div>
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => startTrainerEdit(trainer)}
                  className="rounded-md border border-gray-300 px-2 py-1 text-sm text-gray-700 hover:bg-gray-100"
                >
                  Bearbeiten
                </button>
                <button
                  type="button"
                  onClick={() => void handleDetach(trainer)}
                  className="rounded-md border border-gray-300 px-2 py-1 text-sm text-red-600 hover:bg-gray-100"
                >
                  Entfernen
                </button>
              </div>
            </li>
          ))}
        </ul>
        {notice !== null && (
          <p role="status" className="mt-4 text-sm text-green-700">
            {notice}
          </p>
        )}
        {error !== null && (
          <p role="alert" className="mt-4 text-sm text-red-600">
            {error}
          </p>
        )}
      </section>

      <div className="space-y-8">
        <section className="rounded-lg bg-white p-6 shadow-md">
          <div className="mb-4 flex items-center justify-between gap-2">
            <h2 className="text-xl font-semibold text-gray-900">
              {editingTrainerId === null ? 'Neuen Trainer registrieren' : 'Trainer bearbeiten'}
            </h2>
            <button
              type="button"
              onClick={cancelTrainerEdit}
              title="Bearbeitungsmodus beenden und leeres Formular öffnen"
              className="rounded-md border border-indigo-600 px-3 py-1 text-sm font-medium text-indigo-600 hover:bg-indigo-50"
            >
              + Neuen Trainer anlegen
            </button>
          </div>
          <form onSubmit={handleRegister} className="space-y-3">
            <input
              aria-label="Name"
              placeholder="Name *"
              required
              value={regName}
              onChange={(event) => setRegName(event.target.value)}
              className={inputClass}
            />
            <input
              aria-label="E-Mail"
              placeholder="E-Mail *"
              type="email"
              required
              value={regEmail}
              onChange={(event) => setRegEmail(event.target.value)}
              className={inputClass}
            />
            <input
              aria-label="Passwort"
              placeholder={
                editingTrainerId === null
                  ? 'Passwort (min. 8 Zeichen) *'
                  : 'Neues Passwort (leer lassen = unverändert)'
              }
              type="password"
              autoComplete="new-password"
              required={editingTrainerId === null}
              minLength={8}
              value={regPassword}
              onChange={(event) => setRegPassword(event.target.value)}
              className={inputClass}
            />
            <div className="flex gap-2">
              <button
                type="submit"
                disabled={saving}
                className="flex-1 rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
              >
                {saving
                  ? 'Wird gespeichert …'
                  : editingTrainerId === null
                    ? 'Trainer registrieren'
                    : 'Änderungen speichern'}
              </button>
              {editingTrainerId !== null && (
                <button
                  type="button"
                  onClick={cancelTrainerEdit}
                  className="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100"
                >
                  Abbrechen
                </button>
              )}
            </div>
          </form>
        </section>

        <section className="rounded-lg bg-white p-6 shadow-md">
          <h2 className="mb-4 text-xl font-semibold text-gray-900">
            Bestehenden Trainer freischalten
          </h2>
          <form onSubmit={handleAttach} className="space-y-3">
            <input
              aria-label="E-Mail des Trainers"
              placeholder="E-Mail des Trainers *"
              type="email"
              required
              value={attachEmail}
              onChange={(event) => setAttachEmail(event.target.value)}
              className={inputClass}
            />
            <button
              type="submit"
              disabled={saving}
              className="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            >
              {saving ? 'Wird freigeschaltet …' : 'Freischalten'}
            </button>
          </form>
        </section>
      </div>
    </div>
  )
}
