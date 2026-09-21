import EmptyState from '../ui/EmptyState'
import {
  PriorityPill,
} from './HouseholdTable'

export default function HouseholdOpsPanels({ rows }) {
  return (
    <section className="triage-section">
      <div className="side-heading">
        <span>Purok triage</span>
        <span>{rows.length} areas</span>
      </div>
      {rows.length === 0 ? (
        <EmptyState title="No purok summary yet" message="Area counts will appear after household records have address or purok data." />
      ) : (
        <div className="triage-list">
          {rows.map((row) => (
            <article className={`triage-item ${row.priority === 'urgent' ? 'urgent' : ''}`} key={row.purok}>
              <div className="triage-title">
                <strong>{row.purok}</strong>
                <span className={`risk-pill ${row.priority || 'stable'}`}>{row.next_action || 'Monitor'}</span>
              </div>
              <div className="triage-stats">
                <span><b>{row.total || 0}</b>Total</span>
                <span><b>{row.reported || 0}</b>Reported</span>
                <span><b>{row.unchecked || 0}</b>Unchecked</span>
                <span><b>{row.unsafe || 0}</b>Unsafe</span>
                <span><b>{row.device_risk || 0}</b>Risk</span>
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}
