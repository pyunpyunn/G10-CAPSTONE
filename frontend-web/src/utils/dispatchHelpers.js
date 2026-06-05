export const teamFilters = [
  { key: 'all', label: 'All' },
  { key: 'on_scene', label: 'On-scene' },
  { key: 'dispatched', label: 'Dispatched' },
  { key: 'standby', label: 'Stand-by' },
]

export const dispatchStatuses = [
  { value: 'standby', label: 'Stand-by' },
  { value: 'dispatched', label: 'Dispatched (en route)' },
  { value: 'en_route', label: 'En route' },
  { value: 'on_scene', label: 'On-scene (working)' },
  { value: 'returning', label: 'Returning to base' },
  { value: 'completed', label: 'Completed' },
]

export const priorityOptions = [
  { value: 'critical', label: 'Critical' },
  { value: 'high', label: 'High' },
  { value: 'watch', label: 'Watch' },
  { value: 'monitor', label: 'Monitor' },
]

export function buildRequestBody(option, form) {
  const body = {
    assigned_area: form.assigned_area,
    households_to_cover: Number(form.households_to_cover) || 0,
    responder_count: Number(form.responder_count) || 1,
    priority_level: form.priority_level,
    status: form.status,
    dispatch_notes: form.dispatch_notes,
    route_notes: form.route_notes,
    safe_count: Number(form.safe_count) || 0,
    evacuated_count: Number(form.evacuated_count) || 0,
    unsafe_count: Number(form.unsafe_count) || 0,
    injured_count: Number(form.injured_count) || 0,
    missing_count: Number(form.missing_count) || 0,
    pending_count: Number(form.pending_count) || 0,
    outcome_notes: form.outcome_notes,
  }

  const [type, id] = option.split(':')

  if (type === 'team') {
    body.team_id = Number(id)
  }

  if (type === 'responder') {
    body.responder_id = Number(id)
  }

  return body
}

export function setFormValue(setForm, key, value) {
  setForm((current) => ({ ...current, [key]: value }))
}

export function setFormNumber(setForm, key, value) {
  setForm((current) => ({ ...current, [key]: Number(value) || 0 }))
}

export function defaultForm() {
  return {
    assigned_area: '',
    households_to_cover: 0,
    responder_count: 1,
    priority_level: 'high',
    status: 'dispatched',
    dispatch_notes: '',
    route_notes: '',
    safe_count: 0,
    evacuated_count: 0,
    unsafe_count: 0,
    injured_count: 0,
    missing_count: 0,
    pending_count: 0,
    outcome_notes: '',
  }
}

export function firstAssignmentOption(teams, responders) {
  const team = teams.find((item) => item.team_id)

  if (team) {
    return `team:${team.team_id}`
  }

  const responder = responders[0]

  return responder ? `responder:${responder.responder_id}` : ''
}

export function emptySummary() {
  return {
    total_teams: 0,
    dispatched: 0,
    on_scene: 0,
    standby: 0,
    completed: 0,
    response_rate: 0,
    active_units: 0,
  }
}

export function initials(value) {
  return String(value || 'DP')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()
}

export function label(value) {
  return String(value || '')
    .replaceAll('_', ' ')
    .replaceAll('-', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

export function getSaveMessage(error) {
  const data = error?.response?.data

  if (data?.errors) {
    const firstError = Object.values(data.errors)[0]
    return Array.isArray(firstError) ? firstError[0] : 'Please check the dispatch form.'
  }

  return data?.message || 'Dispatch could not be saved. Please check the form and try again.'
}
