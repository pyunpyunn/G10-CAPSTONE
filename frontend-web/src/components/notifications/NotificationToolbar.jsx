import { CheckCircle2, X } from 'lucide-react'

export default function NotificationToolbar({
  statusFilter,
  onFilterChange,
  onMarkAllRead,
  onDeleteSelected,
  onClearAll,
  disabled = false,
}) {
  return (
    <div className="page-ops-row">
      <div className="left">
        <select
          className="filter-select notification-filter-select"
          value={statusFilter}
          aria-label="Filter notifications"
          onChange={(event) => onFilterChange(event.target.value)}
        >
          <option value="all">All notifications</option>
          <option value="unread">Unread only</option>
          <option value="read">Read only</option>
        </select>
      </div>
      <div className="right">
        <button className="btn btn-secondary btn-sm" type="button" disabled={disabled} onClick={onMarkAllRead}>
          <CheckCircle2 size={14} />
          Mark all read
        </button>
        <button className="btn btn-secondary btn-sm" type="button" disabled={disabled} onClick={onDeleteSelected}>
          <X size={14} />
          Delete selected
        </button>
        <button className="btn btn-danger btn-sm" type="button" disabled={disabled} onClick={onClearAll}>
          <X size={14} />
          Clear all
        </button>
      </div>
    </div>
  )
}
