import { statusColor } from './statusPalette'
export const statuses = [
  { key: 'unsafe', label: 'Unsafe', color: statusColor('unsafe') },
  { key: 'unchecked', label: 'Unchecked', color: statusColor('unchecked') },
  { key: 'evacuated', label: 'Evacuated', color: statusColor('evacuated') },
  { key: 'safe', label: 'Safe', color: statusColor('safe') },
]
export const bandColor = band => statusColor(band?.key || 'low')
