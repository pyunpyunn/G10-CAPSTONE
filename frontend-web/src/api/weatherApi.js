import api from './client'

const WEATHER_REQUEST_TIMEOUT_MS = 12000

export async function getWeatherWorkspace() {
  const response = await api.get('/weather', { timeout: WEATHER_REQUEST_TIMEOUT_MS })
  return response.data.data
}

export async function refreshWeather() {
  const response = await api.post('/weather/refresh', null, { timeout: 20000 })
  return response.data.data
}

export async function getWeatherLogs(eventId) {
  const response = await api.get(`/disaster-events/${eventId}/weather-logs`)
  return response.data.data
}

export async function refreshEventWeather(eventId) {
  const response = await api.post(`/disaster-events/${eventId}/weather-logs/refresh`, null, { timeout: 20000 })
  return response.data.data
}
