// Match the dashboard's semantic chart colors. Never derive a status from a color.
export const toneColors = Object.freeze({
  green: '#3a7d57', blue: '#2a5a90', red: '#962020', gray: '#6a7f94',
  amber: '#8f5c18', purple: '#240e6a',
})
export function normalizeStatus(value) {
  return String(value || '').trim().toLowerCase().replace(/[\s-]+/g, '_')
}
const groups = {
  green: ['safe', 'safe_only', 'safe_total', 'safe_at_home', 'all_members_safe', 'checked', 'active', 'returned', 'returned_home', 'available', 'on_duty', 'completed', 'approved', 'validated', 'verified', 'forwarded', 'fulfilled', 'delivered', 'released', 'rescued', 'responded', 'sent', 'normal', 'low', 'success'],
  blue: ['evacuated', 'relocated', 'dispatched', 'assigned', 'already_assigned', 'accepted', 'en_route', 'deployed', 'acknowledged', 'in_review', 'reserve', 'info'],
  red: ['unsafe', 'needs_help', 'need_help', 'needs_assistance', 'not_safe', 'not_evacuated', 'displaced', 'missing', 'injured', 'trapped', 'unreachable', 'deceased', 'failed', 'rejected', 'critical', 'high', 'urgent', 'danger', 'error'],
  amber: ['pending', 'needs_validation', 'returning', 'unavailable', 'warning', 'warn', 'moderate', 'medium', 'new', 'watch'],
  purple: ['on_scene', 'onscene', 'reviewed', 'archived', 'generated'],
}
const tonesByStatus = Object.fromEntries(Object.entries(groups).flatMap(([tone, keys]) => keys.map(key => [key, tone])))
export function statusTone(status) {
  const key = normalizeStatus(status)
  if (/_critical$|_high$/.test(key)) return 'red'
  if (/_medium$/.test(key)) return 'amber'
  if (/_low$/.test(key)) return 'green'
  return Object.hasOwn(toneColors, key) ? key : tonesByStatus[key] || 'gray'
}
export function statusColor(status) {
  return toneColors[statusTone(status)]
}
