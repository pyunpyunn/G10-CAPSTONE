export function getStats(dashboard) {
  const households = dashboard?.households || {
    total: 0,
    unchecked: 0,
    safe_total: 0,
    safe_only: 0,
    evacuated: 0,
    unsafe: 0,
  }
  const hasActiveEvent = Boolean(dashboard?.active_event)
  const inactiveSub = hasActiveEvent ? '' : 'No active event'

  return [
    { label: 'Total households', value: households.total, sub: 'Registered records' },
    { label: 'Unchecked', value: households.unchecked, sub: hasActiveEvent ? 'No event report yet' : inactiveSub, tone: 'slate' },
    { label: 'Safe total', value: households.safe_total, sub: hasActiveEvent ? 'Safe only + evacuated' : inactiveSub, tone: 'green' },
    { label: 'Safe only', value: households.safe_only, sub: hasActiveEvent ? 'Reported safe at location' : inactiveSub, tone: 'green' },
    { label: 'Evacuated', value: households.evacuated, sub: hasActiveEvent ? 'At evacuation area' : inactiveSub, tone: 'blue' },
    { label: 'Unsafe', value: households.unsafe, sub: hasActiveEvent ? 'Needs dispatch focus' : inactiveSub, tone: 'red' },
  ]
}

export function makeAxis(bars = []) {
  const max = Math.max(...bars.map((bar) => Number(bar.value) || 0), 0)

  if (max === 0) {
    return ['0']
  }

  const top = Math.max(max, 1)
  return [top, Math.ceil(top * 0.75), Math.ceil(top * 0.5), Math.ceil(top * 0.25), 0]
}

export function percent(value, total) {
  if (!total) {
    return 0
  }

  return Math.min(100, Math.max(0, (Number(value) / Number(total)) * 100))
}

export function statusTone(statusKey) {
  const key = statusKey || ''

  if (['active', 'safe', 'returned', 'validated', 'released'].includes(key)) {
    return 'green'
  }

  if (['evacuated', 'relocated'].includes(key)) {
    return 'blue'
  }

  if (['not-evacuated', 'unsafe', 'missing', 'displaced'].includes(key)) {
    return 'red'
  }

  if (['dispatched', 'assigned', 'accepted', 'en-route'].includes(key)) {
    return 'purple'
  }

  if (['needs-validation', 'pending'].includes(key)) {
    return 'amber'
  }

  return 'gray'
}

export function eventTone(severityKey) {
  if (['critical', 'high'].includes(severityKey)) {
    return 'red'
  }

  if (severityKey === 'medium') {
    return 'amber'
  }

  return 'green'
}

export function getCloseEventMessage(error) {
  if (!error?.response) {
    return 'Cannot connect to the server right now. Please check the backend connection.'
  }

  return error.response.data?.message || 'Unable to close the active event. Please try again.'
}
