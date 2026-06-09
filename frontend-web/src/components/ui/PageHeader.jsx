export default function PageHeader({ title, actions }) {
  return (
    <div className="pg-head">
      <div>
        <h1 className="pg-title">{title}</h1>
      </div>
      {actions && <div className="pg-actions">{actions}</div>}
    </div>
  )
}
