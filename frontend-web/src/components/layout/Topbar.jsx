import { Bell, CheckCircle2, Eye, Inbox } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getNotifications, markNotificationsRead } from '../../api/notificationApi'
import resqperationLogo from '../../assets/resqperation-logo.png'

export default function Topbar({ user }) {
  const navigate = useNavigate()
  const [isOpen, setIsOpen] = useState(false)
  const [notifications, setNotifications] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)
  const popoverRef = useRef(null)
  const bellButtonRef = useRef(null)
  const roleName = user?.role?.role_name || 'HQ'
  const displayName = user?.full_name || 'HQ Admin'
  const initials = getInitials(user?.full_name)
  const unreadLabel = unreadCount > 99 ? '99+' : String(unreadCount)
  const canViewInquiries = user?.role?.role_key === 'super_admin'

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

  useEffect(() => {
    if (!isOpen) {
      return undefined
    }

    function closeOnOutsideClick(event) {
      const clickedPopover = popoverRef.current?.contains(event.target)
      const clickedBell = bellButtonRef.current?.contains(event.target)

      if (!clickedPopover && !clickedBell) {
        setIsOpen(false)
      }
    }

    function closeOnEscape(event) {
      if (event.key === 'Escape') {
        setIsOpen(false)
        bellButtonRef.current?.focus()
      }
    }

    document.addEventListener('pointerdown', closeOnOutsideClick)
    document.addEventListener('keydown', closeOnEscape)

    return () => {
      document.removeEventListener('pointerdown', closeOnOutsideClick)
      document.removeEventListener('keydown', closeOnEscape)
    }
  }, [isOpen])

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

  function openInquiryPage() {
    navigate('/super-admin')
  }

  async function openNotification(item) {
    try {
      await markNotificationsRead([item.id])
      window.dispatchEvent(new Event('notifications:changed'))
    } finally {
      setIsOpen(false)
      navigate(item.action_url || '/notifications')
    }
  }

  return (
    <header className="topbar">
      <div className="header-brand" aria-label="ResQperation">
        <img className="header-brand-logo" src={resqperationLogo} alt="" aria-hidden="true" />

        <div className="header-brand-wordmark" role="img" aria-label="ResQperation">
          <span className="header-brand-wordmark-text header-brand-wordmark-prefix">res</span>
          <svg className="header-brand-wordmark-pin" viewBox="0 0 32 39" focusable="false" aria-hidden="true">
            <path
              fill="currentColor"
              fillRule="evenodd"
              d="M16 1C7.72 1 1 7.72 1 16c0 10.38 15 22 15 22s15-11.62 15-22C31 7.72 24.28 1 16 1Zm0 8.25a6.75 6.75 0 1 0 0 13.5 6.75 6.75 0 0 0 0-13.5Z"
              clipRule="evenodd"
            />
            <path d="M19 22.4 27.5 30" fill="none" stroke="var(--nav)" strokeWidth="4.5" strokeLinecap="round" />
          </svg>
          <span className="header-brand-wordmark-text header-brand-wordmark-suffix">peration</span>
          <svg className="header-brand-wordmark-route" viewBox="0 0 176 10" preserveAspectRatio="none" aria-hidden="true">
            <path d="M2 5h49c13 0 12-3 24-3h29c12 0 12 6 24 6h42" fill="none" stroke="currentColor" strokeWidth="1.5" strokeDasharray="5 5" strokeLinecap="round" />
            <circle cx="173" cy="5" r="2.5" fill="currentColor" />
          </svg>
        </div>
      </div>

      <div className="header-actions" aria-label="Header actions">
        {canViewInquiries && (
          <button
            className="header-action"
            type="button"
            title="Inquiries"
            aria-label="Open admin inquiries"
            onClick={openInquiryPage}
          >
            <Inbox size={16} />
          </button>
        )}

        <button
          className="header-action"
          ref={bellButtonRef}
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
        <div className="notification-popover" ref={popoverRef} role="dialog" aria-label="Recent notifications">
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
                <span>Actionable alerts will appear here.</span>
              </div>
            ) : (
              notifications.map((item) => (
                <button
                  className={`notification-preview-item ${item.read ? 'is-read' : ''}`}
                  key={item.id}
                  type="button"
                  onClick={() => openNotification(item)}
                >
                  <span className="notification-preview-dot" aria-hidden="true" />
                  <div>
                    <div className="notification-preview-title">{item.title}</div>
                    <div className="notification-preview-body">{item.body}</div>
                    <div className="notification-preview-time">{item.time} - {item.type}</div>
                  </div>
                </button>
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
