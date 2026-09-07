import api from './client'

export async function getHouseholds(params = {}) {
  const response = await api.get('/households', { params })
  return response.data.data
}

export async function getHousehold(householdId) {
  const response = await api.get(`/households/${householdId}`)
  return response.data.data
}

export async function getHouseholdStatusLogs(householdId) {
  const response = await api.get(`/households/${householdId}/status-logs`)
  return response.data.data
}
