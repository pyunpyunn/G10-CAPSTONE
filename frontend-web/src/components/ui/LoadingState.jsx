export default function LoadingState({ message = 'Loading...' }) {
  return (
    <div className="page-loading-card" role="status" aria-live="polite">
      <div className="page-loading-spinner" aria-hidden="true" />
      <div>
        <div className="page-loading-title">{message}</div>
      </div>
      <div className="page-loading-skeleton" aria-hidden="true">
        <span />
        <span />
        <span />
      </div>
    </div>
  )
}
