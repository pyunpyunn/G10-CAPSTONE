import { Clock3 } from 'lucide-react'

export default function ProfileActivityList({ activity = [] }) {
  return (
    <section className="profile-panel">
      <div className="profile-panel-head">
        <div className="profile-panel-title">Recent account activity</div>
        <Clock3 size={16} />
      </div>

      <div className="profile-activity-list">
        {activity.length === 0 ? (
          <div className="profile-empty-row">No recent account activity yet.</div>
        ) : (
          activity.map((item) => (
            <div className="profile-activity-row" key={item.id}>
              <div className="profile-row-icon">
                <Clock3 />
              </div>
              <div>
                <div className="profile-row-title">{item.title}</div>
                <div className="profile-row-sub">{item.description}</div>
              </div>
              <div className="profile-activity-time">
                <strong>{item.date_label}</strong>
                <span>{item.time}</span>
              </div>
            </div>
          ))
        )}
      </div>
    </section>
  )
}
