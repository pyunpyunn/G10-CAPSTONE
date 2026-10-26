import { palette } from './resqTheme';

// Same semantic palette as the web dashboard.
export const toneColors = Object.freeze({
  green: palette.safe, blue: palette.evacuated, red: palette.unsafe, gray: palette.unchecked,
  amber: palette.warning, purple: palette.dispatch,
})
export function normalizeStatus(value: unknown) {
  return String(value || '').trim().toLowerCase().replace(/[\s-]+/g, '_')
}
const groups: Record<string, string[]> = {
  green: ['safe', 'safe_only', 'safe_total', 'safe_at_home', 'all_members_safe', 'checked', 'active', 'returned', 'returned_home', 'available', 'on_duty', 'completed', 'approved', 'validated', 'verified', 'forwarded', 'fulfilled', 'delivered', 'released', 'rescued', 'responded', 'sent', 'normal', 'low', 'success'],
  blue: ['evacuated', 'relocated', 'dispatched', 'assigned', 'already_assigned', 'accepted', 'en_route', 'deployed', 'acknowledged', 'in_review', 'reserve', 'info'],
  red: ['unsafe', 'needs_help', 'need_help', 'needs_assistance', 'not_safe', 'not_evacuated', 'displaced', 'missing', 'injured', 'trapped', 'unreachable', 'deceased', 'failed', 'rejected', 'critical', 'high', 'urgent', 'danger', 'error'],
  amber: ['pending', 'needs_validation', 'returning', 'unavailable', 'warning', 'warn', 'moderate', 'medium', 'new', 'watch'],
  purple: ['on_scene', 'onscene', 'reviewed', 'archived', 'generated'],
}
const tonesByStatus: Record<string, keyof typeof toneColors> = Object.fromEntries(Object.entries(groups).flatMap(([tone, keys]) => keys.map(key => [key, tone as keyof typeof toneColors])))
export function statusTone(status: unknown): keyof typeof toneColors {
  const key = normalizeStatus(status)
  return Object.hasOwn(toneColors, key) ? key as keyof typeof toneColors : tonesByStatus[key] || 'gray'
}
export function statusColor(status: unknown) {
  return toneColors[statusTone(status)]
}
