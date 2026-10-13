import type { App } from 'vue'
import { $api } from '@/utils/api'

const STORAGE_KEY = 'auth:lastActivityAt'
const ACTIVITY_EVENTS = ['mousedown', 'keydown', 'scroll', 'touchstart', 'click', 'mousemove'] as const

function idleTimeoutMinutes(): number {
  const raw = Number(import.meta.env.VITE_AUTH_IDLE_TIMEOUT_MINUTES)

  return Number.isFinite(raw) && raw >= 0 ? raw : 30
}

function isLoggedIn(): boolean {
  return !!(useCookie('accessToken').value && useCookie('userData').value)
}

async function forceLogoutForIdle(): Promise<void> {
  if (!isLoggedIn())
    return

  try {
    await $api('/auth/logout', { method: 'POST' })
  }
  catch {
    // Token déjà invalide : on nettoie quand même
  }

  useCookie('accessToken').value = null
  useCookie('userData').value = null
  useCookie('userAbilityRules').value = null
  localStorage.removeItem(STORAGE_KEY)

  if (typeof window !== 'undefined' && !window.location.pathname.includes('/login'))
    window.location.assign('/login?reason=idle')
}

/**
 * Déconnexion automatique après inactivité (souris, clavier, etc.).
 * Synchronisé entre onglets via localStorage.
 */
export default function (_app: App) {
  if (typeof window === 'undefined')
    return

  const minutes = idleTimeoutMinutes()
  if (minutes <= 0)
    return

  const idleMs = minutes * 60 * 1000
  let timer: ReturnType<typeof setTimeout> | null = null
  let lastTouch = 0
  let loggingOut = false

  const schedule = () => {
    if (timer)
      clearTimeout(timer)

    if (!isLoggedIn())
      return

    timer = setTimeout(async () => {
      if (loggingOut)
        return
      loggingOut = true
      await forceLogoutForIdle()
    }, idleMs)
  }

  const touch = () => {
    if (!isLoggedIn())
      return

    const now = Date.now()
    // Throttle mousemove / scroll
    if (now - lastTouch < 15_000) {
      schedule()

      return
    }

    lastTouch = now
    localStorage.setItem(STORAGE_KEY, String(now))
    schedule()
  }

  const checkExisting = () => {
    if (!isLoggedIn())
      return

    const raw = Number(localStorage.getItem(STORAGE_KEY) || 0)
    if (raw > 0 && Date.now() - raw > idleMs) {
      void forceLogoutForIdle()

      return
    }

    if (!raw)
      localStorage.setItem(STORAGE_KEY, String(Date.now()))

    schedule()
  }

  ACTIVITY_EVENTS.forEach(event => {
    window.addEventListener(event, touch, { passive: true })
  })

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible')
      checkExisting()
  })

  window.addEventListener('storage', event => {
    if (event.key === STORAGE_KEY)
      schedule()
  })

  checkExisting()
}
