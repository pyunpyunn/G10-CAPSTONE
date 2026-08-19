export default function LoadingState({ label = 'Loading...' }) {
  return (
    <div className="page-loading-card" role="status" aria-live="polite">
      <div className="page-loading-spinner" aria-hidden="true" />
      <div className="page-loading-title">{label}</div>
    </div>
  )
}
