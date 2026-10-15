import axios from 'axios'
import { useState, type FormEvent } from 'react'
import { useAuth } from '../context/useAuth'

export default function Login() {
  const { login } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSubmitting(true)
    try {
      await login(email, password)
    } catch (unknownError: unknown) {
      if (
        axios.isAxiosError(unknownError) &&
        unknownError.response?.status === 422
      ) {
        setError('E-Mail oder Passwort ist falsch.')
      } else {
        setError('Anmeldung fehlgeschlagen. Bitte versuche es erneut.')
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
          Anmelden
        </h1>
        <label
          htmlFor="login-email"
          className="mb-1 block text-sm font-medium text-gray-700"
        >
          E-Mail
        </label>
        <input
          id="login-email"
          type="email"
          autoComplete="email"
          required
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          className="mb-4 w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none"
        />
        <label
          htmlFor="login-password"
          className="mb-1 block text-sm font-medium text-gray-700"
        >
          Passwort
        </label>
        <input
          id="login-password"
          type="password"
          autoComplete="current-password"
          required
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          className="mb-4 w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-indigo-500 focus:outline-none"
        />
        {error !== null && (
          <p role="alert" className="mb-4 text-sm text-red-600">
            {error}
          </p>
        )}
        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
        >
          {submitting ? 'Wird angemeldet …' : 'Anmelden'}
        </button>
      </form>
    </div>
  )
}
