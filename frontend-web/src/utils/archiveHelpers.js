export const ARCHIVE_TABS = [
  { key: 'disaster-events', label: 'Disaster Event' },
  { key: 'household-status-logs', label: 'Household Status Logs' },
  { key: 'dispatch-logs', label: 'Rescue Dispatch Logs' },
  { key: 'resource-requests', label: 'Resources & Requests' },
  { key: 'situation-reports', label: 'Situation Reporting' },
]

export const ARCHIVE_TABLE_COPY = {
  'disaster-events': {
    title: 'Disaster event archive',
    subtitle: 'Broadcast logs, weather summary, disaster type, and closure status',
  },
  'household-status-logs': {
    title: 'Household status logs',
    subtitle: 'Geotagged status reports by date, household, and source',
  },
  'dispatch-logs': {
    title: 'Rescue dispatch status logs',
    subtitle: 'Team routes, status changes, areas covered, and outcomes',
  },
  'resource-requests': {
    title: 'Resources and requests archive',
    subtitle: 'Validated requests, handoff status, and release records',
  },
  'situation-reports': {
    title: 'Situation reporting archive',
    subtitle: 'Official SitRep snapshots linked to disaster event logs',
  },
}

export const ARCHIVE_COLUMNS = {
  'disaster-events': [
    { key: 'event', label: 'Event' },
    { key: 'disaster', label: 'Disaster / weather' },
    { key: 'period', label: 'Date declared / finished' },
    { key: 'broadcasts', label: 'Broadcast logs' },
    { key: 'scope', label: 'Household scope' },
    { key: 'status', label: 'Status' },
  ],
  'household-status-logs': [
    { key: 'datetime', label: 'Date / time' },
    { key: 'event', label: 'Event' },
    { key: 'household', label: 'Household / geotag' },
    { key: 'purok', label: 'Purok' },
    { key: 'status_change', label: 'Status change' },
    { key: 'source', label: 'Source / remarks' },
  ],
  'dispatch-logs': [
    { key: 'datetime', label: 'Date / time' },
    { key: 'event', label: 'Event' },
    { key: 'team_route', label: 'Team / route' },
    { key: 'purok', label: 'Purok' },
    { key: 'status', label: 'Status' },
    { key: 'outcome', label: 'Outcome entry' },
  ],
  'resource-requests': [
    { key: 'datetime', label: 'Date / time' },
    { key: 'event', label: 'Event' },
    { key: 'request', label: 'Request' },
    { key: 'purok_site', label: 'Purok / site' },
    { key: 'validation', label: 'Validation' },
    { key: 'handoff', label: 'Release / handoff' },
  ],
  'situation-reports': [
    { key: 'sitrep', label: 'SitRep No.' },
    { key: 'event', label: 'Event / disaster type' },
    { key: 'period', label: 'Reporting period' },
    { key: 'submitted_by', label: 'Submitted by' },
    { key: 'population', label: 'Population / casualties' },
    { key: 'status', label: 'Status' },
  ],
}

export function archiveParams({ search, purok, eventId, status }) {
  return {
    search: search.trim(),
    purok,
    event_id: eventId,
    status,
    per_page: 25,
  }
}

export function archiveErrorMessage(error, fallback = 'Archive records cannot be loaded right now.') {
  const message = error?.response?.data?.message

  return message || fallback
}

export function archiveFileName(category, type = 'csv') {
  const date = new Date().toISOString().slice(0, 10)

  return `resqperation-${category}-archive-${date}.${type}`
}

export function downloadBlob(fileName, blob) {
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = fileName
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

export function downloadRecordCsv(record) {
  const details = record?.details || []
  const rows = [['Field', 'Value'], ...details.map((item) => [item.label, item.value])]
  const csv = rows.map((row) => row.map(csvValue).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' })
  const fileName = `resqperation-archive-record-${record?.id || 'details'}.csv`

  downloadBlob(fileName, blob)
}

function csvValue(value) {
  const text = String(value ?? '')

  return `"${text.replace(/"/g, '""')}"`
}
