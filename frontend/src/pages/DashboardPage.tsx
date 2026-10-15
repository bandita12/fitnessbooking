import axios from 'axios'
import { useEffect, useState, type FormEvent } from 'react'
import { createStudioAdmin } from '../api/admin'
import {
  createStudio,
  listStudios,
  type NewStudio,
  type Studio,
} from '../api/studios'
import { useAuth } from '../context/useAuth'

type Tab = 'studios' | 'admins'

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

  const [adminName, setAdminName] = useState('')
  const [adminEmail, setAdminEmail] = useState('')
  const [adminPassword, setAdminPassword] = useState('')
  const [adminStudioId, setAdminStudioId] = useState('')
  const [adminNotice, setAdminNotice] = useState<string | null>(null)
  const [adminError, setAdminError] = useState<string | null>(null)
  const [savingAdmin, setSavingAdmin] = useState(false)

  useEffect(() => {
    let active = true
    listStudios()
      .then((data) => {
        if (active) setStudios(data)
      })
      .catch(() => {
        if (active) setLoadError('Studios konnten nicht geladen werden.')
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

  async function handleStudioSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setStudioNotice(null)
    setStudioError(null)
    setSavingStudio(true)
    try {
      const created = await createStudio({
        ...studioForm,
        description: studioForm.description?.trim() === '' ? undefined : studioForm.description,
        thumbnail_url:
          studioForm.thumbnail_url?.trim() === '' ? undefined : studioForm.thumbnail_url,
        image_url: studioForm.image_url?.trim() === '' ? undefined : studioForm.image_url,
        web_link: studioForm.web_link?.trim() === '' ? undefined : studioForm.web_link,
      })
      setStudios((previous) => [...previous, created])
      setStudioForm(emptyStudio)
      setStudioNotice(`Studio „${created.name}“ wurde angelegt.`)
    } catch (unknownError: unknown) {
      setStudioError(errorMessage(unknownError, 'Studio konnte nicht angelegt werden.'))
    } finally {
      setSavingStudio(false)
    }
  }

  async function handleAdminSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setAdminNotice(null)
    setAdminError(null)
    setSavingAdmin(true)
    try {
      const created = await createStudioAdmin({
        name: adminName,
        email: adminEmail,
        password: adminPassword,
        studio_id: Number(adminStudioId),
      })
      setAdminName('')
      setAdminEmail('')
      setAdminPassword('')
      setAdminStudioId('')
      setAdminNotice(`Studio-Admin „${created.name}“ wurde angelegt.`)
    } catch (unknownError: unknown) {
      setAdminError(errorMessage(unknownError, 'Studio-Admin konnte nicht angelegt werden.'))
    } finally {
      setSavingAdmin(false)
    }
  }

  if (user?.role !== 'admin') {
    return (
      <main className="mx-auto max-w-3xl p-8">
        <h1 className="mb-2 text-3xl font-semibold text-gray-900">Dashboard</h1>
        <p className="text-gray-600">
          Die Verwaltung deines Studios folgt in einem späteren Schritt.
        </p>
      </main>
    )
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
      </div>

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
                  className="rounded-md border border-gray-200 px-3 py-2"
                >
                  <span className="font-medium text-gray-900">{studio.name}</span>
                  <span className="block text-sm text-gray-600">
                    {studio.zip} {studio.city}
                  </span>
                </li>
              ))}
            </ul>
          </section>

          <section className="rounded-lg bg-white p-6 shadow-md">
            <h2 className="mb-4 text-xl font-semibold text-gray-900">
              Neues Studio
            </h2>
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
              <button
                type="submit"
                disabled={savingStudio}
                className="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
              >
                {savingStudio ? 'Wird angelegt …' : 'Studio anlegen'}
              </button>
            </form>
          </section>
        </div>
      )}

      {tab === 'admins' && (
        <section className="max-w-xl rounded-lg bg-white p-6 shadow-md">
          <h2 className="mb-4 text-xl font-semibold text-gray-900">
            Studio-Admin zuweisen
          </h2>
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
              placeholder="Passwort (min. 8 Zeichen) *"
              type="password"
              autoComplete="new-password"
              required
              minLength={8}
              value={adminPassword}
              onChange={(event) => setAdminPassword(event.target.value)}
              className={inputClass}
            />
            <select
              aria-label="Studio"
              required
              value={adminStudioId}
              onChange={(event) => setAdminStudioId(event.target.value)}
              className={inputClass}
            >
              <option value="">Studio wählen *</option>
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
            <button
              type="submit"
              disabled={savingAdmin}
              className="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            >
              {savingAdmin ? 'Wird angelegt …' : 'Studio-Admin anlegen'}
            </button>
          </form>
        </section>
      )}
    </main>
  )
}
