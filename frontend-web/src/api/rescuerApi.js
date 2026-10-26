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
  return { ...response.data.data, message: response.data.message }
}

export async function updateRescuer(responderId, payload) {
  const response = await api.patch(`/rescuers/${responderId}`, payload)
  return { ...response.data.data, message: response.data.message }
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

export async function deleteRescuer(responderId) {
  const response = await api.delete(`/rescuers/${responderId}`)
  return response.data.data
}

export async function getPersonnelRoles() { return (await api.get('/rescuers/roles')).data.data }
export async function savePersonnelRole(payload) { return (await (payload.role_id ? api.patch(`/rescuers/roles/${payload.role_id}`,payload) : api.post('/rescuers/roles',payload))).data.data }
export async function deletePersonnelRole(roleId,replacementRoleId) { return (await api.delete(`/rescuers/roles/${roleId}`,{data:{replacement_role_id:replacementRoleId || null}})).data.data }
