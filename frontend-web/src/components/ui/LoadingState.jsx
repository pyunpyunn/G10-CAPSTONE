export default function LoadingState({ inline = false }) {
  return (
    <div className={`page-loading-card ${inline ? 'inline' : ''}`} role="status" aria-live="polite">
      <div className="page-loading-spinner" aria-hidden="true" />
      <div className="page-loading-title">Loading...</div>
    </div>
  )
}
