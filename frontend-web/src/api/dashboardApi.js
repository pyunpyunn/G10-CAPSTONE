import api from './client'

export async function getDashboard() {
  const response = await api.get('/dashboard')
  return response.data.data
}

export async function getDashboardSummary() {
  const response = await api.get('/dashboard/summary')
  return response.data.data
}

export async function getDashboardDispatch() {
  const response = await api.get('/dashboard/dispatch')
  return response.data.data
}

export async function getDashboardWeather() {
  const response = await api.get('/dashboard/weather')
  return response.data.data
}

export async function getDashboardRequests() {
  const response = await api.get('/dashboard/requests')
  return response.data.data
}

export async function getDashboardActivity() {
  const response = await api.get('/dashboard/activity')
  return response.data.data
}

export async function closeActiveEvent() {
  const response = await api.post('/dashboard/active-event/close')
  return response.data.data
}
