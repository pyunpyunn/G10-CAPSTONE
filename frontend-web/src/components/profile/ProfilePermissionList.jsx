import { CheckCircle2, ShieldCheck } from 'lucide-react'
import Badge from '../ui/Badge'

export default function ProfilePermissionList({ permissions = [] }) {
  return (
    <section className="profile-panel">
      <div className="profile-panel-head">
        <div className="profile-panel-title">Role permissions</div>
        <ShieldCheck size={16} />
      </div>

      <div className="profile-permission-list">
        {permissions.length === 0 ? (
          <div className="profile-empty-row">No permissions found for this account.</div>
        ) : (
          permissions.map((permission) => (
            <div className="profile-permission-row" key={permission.key}>
              <div className="profile-row-icon">
                <CheckCircle2 />
              </div>
              <div>
                <div className="profile-row-title">{permission.title}</div>
                <div className="profile-row-sub">{permission.description}</div>
              </div>
              <Badge tone={permission.tone || 'gray'}>{permission.status}</Badge>
            </div>
          ))
        )}
      </div>
    </section>
  )
}
