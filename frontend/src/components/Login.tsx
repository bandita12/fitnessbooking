import axios from 'axios'
import { useState, type FormEvent } from 'react'
import { useAuth } from '../context/useAuth'

type Mode = 'login' | 'register'

type FieldErrors = Partial<Record<'name' | 'email' | 'password' | 'password_confirmation', string>>

function extractFieldErrors(unknownError: unknown): FieldErrors | null {
  if (!axios.isAxiosError(unknownError)) return null
  const errors = unknownError.response?.data?.errors
  if (errors === null || errors === undefined || typeof errors !== 'object') return null
  const result: FieldErrors = {}
  for (const [key, value] of Object.entries(errors as Record<string, unknown>)) {
    if (
      (key === 'name' || key === 'email' || key === 'password' || key === 'password_confirmation') &&
      Array.isArray(value) &&
      typeof value[0] === 'string'
    ) {
      result[key] = value[0]
    }
  }
  return Object.keys(result).length > 0 ? result : null
}

export default function Login() {
  const { login, register } = useAuth()
  const [mode, setMode] = useState<Mode>('login')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
  const [generalError, setGeneralError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const isRegister = mode === 'register'
  const inputClass =
    'w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none'

  function switchMode(next: Mode) {
    setMode(next)
    setFieldErrors({})
    setGeneralError(null)
  }

  function fieldError(field: keyof FieldErrors): string | null {
    return fieldErrors[field] ?? null
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFieldErrors({})
    setGeneralError(null)
    setSubmitting(true)
    try {
      if (isRegister) {
        await register(name.trim(), email, password, passwordConfirmation)
      } else {
        await login(email, password)
      }
    } catch (unknownError: unknown) {
      const mapped = extractFieldErrors(unknownError)
      if (mapped !== null) {
        setFieldErrors(mapped)
      } else if (
        axios.isAxiosError(unknownError) &&
        unknownError.response?.status === 422
      ) {
        setGeneralError(
          isRegister
            ? 'Registrierung fehlgeschlagen. Bitte Eingaben prüfen.'
            : 'E-Mail oder Passwort ist falsch.',
        )
      } else {
        setGeneralError(
          isRegister
            ? 'Registrierung fehlgeschlagen. Bitte versuche es erneut.'
            : 'Anmeldung fehlgeschlagen. Bitte versuche es erneut.',
        )
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4">
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-sm rounded-lg bg-white p-8 shadow-md"
      >
        <h1 className="mb-6 text-center text-2xl font-semibold text-gray-900">
          {isRegister ? 'Registrieren' : 'Anmelden'}
        </h1>
        {isRegister && (
          <>
            <label
              htmlFor="register-name"
              className="mb-1 block text-sm font-medium text-gray-700"
            >
              Name
            </label>
            <input
              id="register-name"
              type="text"
              autoComplete="name"
              required
              value={name}
              onChange={(event) => setName(event.target.value)}
              className={`${inputClass} ${fieldError('name') !== null ? 'border-red-500' : 'mb-4'}`}
            />
            {fieldError('name') !== null && (
              <p role="alert" className="mb-4 text-sm text-red-600">
                {fieldError('name')}
              </p>
            )}
          </>
        )}
        <label
          htmlFor={isRegister ? 'register-email' : 'login-email'}
          className="mb-1 block text-sm font-medium text-gray-700"
        >
          E-Mail
        </label>
        <input
          id={isRegister ? 'register-email' : 'login-email'}
          type="email"
          autoComplete="email"
          required
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          className={`${inputClass} ${fieldError('email') !== null ? 'border-red-500' : 'mb-4'}`}
        />
        {fieldError('email') !== null && (
          <p role="alert" className="mb-4 text-sm text-red-600">
            {fieldError('email')}
          </p>
        )}
        <label
          htmlFor={isRegister ? 'register-password' : 'login-password'}
          className="mb-1 block text-sm font-medium text-gray-700"
        >
          Passwort
        </label>
        <input
          id={isRegister ? 'register-password' : 'login-password'}
          type="password"
          autoComplete={isRegister ? 'new-password' : 'current-password'}
          required
          minLength={isRegister ? 8 : undefined}
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          className={`${inputClass} ${fieldError('password') !== null ? 'border-red-500' : 'mb-4'}`}
        />
        {fieldError('password') !== null && (
          <p role="alert" className="mb-4 text-sm text-red-600">
            {fieldError('password')}
          </p>
        )}
        {isRegister && (
          <>
            <label
              htmlFor="register-password-confirmation"
              className="mb-1 block text-sm font-medium text-gray-700"
            >
              Passwort bestätigen
            </label>
            <input
              id="register-password-confirmation"
              type="password"
              autoComplete="new-password"
              required
              minLength={8}
              value={passwordConfirmation}
              onChange={(event) => setPasswordConfirmation(event.target.value)}
              className={`${inputClass} ${fieldError('password_confirmation') !== null ? 'border-red-500' : 'mb-4'}`}
            />
            {fieldError('password_confirmation') !== null && (
              <p role="alert" className="mb-4 text-sm text-red-600">
                {fieldError('password_confirmation')}
              </p>
            )}
          </>
        )}
        {generalError !== null && (
          <p role="alert" className="mb-4 text-sm text-red-600">
            {generalError}
          </p>
        )}
        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
        >
          {submitting
            ? isRegister
              ? 'Wird registriert …'
              : 'Wird angemeldet …'
            : isRegister
              ? 'Registrieren'
              : 'Anmelden'}
        </button>
        <p className="mt-4 text-center text-sm text-gray-600">
          {isRegister ? (
            <>
              Bereits registriert?{' '}
              <button
                type="button"
                onClick={() => switchMode('login')}
                className="font-medium text-indigo-600 hover:underline"
              >
                Jetzt anmelden
              </button>
            </>
          ) : (
            <>
              Noch kein Konto?{' '}
              <button
                type="button"
                onClick={() => switchMode('register')}
                className="font-medium text-indigo-600 hover:underline"
              >
                Jetzt registrieren
              </button>
            </>
          )}
        </p>
      </form>
    </div>
  )
}
