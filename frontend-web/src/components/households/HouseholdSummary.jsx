import {
  makeProgress,
} from '../../utils/householdStatusHelpers'

export default function HouseholdSummary({ summary }) {
  const progress = makeProgress(summary)

  return (
    <>
      <section className="summary-section">
        <div className="summary-header-row">
          <span>Household summary</span>
          <span className="summary-total-badge">{summary.total || 0} total</span>
        </div>
        <div className="summary-grid compact-summary-grid">
          <Metric label="Unchecked" value={String(summary.unchecked || 0)} tone="neutral" />
          <Metric label="Safe" value={String(summary.safe_total || summary.safe_only || 0)} tone="safe" />
          <Metric label="Evacuated" value={String(summary.evacuated || 0)} tone="evacuated" />
          <Metric label="Unsafe" value={String(summary.unsafe || 0)} tone="unsafe" />
        </div>
      </section>

      <section className="progress-section">
        <div className="side-heading">
          <span>Progress</span>
          <span>{summary.reported || 0}% reported</span>
        </div>
        <div className="progress-track" aria-label="Household status progress">
          {progress.map((item) => (
            <span key={item.label} className={`progress-${item.className}`} style={{ width: `${item.percent}%` }} />
          ))}
        </div>
        <div className="progress-legend">
          {progress.map((item) => (
            <span key={item.label}><i className={`${item.className}-dot`} />{item.label} {item.percent}%</span>
          ))}
        </div>
      </section>
    </>
  )
}

function Metric({ label, value, tone }) {
  return (
    <div className={`metric ${tone}`}>
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  )
}
