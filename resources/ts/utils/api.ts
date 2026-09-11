import { ofetch } from 'ofetch'

const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS', 'TRACE'])

function readCookie(name: string): string | null {
  if (typeof document === 'undefined')
    return null

  const match = document.cookie
    .split('; ')
    .find(row => row.startsWith(`${name}=`))

  return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : null
}

function setHeader(headers: HeadersInit | undefined, key: string, value: string): Headers {
  const next = new Headers(headers as HeadersInit)

  next.set(key, value)

  return next
}

let csrfPromise: Promise<void> | null = null

async function ensureCsrfCookie(): Promise<void> {
  if (readCookie('XSRF-TOKEN'))
    return

  csrfPromise ??= ofetch('/sanctum/csrf-cookie', {
    method: 'GET',
    credentials: 'include',
  }).then(() => undefined).finally(() => {
    csrfPromise = null
  })

  await csrfPromise
}

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  credentials: 'include',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  async onRequest({ options }) {
    const method = String(options.method || 'GET').toUpperCase()

    if (!SAFE_METHODS.has(method)) {
      await ensureCsrfCookie()

      const xsrfToken = readCookie('XSRF-TOKEN')
      if (xsrfToken)
        options.headers = setHeader(options.headers as HeadersInit, 'X-XSRF-TOKEN', xsrfToken)
    }

    const accessToken = useCookie('accessToken').value
    if (accessToken)
      options.headers = setHeader(options.headers as HeadersInit, 'Authorization', `Bearer ${accessToken}`)

    // Laisser le navigateur poser le boundary multipart (sinon le fichier est ignoré).
    if (options.body instanceof FormData) {
      const headers = new Headers(options.headers as HeadersInit)

      headers.delete('Content-Type')
      options.headers = headers
    }
  },
  onResponseError({ response }) {
    if (response.status === 403 && response._data?.code === 'MUST_CHANGE_PASSWORD') {
      const userData = useCookie<Record<string, unknown> | null>('userData')
      if (userData.value)
        userData.value = { ...userData.value, mustChangePassword: true }

      if (typeof window !== 'undefined' && !window.location.pathname.includes('/change-password'))
        window.location.assign('/change-password')
    }
  },
})
