import { Info } from 'lucide-react'
import EmptyState from '../ui/EmptyState'
import {
  PriorityPill,
  StatusBadge,
} from './HouseholdTable'

export default function HouseholdOpsPanels({ activities, rows }) {
  return (
    <div className="hh-two-col">
      <ActivityTimeline activities={activities} />
      <PurokTriage rows={rows} />
    </div>
  )
}

function ActivityTimeline({ activities }) {
  return (
    <section className="hh-ops-panel">
      <div className="hh-ops-head">
        <span>Activity timeline</span>
        <span>Last 10</span>
      </div>
      {activities.length === 0 ? (
        <EmptyState title="No status activity yet" message="Household and responder reports will appear here after the active event receives updates." />
      ) : (
        <div className="hh-timeline">
          {activities.map((activity) => (
            <div className="hh-tl-item" key={activity.status_log_id}>
              <div className={`hh-tl-dot ${activity.status?.tone || 'gray'}`}>{activity.status?.label?.charAt(0) || '?'}</div>
              <div>
                <div className="hh-tl-text">
                  <strong>{activity.household_name}</strong> - Status: <StatusBadge status={activity.status} />
                </div>
                <div className="hh-tl-time">
                  {activity.source} - {activity.time || 'No time'} {activity.battery_level !== null ? `- Battery ${activity.battery_level}%` : ''}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
      <div className="log-perf-note">
        <Info size={12} />
        Latest status rows load first. Full household history loads only when a household is opened.
      </div>
    </section>
  )
}

function PurokTriage({ rows }) {
  return (
    <section className="hh-ops-panel">
      <div className="hh-ops-head">
        <span>Purok triage</span>
        <span>{rows.length} areas</span>
      </div>
      {rows.length === 0 ? (
        <EmptyState title="No purok summary yet" message="Area counts will appear after household records have address or purok data." />
      ) : (
        <div className="hh-detail-table-wrap">
          <table className="hh-mini-table">
            <thead>
              <tr>
                <th>Purok</th>
                <th>Total</th>
                <th>Reported</th>
                <th>Unchecked</th>
                <th>Unsafe</th>
                <th>Device risk</th>
                <th>Next</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr className={row.priority === 'urgent' ? 'hh-row-urgent' : ''} key={row.purok}>
                  <td><strong>{row.purok}</strong></td>
                  <td>{row.total}</td>
                  <td>{row.reported}</td>
                  <td>{row.unchecked}</td>
                  <td className="hh-num-danger">{row.unsafe}</td>
                  <td>{row.device_risk}</td>
                  <td><PriorityPill priority={{ key: row.priority, label: row.next_action }} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
