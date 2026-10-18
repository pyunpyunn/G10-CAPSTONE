export default function PageHeader({ filters, actions, event, operations = false }) {
  if (operations) return <div className="ro-header">
    <span className={`ro-event${event ? '' : ' is-inactive'}`}><i aria-hidden="true" />{event?.name || 'No active event'}</span>
    <div className="ro-header-actions">{actions}</div>
  </div>
  if (!filters && !actions) return null

  return (
    <div className="app-page-controls-row">
      {filters && <div className="app-page-controls-filters">{filters}</div>}
      {actions && <div className="pg-actions">{actions}</div>}
    </div>
  )
}
export function ResponseOperationsLayout({ children }) {
  return <div className="ro-reference ro-shell"><div className="ro-content">{children}</div></div>
}
