import api from './client'

const archiveEndpoints = {
  'disaster-events': '/archive/disaster-events',
  'household-status-logs': '/archive/household-status-logs',
  'dispatch-logs': '/archive/dispatch-logs',
  'radio-communication-logs': '/archive/radio-communication-logs',
  'resource-requests': '/archive/resource-requests',
  'situation-reports': '/archive/situation-reports',
}

export async function getArchiveRecords(category, params = {}) {
  const endpoint = archiveEndpoints[category] || archiveEndpoints['disaster-events']
  const response = await api.get(endpoint, { params })

  return response.data.data
}

export async function getDisasterEventArchives(params = {}) {
  return getArchiveRecords('disaster-events', params)
}

export async function getHouseholdStatusLogs(params = {}) {
  return getArchiveRecords('household-status-logs', params)
}

export async function getDispatchLogs(params = {}) {
  return getArchiveRecords('dispatch-logs', params)
}

export async function getResourceRequestArchives(params = {}) {
  return getArchiveRecords('resource-requests', params)
}

export async function getSituationReportArchives(params = {}) {
  return getArchiveRecords('situation-reports', params)
}

export async function exportArchive(category, type, params = {}) {
  const response = await api.get('/archive/export', {
    params: {
      ...params,
      category,
      type,
    },
    responseType: 'blob',
  })

  return response.data
}

export async function deleteArchiveRecords(category, ids = []) {
  const response = await api.post('/archive/delete-selected', {
    category,
    ids,
  })

  return response.data.data
}

export async function getSavedArchiveGroups() {
  const response = await api.get('/archive/saved-groups')

  return response.data.data
}

export async function createSavedArchiveGroup(category, records = []) {
  const response = await api.post('/archive/saved-groups', {
    category,
    records,
  })

  return response.data.data
}

export async function deleteSavedArchiveGroup(groupId) {
  const response = await api.delete(`/archive/saved-groups/${groupId}`)

  return response.data.data
}

export async function deleteSavedArchiveGroupRecord(groupId, recordId) {
  const response = await api.delete(`/archive/saved-groups/${groupId}/records/${recordId}`)

  return response.data.data
}
