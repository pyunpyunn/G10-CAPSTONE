import api from './client'

export async function getEvaTrackReportConfiguration() {
  const { data } = await api.get('/reports/evatrack/configuration')
  return data.data
}

export async function generateEvaTrackReport(payload) {
  const { data } = await api.post('/reports/evatrack/generate', payload, { timeout: 90000 })
  return data
}
