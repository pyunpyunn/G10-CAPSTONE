export default function LoadingState({ message = 'Loading...' }) {
  return (
    <div className="page-loading-card" role="status" aria-live="polite">
      <div className="page-loading-spinner" aria-hidden="true" />
      <div>
        <div className="page-loading-title">{message}</div>
        <div className="page-loading-sub">Fetching live RESQPERATION records from the server.</div>
      </div>
      <div className="page-loading-skeleton" aria-hidden="true">
        <span />
        <span />
        <span />
      </div>
    </div>
  )
}
