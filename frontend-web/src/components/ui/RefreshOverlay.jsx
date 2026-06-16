export default function RefreshOverlay({ active = false, children }) {
  return (
    <div className={`refresh-region ${active ? 'is-refreshing' : ''}`}>
      {active && (
        <div className="refresh-pill" role="status" aria-live="polite">
          <span className="refresh-spinner" aria-hidden="true" />
          Updating...
        </div>
      )}
      {children}
    </div>
  )
}
