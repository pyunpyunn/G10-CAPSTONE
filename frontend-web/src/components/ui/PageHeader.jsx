export default function PageHeader({ title, subtitle, actions }) {
  return (
    <div className="pg-head">
      <div>
        <h1 className="pg-title">{title}</h1>
        {subtitle && <p className="pg-sub">{subtitle}</p>}
      </div>
      {actions && <div className="pg-actions">{actions}</div>}
    </div>
  )
}
