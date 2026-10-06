import api from './client'

export async function getDispatchDashboard(params = {}) {
  const response = await api.get('/dispatches', { params })
  return response.data.data
}

export async function getRescueTeams() {
  const response = await api.get('/rescue-teams')
  return response.data.data
}

export async function getWelfareChecks(params = {}) {
  const response = await api.get('/dispatches/welfare-checks', { params })
  return response.data
}

export async function getRescuePriorities(params = {}) {
  const response = await api.get('/dispatches/priorities', { params })
  return response.data
}

export async function getPurokPriorities() {
  const response = await api.get('/dispatches/purok-priorities')
  return response.data.data
}

export async function getSitioPriorities() {
  const response = await api.get('/dispatches/sitio-priorities')
  return response.data.data
}

export async function getRescueCriteriaTimeline() {
  const response = await api.get('/dispatches/criteria-timeline')
  return response.data.data
}

export async function getMemberCheckQueue(params = {}) {
  const response = await api.get('/dispatches/member-check-queue', { params })
  return response.data
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
