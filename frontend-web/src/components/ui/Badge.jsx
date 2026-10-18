import { statusColor } from '../../utils/statusPalette'

export default function Badge({ tone = 'gray', children }) {
  return <span className={`badge b-${tone}`} style={{ '--status-color': statusColor(tone) }}>{children}</span>
}
