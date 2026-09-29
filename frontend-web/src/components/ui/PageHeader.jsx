export default function PageHeader({ title, subtitle, filters, actions }) {
  return (
    <header className="pg-head app-page-heading">
      <div className="app-page-heading-copy">
        <h1 className="pg-title">{title}</h1>
        {subtitle && <p className="pg-sub">{subtitle}</p>}
      </div>
      {(filters || actions) && (
        <div className="app-page-heading-tools">
          {filters && <div className="app-page-heading-filters">{filters}</div>}
          {actions && <div className="pg-actions">{actions}</div>}
        </div>
      )}
    </header>
  )
}
