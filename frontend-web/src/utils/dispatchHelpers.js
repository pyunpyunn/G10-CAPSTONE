export const dispatchStatuses = [
  { value: 'dispatched', label: 'Assigned / waiting acceptance' },
  { value: 'accepted', label: 'Accepted' },
  { value: 'en_route', label: 'En route' },
  { value: 'on_scene', label: 'On-scene (working)' },
  { value: 'returning', label: 'Returning to base' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
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
    household_id: form.household_id || null,
    households_to_cover: Number(form.households_to_cover) || 0,
    responder_count: Number(form.responder_count) || 1,
    dispatch_type: form.dispatch_type || 'rescue',
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
    household_id: '',
    households_to_cover: 0,
    responder_count: 1,
    dispatch_type: 'rescue',
    selected_responder_ids: [],
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

export function firstAssignmentOption(teams) {
  const team = teams.find((item) => item.team_id && item.is_available)

  if (team) {
    return `team:${team.team_id}`
  }

  return ''
}

export function emptySummary() {
  return {
    total_teams: 0,
    dispatched: 0,
    on_scene: 0,
    available: 0,
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

export function buildDispatchAreaGroups(priorities = [], areas = []) {
  const used = new Set()
  const normalize = (value) => String(value || '').trim().toLowerCase()
  const groups = priorities.map((sitio) => {
    const cards = (sitio.puroks || []).map((purok) => {
      const names = [`${purok.purok_name}, ${sitio.sitio}`, `${sitio.sitio} - ${purok.purok_name}`].map(normalize)
      const matches = areas.filter((area) => (area.sitio_id != null && area.purok_id != null && String(area.sitio_id) === String(sitio.sitio_id) && String(area.purok_id) === String(purok.purok_id)) || names.includes(normalize(area.area_name)))
      matches.forEach((area) => used.add(area.id))
      const households = matches.flatMap((area) => area.households || [])
      return { id: `purok:${purok.purok_id}`, name: purok.purok_name, area_name: matches[0]?.area_name || `${purok.purok_name}, ${sitio.sitio}`, households, total_households: purok.household_count ?? households.length, priority: matches[0]?.priority || 'monitor' }
    })
    for (const area of areas.filter((item) => !used.has(item.id) && (String(item.sitio_id) === String(sitio.sitio_id) || normalize(item.area_name) === normalize(sitio.sitio) || (sitio.address_labels || []).some((entry) => normalize(entry.label) === normalize(item.area_name))))) {
      used.add(area.id); cards.push({ ...area, name: area.area_name })
    }
    return { id: `sitio:${sitio.sitio_id}`, name: sitio.sitio, rank: sitio.rank, band: sitio.band, cards }
  })
  for (const area of areas.filter((item) => !used.has(item.id))) groups.push({ id: `area:${area.id}`, name: area.area_name, rank: groups.length + 1, band: { label: label(area.priority), key: ['critical','high'].includes(area.priority) ? 'high' : 'low' }, cards: [{ ...area, name: area.area_name }] })
  return groups
}

export function planAreaResponders(cards, selections, responders, count = 2) {
  const available = responders.filter((member) => member.is_available).sort((left, right) => Number(left.last_active_timestamp || 0) - Number(right.last_active_timestamp || 0) || Number(left.responder_id) - Number(right.responder_id))
  const used = new Set()
  const plans = {}
  for (const card of cards) {
    const teamId = selections[card.id]
    if (!teamId) continue
    const selected = available.filter((member) => String(member.team_id) === String(teamId) && !used.has(member.responder_id)).slice(0, count)
    plans[card.id] = selected.length === count ? selected : null
    selected.forEach((member) => used.add(member.responder_id))
  }
  return plans
}

export function splitCoverageHouseholds(households, count = 2) {
  const groups = Array.from({ length: count }, () => [])
  const weights = Array(count).fill(0)
  for (const household of [...households].sort((a, b) => Number(b.reported_unsafe_count || 0) - Number(a.reported_unsafe_count || 0))) {
    const index = weights.indexOf(Math.min(...weights))
    groups[index].push(household)
    weights[index] += Number(household.reported_unsafe_count || 0) * 3 + 1
  }
  return groups
}

export function shuffleDispatchItems(items) {
  const shuffled = [...items]
  for (let index = shuffled.length - 1; index > 0; index--) {
    const other = Math.floor(Math.random() * (index + 1))
    ;[shuffled[index], shuffled[other]] = [shuffled[other], shuffled[index]]
  }
  return shuffled
}

export function planSelectedAreaResponders(cards, selections, responders, teamId) {
  const available = new Map(responders.filter((member) => member.is_available && String(member.team_id) === String(teamId)).map((member) => [String(member.responder_id), member]))
  const used = new Set()
  const plans = {}
  for (const card of cards) {
    const ids = (selections[card.id] || []).filter(Boolean).map(String)
    if (!ids.length) continue
    plans[card.id] = ids.every((id) => available.has(id) && !used.has(id)) && new Set(ids).size === ids.length ? ids.map((id) => available.get(id)) : null
    ids.forEach((id) => used.add(id))
  }
  return plans
}

export function groupSitioDeployments(priorities, areas, assignments) {
  const normalize = (value) => String(value || '').trim().toLowerCase()
  const groups = buildDispatchAreaGroups(priorities, areas)
  const active = assignments.filter((assignment) => ['dispatched','accepted','en_route','on_scene','onscene','returning'].includes(assignment.status?.key))
  return priorities.map((sitio) => {
    const group = groups.find((item) => item.id === `sitio:${sitio.sitio_id}`)
    const labels = new Set([sitio.sitio, ...(sitio.address_labels || []).map((item) => item.label), ...(group?.cards || []).map((card) => card.area_name)].map(normalize))
    const households = new Set((group?.cards || []).flatMap((card) => card.households || []).map((household) => String(household.household_id)))
    const dispatches = active.filter((assignment) => labels.has(normalize(assignment.assigned_area)) || [assignment.household_id, ...(assignment.household_ids || [])].filter(Boolean).some((id) => households.has(String(id))))
    return { ...sitio, activeDispatches:dispatches }
  })
}
