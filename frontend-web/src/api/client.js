import axios from 'axios'
import { getToken } from './token'

function getApiBaseUrl() {
  const configuredUrl = String(import.meta.env.VITE_API_BASE_URL || '').trim()

  if (configuredUrl) {
    return configuredUrl
  }

  const browserHost = window.location.hostname
  const openedFromNetwork = browserHost !== 'localhost' && browserHost !== '127.0.0.1'

  if (openedFromNetwork) {
    return `${window.location.protocol}//${browserHost}:8000/api/v1`
  }

  return 'http://127.0.0.1:8000/api/v1'
}

const api = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: Number(import.meta.env.VITE_API_TIMEOUT_MS || 30000),
  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = getToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status

    if (status === 401) {
      error.friendlyMessage = 'Authentication failed. Please log in again.'
    } else if (status === 403) {
      error.friendlyMessage = 'Access denied. You need admin access to view the dashboard.'
    } else if (status === 404) {
      error.friendlyMessage = 'The requested API endpoint was not found on the backend.'
    } else if (status === 503) {
      error.friendlyMessage = error.response?.data?.message || 'The database is not available right now. Make sure Tailscale is connected and the shared MySQL host is online.'
    } else if (error.code === 'ECONNABORTED' || String(error.message || '').toLowerCase().includes('timeout')) {
      error.friendlyMessage = 'The server took too long to respond. Make sure Laravel is running, Tailscale is connected, and the shared database is online, then try again.'
    } else if (!error.response) {
      error.friendlyMessage = 'Cannot reach the backend API. Start Laravel with `php artisan serve --host=0.0.0.0 --port=8000` and confirm the frontend is pointing to the correct API URL.'
    }

    return Promise.reject(error)
  },
)

export default api
