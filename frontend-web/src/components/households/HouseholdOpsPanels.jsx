import EmptyState from '../ui/EmptyState'

export default function HouseholdOpsPanels({ rows }) {
  return (
    <section className="triage-section">
      <div className="side-heading">
        <span>Purok triage</span>
        <span>{rows.length} areas</span>
      </div>
      {rows.length === 0 ? (
        <EmptyState title="No Purok priorities yet" message="Priorities appear when an active event has households with recorded Purok addresses." />
      ) : (
        <div className="triage-list">
          {rows.map((row) => (
            <article className={`triage-item ${row.band?.key === 'first' || row.band?.key === 'high' ? 'urgent' : ''}`} key={row.purok}>
              <div className="triage-title">
                <strong>{row.rank}. {row.purok}</strong>
                <span className="triage-priority-percent">{Number(row.priority_score).toFixed(1)}% total priority</span>
              </div>
              <div className="triage-stats">
                <span><b>{row.households}</b>Households</span>
                <span><b>{row.impacted_households}</b>Impacted</span>
                <span><b>{row.unreported_members}</b>Unreported</span>
                <span><b>{row.no_contact_households}</b>No contact</span>
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}
