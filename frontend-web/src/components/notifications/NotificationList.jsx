import Badge from '../ui/Badge'
import { groupNotifications } from '../../utils/notificationHelpers'

export default function NotificationList({
  notifications = [],
  selectedIds = [],
  onToggleSelected,
  pagination = {},
  onPrevious,
  onNext,
}) {
  const grouped = groupNotifications(notifications)
  const page = pagination.current_page || 1
  const pageCount = pagination.page_count || 1

  return (
    <section className="notification-page-panel" aria-label="Notification list">
      {notifications.length === 0 ? (
        <div className="notification-page-empty">No notifications match the current filter.</div>
      ) : (
        Object.entries(grouped).map(([date, items]) => (
          <section className="notification-date-group" key={date}>
            <div className="notification-date-label">{date}</div>
            {items.map((item) => (
              <article className={`notification-page-item ${item.read ? 'is-read' : ''}`} key={item.id}>
                <label className="notification-page-check" aria-label={`Select ${item.title}`}>
                  <input
                    type="checkbox"
                    checked={selectedIds.includes(item.id)}
                    onChange={(event) => onToggleSelected(item.id, event.target.checked)}
                  />
                </label>
                <div>
                  <div className="notification-page-title">{item.title}</div>
                  <div className="notification-page-body">{item.body}</div>
                  <div className="notification-page-meta">
                    <span>{item.time}</span>
                    <span className="notification-status-pill">{item.read ? 'Read' : 'Unread'}</span>
                    <Badge tone={item.tone || 'gray'}>{item.priority}</Badge>
                  </div>
                </div>
                <div className="notification-page-type">{item.type}</div>
              </article>
            ))}
          </section>
        ))
      )}

      <div className="notification-pagination">
        <button className="btn btn-secondary btn-sm" type="button" disabled={page <= 1} onClick={onPrevious}>
          Previous
        </button>
        <span className="ops-label">Page {page} of {pageCount}</span>
        <button className="btn btn-secondary btn-sm" type="button" disabled={page >= pageCount} onClick={onNext}>
          Next
        </button>
      </div>
    </section>
  )
}
