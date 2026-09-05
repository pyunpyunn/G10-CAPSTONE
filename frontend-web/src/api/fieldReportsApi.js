import api from './client'

export async function getFieldReports(params = {}) {
  const response = await api.get('/field-reports', { params })
  return response.data.data
}
export async function verifyEvacuationQr(payload) {
  const response = await api.post('/evacuation/verify-qr', payload)
  return response.data.data
}

export async function verifyEvacuationCheckIn(payload) {
  const response = await api.post('/evacuation/check-in', payload)
  return response.data.data
}
