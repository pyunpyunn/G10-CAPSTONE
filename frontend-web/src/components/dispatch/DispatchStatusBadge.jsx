export default function DispatchStatusBadge({ status }) {
  return <span className={`badge b-${status?.tone || 'gray'}`}>{status?.label || 'Stand-by'}</span>
}
