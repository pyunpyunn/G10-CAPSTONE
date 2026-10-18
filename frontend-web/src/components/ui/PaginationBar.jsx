export default function PaginationBar({ meta = {}, onPageChange, label = 'records', compact = false, reference = false }) {
  const current = meta.current_page || 1
  const perPage = meta.per_page || 6
  const total = meta.total || 0
  const last = meta.last_page || Math.max(1, Math.ceil(total / perPage))
  const canGoPrevious = current > 1
  const canGoNext = current < last
  if (reference) return <div className="pgr"><span>{total ? `${meta.from ?? (current - 1) * perPage + 1}–${meta.to ?? Math.min(current * perPage, total)} of ${total}` : '0'}</span><span className="sp" /><button type="button" aria-label={`Previous ${label} page`} disabled={!canGoPrevious} onClick={() => onPageChange(current - 1)}>‹</button><button type="button" aria-label={`Next ${label} page`} disabled={!canGoNext} onClick={() => onPageChange(current + 1)}>›</button></div>

  return (
    <div className="pagination-bar">
      <span className="pagination-info">
        {compact ? `${total ? meta.from ?? (current - 1) * perPage + 1 : 0}–${total ? meta.to ?? Math.min(current * perPage, total) : 0} of ${total}` : <>Page <strong>{current}</strong> of {last} - {perPage} {label} per page</>}
      </span>
      <div className="pagination-actions">
        <button
          className="pg-btn"
          type="button"
          disabled={!canGoPrevious}
          onClick={() => onPageChange(current - 1)}
        >
          {'<'}
        </button>
        <button className="pg-btn active" type="button" disabled>
          {current}
        </button>
        <button
          className="pg-btn"
          type="button"
          disabled={!canGoNext}
          onClick={() => onPageChange(current + 1)}
        >
          {'>'}
        </button>
      </div>
    </div>
  )
}
