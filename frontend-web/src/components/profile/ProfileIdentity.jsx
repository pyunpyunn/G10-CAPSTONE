export default function ProfileIdentity({ identity = {}, barangayProfile = {}, settingsMenu = null }) {
  const name = identity.name || 'HQ/Admin Desk'
  const barangayName = barangayProfile.name || 'Registered barangay'
  const registered = barangayProfile.registered_barangay || {}
  const rows = [
    ['Account ID', identity.account_id || 'Not recorded'],
    ['User ID', identity.user_id || 'Not recorded'],
    ['Role', identity.role_name || 'HQ/Admin'],
    ['Account status', identity.status || 'Active and verified'],
    ['Email address', displayValue(identity.email, 'No email recorded')],
    ['Mobile number', displayValue(identity.contact_number, 'No mobile recorded')],
    ['Assigned station', identity.assigned_station || 'Command desk'],
  ]
  const barangayRows = [
    ['Registered barangay', registered.barangay_name || barangayName],
    ['Source', registered.source || sourceLabel(barangayProfile.source)],
    ['City / Municipality', barangayProfile.city_name || registered.city_name || 'Not recorded'],
    ['Province', barangayProfile.province_name || registered.province_name || 'Not recorded'],
    ['Office address', barangayProfile.office_address || registered.office_address || 'Not recorded'],
    ['Office contact', barangayProfile.contact_number || registered.contact_number || 'Not recorded'],
    ['Weather focus', barangayProfile.weather_name || barangayName],
  ]
  const mapCenter = barangayProfile.center || {}

  return (
    <section className="profile-identity profile-identity-large">
      <div className="profile-hero-block">
        <div className="profile-hero-main">
          <div className="profile-page-avatar">{initials(name)}</div>
          <div className="profile-hero-copy">
            <div className="profile-kicker">HQ/Admin profile</div>
            <div className="profile-name">{name}</div>
            <div className="profile-role">{identity.role_name || 'HQ/Admin'} - {barangayName}</div>
            <div className="profile-status-line">
              <span>{identity.status || 'Active and verified'}</span>
              <span>{identity.account_id || identity.user_id || 'Account ID not recorded'}</span>
            </div>
          </div>
        </div>
        {settingsMenu && <div className="profile-hero-settings">{settingsMenu}</div>}
      </div>

      <div className="profile-info-grid">
        <InfoBlock title="Account information" rows={rows} />
        <InfoBlock title="Barangay deployment" rows={barangayRows} />
        <div className="profile-map-focus">
          <div className="profile-info-title">Operational map focus</div>
          <div className="profile-coordinate-grid">
            <div>
              <span>Latitude</span>
              <strong>{mapCenter.latitude ?? 'Not set'}</strong>
            </div>
            <div>
              <span>Longitude</span>
              <strong>{mapCenter.longitude ?? 'Not set'}</strong>
            </div>
            <div>
              <span>Zoom</span>
              <strong>{barangayProfile.map_zoom || 16}</strong>
            </div>
          </div>
          <p>{barangayProfile.save_note || 'This barangay controls dashboard labels, weather coordinates, and mapping focus.'}</p>
        </div>
      </div>
    </section>
  )
}

function InfoBlock({ title, rows }) {
  return (
    <div className="profile-info-block">
      <div className="profile-info-title">{title}</div>
      <div className="profile-detail-list">
        {rows.map(([label, value]) => (
          <div className="profile-detail-row" key={label}>
            <span>{label}</span>
            <strong>{value}</strong>
          </div>
        ))}
      </div>
    </div>
  )
}

function displayValue(value, emptyText) {
  return !value || value === emptyText ? emptyText : value
}

function sourceLabel(source) {
  return source === 'database' ? 'Saved barangay profile' : 'Environment fallback'
}

function initials(name = '') {
  const parts = name.trim().split(' ').filter(Boolean)
  const value = parts.map((part) => part[0]).join('').slice(0, 2)
  return value || 'HQ'
}
