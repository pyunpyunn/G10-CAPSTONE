import api from './client'

export async function getDashboard() {
  const response = await api.get('/dashboard')
  return response.data.data
}

export async function closeActiveEvent() {
  const response = await api.post('/dashboard/active-event/close')
  return response.data.data
}
