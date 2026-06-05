export const statusFilters = [
  { key: 'all', label: 'All', countKey: 'total' },
  { key: 'unchecked', label: 'Unchecked', countKey: 'unchecked' },
  { key: 'safe', label: 'Safe total', countKey: 'safe_total' },
  { key: 'evacuated', label: 'Evacuated', countKey: 'evacuated' },
  { key: 'unsafe', label: 'Unsafe', countKey: 'unsafe' },
  { key: 'device', label: 'Device alerts', countKey: 'device_alerts' },
  { key: 'urgent', label: 'Urgent', countKey: 'urgent', urgent: true },
]

export function makeProgress(summary) {
  return [
    { label: 'Safe only', percent: percent(summary.safe_only, summary.total), className: 'safe' },
    { label: 'Evacuated', percent: percent(summary.evacuated, summary.total), className: 'evac' },
    { label: 'Unsafe', percent: percent(summary.unsafe, summary.total), className: 'unsafe' },
    { label: 'Unchecked', percent: percent(summary.unchecked, summary.total), className: 'unchecked' },
  ]
}

export function percent(value, total) {
  if (!total) {
    return 0
  }

  return Math.round((Number(value || 0) / Number(total)) * 100)
}

export function tableRange(meta) {
  if (!meta.total) {
    return 'Showing 0 of 0'
  }

  return `Showing ${meta.from || 1}-${meta.to || 0} of ${meta.total}`
}

export function emptySummary() {
  return {
    total: 0,
    reported: 0,
    unchecked: 0,
    safe_total: 0,
    safe_only: 0,
    evacuated: 0,
    unsafe: 0,
    device_alerts: 0,
    urgent: 0,
  }
}

export function csvValue(value) {
  const text = value === null || value === undefined ? '' : String(value)
  return `"${text.replaceAll('"', '""')}"`
}
