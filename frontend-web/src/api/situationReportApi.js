import api from './client'

export async function getSituationWorkspace() {
  const response = await api.get('/situation-reports')
  return response.data.data
}

export async function getSituationSummary(eventId) {
  const response = await api.get(`/disaster-events/${eventId}/situation-summary`)
  return response.data.data
}

export async function createSituationReport(payload) {
  const response = await api.post('/situation-reports', payload)
  return response.data.data
}

export async function getSituationReport(sitRepId) {
  const response = await api.get(`/situation-reports/${sitRepId}`)
  return response.data.data
}

export async function requestSituationPdf(sitRepId) {
  const response = await api.get(`/situation-reports/${sitRepId}/pdf`)
  return response.data
}
