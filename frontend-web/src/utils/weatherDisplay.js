const timezone = 'Asia/Manila'
export function weatherNumber(value, unit = '') {
  return value === null || value === undefined || value === '' || !Number.isFinite(Number(value)) ? '—' : `${Number(value)}${unit}`
}
export function weatherTime(iso) {
  if (!iso || !Number.isFinite(Date.parse(iso))) return 'Time not recorded'
  return new Intl.DateTimeFormat('en-PH', { timeZone: timezone, hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
}
export function weatherDay(iso, now = new Date()) {
  if (!iso || !Number.isFinite(Date.parse(iso))) return 'Date not recorded'
  const date = new Date(iso)
  const day = value => new Intl.DateTimeFormat('en-CA', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(value)
  if (day(date) === day(now)) return 'Today'
  if (day(date) === day(new Date(now.getTime() - 86400000))) return 'Yesterday'
  if (day(date) === day(new Date(now.getTime() + 86400000))) return 'Tomorrow'
  return new Intl.DateTimeFormat('en-PH', { timeZone: timezone, month: 'short', day: 'numeric' }).format(date)
}
export function weatherGradient(condition) {
  const key = String(condition || '').toLowerCase()
  return /storm/.test(key) ? 'linear-gradient(135deg,#3b2f63,#0b1020)' : /rain/.test(key) ? 'linear-gradient(135deg,#2c4a73,#0b1524)' : /sun|clear/.test(key) ? 'linear-gradient(135deg,#2563eb,#0f1c2d)' : 'linear-gradient(135deg,#4b6384,#0f1c2d)'
}
