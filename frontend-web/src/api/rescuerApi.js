import api from './client'

export async function getRescuers(params = {}) {
  const response = await api.get('/rescuers', { params })
  return response.data.data
}

export async function getRescuer(responderId) {
  const response = await api.get(`/rescuers/${responderId}`)
  return response.data.data
}

export async function createRescuer(payload) {
  const response = await api.post('/rescuers', payload)
  return response.data.data
}

export async function updateRescuer(responderId, payload) {
  const response = await api.patch(`/rescuers/${responderId}`, payload)
  return response.data.data
}

export async function deactivateRescuer(responderId) {
  const response = await api.post(`/rescuers/${responderId}/deactivate`)
  return response.data.data
}

export async function getRescueTeamConfig() {
  const response = await api.get('/rescuers/team-config')
  return response.data.data
}

export async function createRescueTeam(payload) {
  const response = await api.post('/rescuers/team-config', payload)
  return response.data.data
}

export async function updateRescueTeam(teamId, payload) {
  const response = await api.patch(`/rescuers/team-config/${teamId}`, payload)
  return response.data.data
}

export async function deleteRescueTeam(teamId) {
  const response = await api.delete(`/rescuers/team-config/${teamId}`)
  return response.data.data
}
