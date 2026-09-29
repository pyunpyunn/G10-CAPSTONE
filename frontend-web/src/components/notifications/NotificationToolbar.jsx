import { CheckCircle2, X } from 'lucide-react'
import DataFilterBar from '../ui/DataFilterBar'

export default function NotificationToolbar({
  statusFilter,
  onFilterChange,
  onMarkAllRead,
  onDeleteSelected,
  onClearAll,
  disabled = false,
}) {
  return (
    <div className="page-ops-row notification-toolbar notification-heading-toolbar">
      <DataFilterBar
        filters={[{
          id: 'read-status', label: 'Read status', value: statusFilter, onChange: onFilterChange,
          options: [
            { value: 'all', label: 'All notifications' },
            { value: 'unread', label: 'Unread only' },
            { value: 'read', label: 'Read only' },
          ],
        }]}
        onReset={() => onFilterChange('all')}
      />
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
