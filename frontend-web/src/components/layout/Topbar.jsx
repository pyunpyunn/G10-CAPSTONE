import { Bell, ShieldCheck } from 'lucide-react'

export default function Topbar({ user }) {
  const roleName = user?.role?.role_name || 'HQ'
  const initials = getInitials(user?.full_name)

  return (
    <header className="topbar">
      <div>
        <p className="eyebrow">RESQPERATION HQ</p>
        <h1>Barangay Response Command</h1>
      </div>

      <div className="topbar-actions">
        <button className="icon-button" type="button" aria-label="Notifications">
          <Bell size={18} />
          <span className="notice-dot" />
        </button>

        <div className="user-chip">
          <span className="avatar">{initials}</span>
          <div>
            <strong>{user?.full_name || 'HQ Admin'}</strong>
            <span>
              <ShieldCheck size={13} />
              {roleName}
            </span>
          </div>
        </div>
      </div>
    </header>
  )
}

function getInitials(name = '') {
  const parts = name.trim().split(' ').filter(Boolean)
  const initials = parts.map((part) => part[0]).join('').slice(0, 2)
  return initials || 'HQ'
}
