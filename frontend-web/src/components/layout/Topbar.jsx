import { Bell } from 'lucide-react'
import { useState } from 'react'

export default function Topbar({ user }) {
  const [isOpen, setIsOpen] = useState(false)
  const notifications = []
  const roleName = user?.role?.role_name || 'HQ'
  const initials = getInitials(user?.full_name)

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
          aria-label="Notifications"
          aria-haspopup="dialog"
          aria-expanded={isOpen}
          onClick={() => setIsOpen(!isOpen)}
        >
          <Bell size={17} />
          {notifications.length > 0 && <span className="notification-dot" aria-hidden="true" />}
        </button>

        <button className="header-profile" type="button" title="Profile" aria-label="Profile">
          <span className="profile-avatar">{initials}</span>
          <span className="profile-label">{roleName}</span>
        </button>
      </div>

      {isOpen && (
        <div className="notification-popover" role="dialog" aria-label="Recent notifications">
          <div className="notification-popover-head">
            <div className="notification-popover-title">
              <Bell size={16} />
              Notifications
            </div>
            <span className="notification-count-pill">{notifications.length} unread</span>
          </div>
          <div className="notification-popover-list">
            <div className="notification-empty">
              <strong>No notifications yet</strong>
              <span>Broadcast delivery, household alerts, and request notices will appear here after those modules are connected.</span>
            </div>
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
