export default function PageHeader({ filters, actions }) {
  if (!filters && !actions) return null

  return (
    <div className="app-page-controls-row">
      {filters && <div className="app-page-controls-filters">{filters}</div>}
      {actions && <div className="pg-actions">{actions}</div>}
    </div>
  )
}
