export default function ProfileIdentity({ identity = {}, barangayProfile = {} }) {
  const name = identity.name || 'HQ/Admin Desk'
  const barangayName = barangayProfile.name || 'Registered barangay'

  const rows = [
    ['Account ID', identity.account_id || 'Not recorded'],
    ['Email', identity.email || 'No email recorded'],
    ['Mobile', identity.contact_number || 'No mobile recorded'],
    ['Status', identity.status || 'Active and verified'],
    ['Assigned station', identity.assigned_station || 'Command desk'],
  ]

  return (
    <section className="profile-identity">
      <div className="profile-identity-top">
        <div className="profile-page-avatar">{initials(name)}</div>
        <div>
          <div className="profile-name">{name}</div>
          <div className="profile-role">{identity.role_name || 'HQ/Admin'} - {barangayName}</div>
        </div>
      </div>

      <div className="profile-detail-list">
        {rows.map(([label, value]) => (
          <div className="profile-detail-row" key={label}>
            <span>{label}</span>
            <strong>{value}</strong>
          </div>
        ))}
      </div>
    </section>
  )
}

function initials(name = '') {
  const parts = name.trim().split(' ').filter(Boolean)
  const value = parts.map((part) => part[0]).join('').slice(0, 2)
  return value || 'HQ'
}
