import api from './client'

export async function getResourceRequests(params = {}) {
  const response = await api.get('/resource-requests', { params })
  return response.data.data
}

export async function getResourceRequest(requestId) {
  const response = await api.get(`/resource-requests/${requestId}`)
  return response.data.data
}

export async function createResourceRequest(payload) {
  const response = await api.post('/resource-requests', payload)
  return response.data.data
}

export async function validateResourceRequest(requestId, payload) {
  const response = await api.post(`/resource-requests/${requestId}/validate`, payload)
  return response.data.data
}

export async function forwardResourceRequest(requestId, payload = {}) {
  const response = await api.post(`/resource-requests/${requestId}/forward`, payload)
  return response.data.data
}

export async function returnResourceRequest(requestId, payload) {
  const response = await api.post(`/resource-requests/${requestId}/return`, payload)
  return response.data.data
}
