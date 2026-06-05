import { Bell, CheckCircle2, Eye } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getNotifications, markNotificationsRead } from '../../api/notificationApi'

export default function Topbar({ user }) {
  const navigate = useNavigate()
  const [isOpen, setIsOpen] = useState(false)
  const [notifications, setNotifications] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)
  const roleName = user?.role?.role_name || 'HQ'
  const displayName = user?.full_name || 'HQ Admin'
  const initials = getInitials(user?.full_name)
  const unreadLabel = unreadCount > 99 ? '99+' : String(unreadCount)

  useEffect(() => {
    let ignore = false

    async function loadPreview() {
      try {
        const data = await getNotifications({ status: 'all', page: 1 })

        if (!ignore) {
          setNotifications(data.preview || [])
          setUnreadCount(data.summary?.unread || 0)
        }
      } catch {
        if (!ignore) {
          setNotifications([])
          setUnreadCount(0)
        }
      }
    }

    async function refreshPreview() {
      try {
        const data = await getNotifications({ status: 'all', page: 1 })

        setNotifications(data.preview || [])
        setUnreadCount(data.summary?.unread || 0)
      } catch {
        setNotifications([])
        setUnreadCount(0)
      }
    }

    loadPreview()
    window.addEventListener('notifications:changed', refreshPreview)

    return () => {
      ignore = true
      window.removeEventListener('notifications:changed', refreshPreview)
    }
  }, [])

  async function markAllRead() {
    try {
      await markNotificationsRead([])
      window.dispatchEvent(new Event('notifications:changed'))
    } finally {
      setNotifications((currentItems) => currentItems.map((item) => ({ ...item, read: true })))
      setUnreadCount(0)
    }
  }

  function openNotificationsPage() {
    setIsOpen(false)
    navigate('/notifications')
  }

  return (
    <header className="topbar">
      <div className="header-brand" aria-label="RESQPERATION">
        <div className="header-brand-mark">R</div>
        <strong>RESQPERATION</strong>
      </div>

      <div className="header-actions" aria-label="Header actions">
        <button
          className="header-action"
          type="button"
          title="Notifications"
          aria-label={`${unreadLabel} unread notifications`}
          aria-haspopup="dialog"
          aria-expanded={isOpen}
          onClick={() => setIsOpen(!isOpen)}
        >
          <Bell size={17} />
          {unreadCount > 0 && <span className="notification-badge" aria-hidden="true">{unreadLabel}</span>}
        </button>

        <button
          className="header-profile"
          type="button"
          title="Profile"
          aria-label="Profile"
          onClick={() => navigate('/profile')}
        >
          <span className="profile-avatar">{initials}</span>
          <span className="profile-copy">
            <span className="profile-label">{displayName}</span>
            <span className="profile-role-label">{roleName}</span>
          </span>
        </button>
      </div>

      {isOpen && (
        <div className="notification-popover" role="dialog" aria-label="Recent notifications">
          <div className="notification-popover-head">
            <div className="notification-popover-title">
              <Bell size={16} />
              Notifications
            </div>
            <span className="notification-count-pill">{unreadCount} unread</span>
          </div>
          <div className="notification-popover-list">
            {notifications.length === 0 ? (
              <div className="notification-empty">
                <strong>No notifications yet</strong>
                <span>Broadcast delivery, household alerts, and request notices will appear here after those modules are connected.</span>
              </div>
            ) : (
              notifications.map((item) => (
                <article className={`notification-preview-item ${item.read ? 'is-read' : ''}`} key={item.id}>
                  <span className="notification-preview-dot" aria-hidden="true" />
                  <div>
                    <div className="notification-preview-title">{item.title}</div>
                    <div className="notification-preview-body">{item.body}</div>
                    <div className="notification-preview-time">{item.time} - {item.type}</div>
                  </div>
                </article>
              ))
            )}
          </div>
          <div className="notification-popover-actions">
            <button className="btn btn-secondary btn-sm" type="button" onClick={markAllRead}>
              <CheckCircle2 size={14} />
              Mark as read
            </button>
            <button className="btn btn-primary btn-sm" type="button" onClick={openNotificationsPage}>
              <Eye size={14} />
              View all
            </button>
          </div>
        </div>
      )}
    </header>
  )
}

function getInitials(name = '') {
  const parts = name.trim().split(' ').filter(Boolean)
  const initials = parts.map((part) => part[0]).join('').slice(0, 2)
  return initials || 'HQ'
}
