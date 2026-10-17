import { statusColor } from '../../utils/statusPalette'

export default function DispatchStatusBadge({ status }) {
  return <span className="bg" style={{ '--c': statusColor(status?.key) }}>{status?.label || 'Status unavailable'}</span>
}
