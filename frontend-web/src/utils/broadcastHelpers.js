export const defaultStatusKeys = ['safe', 'evacuated', 'need_help', 'unsafe']

export const priorityOptions = [
  { value: 'critical', label: 'Critical' },
  { value: 'high', label: 'High' },
  { value: 'watch', label: 'Watch' },
  { value: 'monitor', label: 'Monitor' },
]

export const durationOptions = [
  { value: '2 hours', label: '2 hours' },
  { value: '6 hours', label: '6 hours' },
  { value: '12 hours', label: '12 hours' },
  { value: '24 hours', label: '24 hours' },
  { value: 'Until further notice', label: 'Until further notice' },
]

export function defaultForm(workspace = {}) {
  const now = new Date()
  const date = now.toISOString().slice(0, 10)
  const time = now.toTimeString().slice(0, 5)
  const disasterTypes = workspace.disaster_types || []
  const severityLevels = workspace.severity_levels || []

  return {
    event_name: '',
    type_id: disasterTypes[0]?.type_id || '',
    severity_id: preferredSeverityId(severityLevels),
    started_date: date,
    started_time: time,
    estimated_duration: 'Until further notice',
    scope_type: 'barangay_wide',
    base_priority: 'high',
    broadcast_title: 'Official disaster advisory',
    message: 'Stay alert and follow official barangay instructions. Use the household mobile status buttons to report your current situation.',
    attach_route: false,
  }
}

export function getRecipientNote(typeName, scopeType, directPuroks) {
  const normalizedType = (typeName || '').toLowerCase()

  if (scopeType === 'rescuers_only') {
    return 'Responder mobile users only.'
  }

  if (scopeType === 'selected_puroks' || scopeType === 'local_direct_impact') {
    const areaLabel = directPuroks.length > 0 ? directPuroks.map((purok) => purok.name).join(', ') : 'selected puroks'
    return `Household mobile users in ${areaLabel}, plus responder mobile users.`
  }

  if (normalizedType.includes('fire') || normalizedType.includes('landslide')) {
    return 'Localized incident: use direct-impact puroks when only nearby households should receive the alert.'
  }

  return 'Barangay-wide: all household mobile users and responder mobile users.'
}

export function targetAreaLabel(scopeType, directPuroks) {
  if (scopeType === 'rescuers_only') {
    return 'Responders only'
  }

  if (['selected_puroks', 'local_direct_impact'].includes(scopeType)) {
    return directPuroks.map((purok) => purok.name).join(', ')
  }

  return 'Barangay-wide'
}

export function labelFromValue(options, value) {
  return options.find((option) => option.value === value)?.label || value
}

export function eventTone(severityKey) {
  if (['critical', 'high'].includes(severityKey)) {
    return 'red'
  }

  if (severityKey === 'medium' || severityKey === 'watch') {
    return 'amber'
  }

  return 'green'
}

export function priorityTone(priority) {
  if (['critical', 'high'].includes(priority)) {
    return 'red'
  }

  if (priority === 'watch') {
    return 'amber'
  }

  return 'gray'
}

export function apiErrorMessage(error, fallback) {
  if (!error?.response) {
    return 'Cannot connect to the server right now. Please check the backend connection.'
  }

  const data = error.response.data

  if (data?.errors) {
    const firstError = Object.values(data.errors)[0]
    return Array.isArray(firstError) ? firstError[0] : fallback
  }

  return data?.message || fallback
}

function preferredSeverityId(severityLevels) {
  return severityLevels.find((severity) => severity.severity_key === 'high')?.severity_id
    || severityLevels[0]?.severity_id
    || ''
}
