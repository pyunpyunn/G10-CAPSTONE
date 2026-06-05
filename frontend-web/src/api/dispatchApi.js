import api from './client'

export async function getDispatchDashboard(params = {}) {
  const response = await api.get('/dispatches', { params })
  return response.data.data
}

export async function getRescueTeams() {
  const response = await api.get('/rescue-teams')
  return response.data.data
}

export async function createDispatch(payload) {
  const response = await api.post('/dispatches', payload)
  return response.data.data
}

export async function updateDispatch(assignmentId, payload) {
  const response = await api.patch(`/dispatches/${assignmentId}`, payload)
  return response.data.data
}

export async function completeDispatch(assignmentId, payload) {
  const response = await api.post(`/dispatches/${assignmentId}/complete`, payload)
  return response.data.data
}
