export default function LoadingState() {
  return (
    <div className="page-loading-card" role="status" aria-live="polite">
      <div className="page-loading-spinner" aria-hidden="true" />
      <div className="page-loading-title">Loading...</div>
    </div>
  )
}
