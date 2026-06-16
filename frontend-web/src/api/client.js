import axios from 'axios'
import { getToken } from './token'

function getApiBaseUrl() {
  const configuredUrl = import.meta.env.VITE_API_BASE_URL
  const browserHost = window.location.hostname
  const openedFromNetwork = browserHost !== 'localhost' && browserHost !== '127.0.0.1'
  const configuredIsLocal = configuredUrl?.includes('127.0.0.1') || configuredUrl?.includes('localhost')

  if (openedFromNetwork && (!configuredUrl || configuredIsLocal)) {
    return `${window.location.protocol}//${browserHost}:8000/api/v1`
  }

  return configuredUrl || 'http://127.0.0.1:8000/api/v1'
}

const api = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: Number(import.meta.env.VITE_API_TIMEOUT_MS || 10000),
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
    if (error.code === 'ECONNABORTED' || String(error.message || '').toLowerCase().includes('timeout')) {
      error.friendlyMessage = 'The server took too long to respond. Please check if Laravel and the shared database are running, then try again.'
    }

    return Promise.reject(error)
  },
)

export default api
