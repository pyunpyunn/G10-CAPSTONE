import { downloadBackendReport } from './reportApi'
import api from './client'

export async function getSituationWorkspace() {
  const response = await api.get('/situation-reports')
  return response.data.data
}

export async function getSituationSitios() {
  const { data } = await api.get('/reports/situation/sitios')
  return data.data.sitios
}

export async function getSituationSummary(eventId, sitioId = null) {
  const response = await api.get(`/disaster-events/${eventId}/situation-summary`, { params: sitioId && sitioId !== 'all' ? { sitio_id: sitioId } : undefined })
  return response.data.data
}

export async function createSituationReport(payload) {
  const response = await api.post('/situation-reports', payload, {timeout:240000})
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

export async function exportSituationReport(payload) {
  return downloadBackendReport({ ...payload, report_type: 'situation' })
}

export async function recordSituationSubmission(sitRepId, payload) {
  const response = await api.post(`/situation-reports/${sitRepId}/submissions`, payload)
  return response.data.data
}

export async function syncSituationReports(eventId) {
  const {data} = await api.post('/situation-reports/sync',{event_id:eventId},{timeout:240000})
  return data.data
}
