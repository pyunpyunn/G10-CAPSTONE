import { resetApiState } from '../state/apiStore.js'
const tokenKey = 'resqperation_web_token'
const sessionTimestampKey = 'resqperation_session_at'

export function getToken() {
  return localStorage.getItem(tokenKey)
}

export function saveToken(token) {
  resetApiState()
  localStorage.setItem(tokenKey, token)
  localStorage.setItem(sessionTimestampKey, String(Date.now()))
}

export function clearToken() {
  resetApiState()
  localStorage.removeItem(tokenKey)
  localStorage.removeItem(sessionTimestampKey)
}

export function hasValidSession() {
  return Boolean(getToken())
}

// Multi-tab session synchronization: log out across tabs when cleared in one tab
if (typeof window !== 'undefined') {
  window.addEventListener('storage', (event) => {
    if (event.key === tokenKey && !event.newValue) {
      resetApiState()
      window.dispatchEvent(new Event('auth:logged-out'))
      if (!window.location.pathname.startsWith('/login')) {
        window.location.href = '/login'
      }
    }
  })
}
