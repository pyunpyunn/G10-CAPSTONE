import axios from 'axios'
import { getToken } from './token.js'
import { assertApiDestination } from './requestSecurity.js'
import { installReduxTransport, invalidateApiState, invalidateTransportCache, resetApiState } from '../state/apiStore.js'

function getApiBaseUrl() {
  const configuredUrl = import.meta.env?.VITE_API_BASE_URL
  const browserHost = window.location.hostname
  const isLocalBrowser = ['localhost', '127.0.0.1', '0.0.0.0'].includes(browserHost)

  if (configuredUrl) {
    try {
      const parsedUrl = new URL(configuredUrl)

      if (!isLocalBrowser && ['localhost', '127.0.0.1', '0.0.0.0'].includes(parsedUrl.hostname)) {
        // A LAN browser cannot reach the developer machine's loopback-only
        // Laravel listener. Vite proxies this same-origin path during development.
        return parsedUrl.pathname.replace(/\/$/, '')
      }

      return configuredUrl
    } catch {
      return configuredUrl
    }
  }

  if (!isLocalBrowser) {
    return '/api/v1'
  }

  return 'http://127.0.0.1:8000/api/v1'
}

const api = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: Number(import.meta.env?.VITE_API_TIMEOUT_MS || 10000),
  headers: {
    Accept: 'application/json',
  },
})

installReduxTransport(api)
window.addEventListener('operations:changed', event => {
  const changes = event.detail?.topics
  const paths = {
    mapping: ['/map/', '/dashboard'],
    dispatch: ['/dispatch', '/rescue-teams', '/field-reports', '/dashboard', '/notifications'],
    households: ['/household', '/dispatch', '/field-reports', '/dashboard', '/map/', '/situation'],
    'field-reports': ['/field-reports', '/household', '/dispatch', '/situation'],
    requests: ['/resource-request', '/notifications', '/dashboard', '/situation'],
    communications: ['/communications', '/radio'], weather: ['/weather', '/dashboard'],
    inquiries: ['/inquiries', '/archive'],
  }
  invalidateApiState(changes?.length && changes.every(topic => paths[topic]) ? changes.flatMap(topic => paths[topic]) : undefined)
})
window.addEventListener('online', () => invalidateTransportCache())
window.addEventListener('focus', () => invalidateTransportCache())
window.addEventListener('personnel-access:changed', () => invalidateApiState(['/auth/', '/profile', '/rescuers']))

api.interceptors.request.use((config) => {
  assertApiDestination(api.getUri(config), api.defaults.baseURL, window.location.href, import.meta.env.PROD)
  const token = getToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => {
    const method = String(response.config?.method || 'get').toLowerCase()
    const path = response.config?.url || ''
    if (['post', 'patch', 'put', 'delete'].includes(method)
      && (/^\/(dispatches|resource-requests)(\/|$)/.test(path)
        || /^\/disaster-events\/[^/]+\/broadcasts?$/.test(path))) {
      window.dispatchEvent(new Event('notifications:changed'))
    }
    if (['post', 'patch', 'put', 'delete'].includes(method) && /^\/(rescuers|headquarters-accounts)(\/|$)/.test(path)) {
      window.dispatchEvent(new Event('personnel-access:changed'))
    }
    if (['post', 'patch', 'put', 'delete'].includes(method)
      && (/^\/(households|household|dispatches|broadcast|disaster-events|trusted-households|members)(\/|$)/.test(path)
        || path === '/dashboard/active-event/close')) {
      window.dispatchEvent(new Event('rescue-criteria:changed'))
    }
    return response
  },
  (error) => {
    if (error.response?.status === 401) resetApiState()
    if ([401, 403].includes(error.response?.status) && !String(error.config?.url || '').startsWith('/auth/')) {
      window.dispatchEvent(new Event('personnel-access:changed'))
    }
    if (error.code === 'ECONNABORTED' || String(error.message || '').toLowerCase().includes('timeout')) {
      error.friendlyMessage = 'The server took too long to respond. Please check if Laravel and the shared database are running, then try again.'
    }

    return Promise.reject(error)
  },
)

export default api
