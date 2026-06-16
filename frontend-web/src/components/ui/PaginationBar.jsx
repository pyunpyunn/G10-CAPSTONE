export default function PaginationBar({ meta = {}, onPageChange, label = 'records' }) {
  const current = meta.current_page || 1
  const perPage = meta.per_page || 6
  const total = meta.total || 0
  const last = meta.last_page || Math.max(1, Math.ceil(total / perPage))
  const canGoPrevious = current > 1
  const canGoNext = current < last

  return (
    <div className="pagination-bar">
      <span className="pagination-info">
        Page <strong>{current}</strong> of {last} - {perPage} {label} per page
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
