import { Bell, CheckCircle2, Eye } from 'lucide-react'
import { useState } from 'react'

export default function Topbar({ user }) {
  const [isOpen, setIsOpen] = useState(false)
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
          <span className="notification-dot" aria-hidden="true" />
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
            <span className="notification-count-pill">3 unread</span>
          </div>
          <div className="notification-popover-list">
            <div className="notification-preview-item">
              <span className="notification-preview-dot" />
              <div>
                <div className="notification-preview-title">Unsafe household report</div>
                <div className="notification-preview-body">Reyes Family, Purok 5. Rescuer flagged for dispatch.</div>
                <div className="notification-preview-time">Just now</div>
              </div>
            </div>
            <div className="notification-preview-item">
              <span className="notification-preview-dot" />
              <div>
                <div className="notification-preview-title">Resource request received</div>
                <div className="notification-preview-body">Food packs request from evacuation site needs validation.</div>
                <div className="notification-preview-time">5 min ago</div>
              </div>
            </div>
          </div>
          <div className="notification-popover-actions">
            <button className="btn btn-secondary btn-sm" type="button">
              <CheckCircle2 size={14} />
              Mark as read
            </button>
            <button className="btn btn-primary btn-sm" type="button">
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
