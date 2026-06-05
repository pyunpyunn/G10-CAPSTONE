import api from './client'

export async function getBroadcastWorkspace() {
  const response = await api.get('/disaster-events')
  return response.data.data
}

export async function createDisasterEvent(payload) {
  const response = await api.post('/disaster-events', payload)
  return response.data.data
}

export async function createBroadcast(eventId, payload) {
  const response = await api.post(`/disaster-events/${eventId}/broadcasts`, payload)
  return response.data.data
}

export async function getBroadcasts(eventId) {
  const response = await api.get(`/disaster-events/${eventId}/broadcasts`)
  return response.data.data
}
