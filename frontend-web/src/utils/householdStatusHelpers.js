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
  const total = Math.max(0, Number(summary.total) || 0)
  const values = [
    { label: 'Safe only', count: Number(summary.safe_only) || 0, className: 'safe' },
    { label: 'Evacuated', count: Number(summary.evacuated) || 0, className: 'evacuated' },
    { label: 'Unsafe', count: Number(summary.unsafe) || 0, className: 'unsafe' },
    { label: 'Unchecked', count: Number(summary.unchecked) || 0, className: 'unchecked' },
  ]
  const remaining = Math.max(0, total - values.reduce((sum, item) => sum + item.count, 0))
  if (remaining > 0) values.push({ label: 'Other reported', count: remaining, className: 'other' })
  return values.map((item) => ({ ...item, percent: total > 0 ? Math.round(item.count / total * 1000) / 10 : 0, width: total > 0 ? item.count / total * 100 : 0 }))
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
