export default function DispatchStatusBadge({ status }) {
  const colors = { dispatched: '#3b82f6', accepted: '#3b82f6', en_route: '#3b82f6', on_scene: '#f59e0b', returning: '#8b5cf6', completed: '#0ea5e9', cancelled: '#64748b', safe: '#2563eb', evacuated: '#7c3aed', unsafe: '#dc2626', available: '#0ea5e9', pending: '#64748b' }
  return <span className="bg" style={{ '--c': colors[status?.key] || '#64748b' }}>{status?.label || 'Status unavailable'}</span>
}
