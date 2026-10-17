import api from './client'

export async function getEvaTrackReportConfiguration() {
  const { data } = await api.get('/reports/evatrack/configuration')
  return data.data
}

export async function generateEvaTrackReport(payload) {
  const { data } = await api.post('/reports/evatrack/generate', payload, { timeout: 90000 })
  return data
}

export async function generateSafeTrackReport(payload) {
  const { data } = await api.post('/reports/safetrack/generate', payload, { timeout: 90000 })
  return data
}

export async function generateTrackingAidReport(payload) {
  const { data } = await api.post('/reports/trackingaid/generate', payload, { timeout: 90000 })
  return data
}

export async function getExternalReportProviders() {
  const { data } = await api.get('/reports/external/providers')
  return data.data.providers
}

export async function getReportRequestInbox(params = {}) {
  const { data } = await api.get('/reports/external/requests', { params })
  return data.data
}

export async function decideReportRequest(id, payload) {
  const { data } = await api.post(`/reports/external/requests/${id}/decision`, payload, { timeout: 90000 })
  return data.data
}

export async function refreshReportRequestFile(id) {
  const { data } = await api.post(`/reports/external/requests/${id}/file`, {}, { timeout: 90000 })
  return data.data
}
