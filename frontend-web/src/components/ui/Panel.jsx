export default function Panel({ title, action, children, className = '' }) {
  return (
    <section className={`panel ${className}`.trim()}>
      {(title || action) && (
        <div className="panel-head">
          {title && <span className="panel-title">{title}</span>}
          {action}
        </div>
      )}
      {children}
    </section>
  )
}
