import api from './client'

export async function getWeatherWorkspace() {
  const response = await api.get('/weather')
  return response.data.data
}

export async function refreshWeather() {
  const response = await api.post('/weather/refresh')
  return response.data.data
}

export async function getWeatherLogs(eventId) {
  const response = await api.get(`/disaster-events/${eventId}/weather-logs`)
  return response.data.data
}

export async function refreshEventWeather(eventId) {
  const response = await api.post(`/disaster-events/${eventId}/weather-logs/refresh`)
  return response.data.data
}
