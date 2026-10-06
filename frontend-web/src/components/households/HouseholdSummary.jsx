import {
  makeProgress,
} from '../../utils/householdStatusHelpers'

export default function HouseholdSummary({ summary }) {
  return (
    <>
      <HouseholdSummaryMetrics summary={summary} />
      <HouseholdProgress summary={summary} />
    </>
  )
}

export function HouseholdSummaryMetrics({ summary }) {
  return (
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
  )
}

export function HouseholdProgress({ summary }) {
  const progress = makeProgress(summary)
  return (
      <section className="progress-section">
        <div className="side-heading">
          <span>Progress</span>
          <span>{summary.reporting_percent || 0}% reported</span>
        </div>
        <div className="progress-wrap">
          <div className="progress-track" tabIndex={0} aria-label={`Household status progress: ${progress.map((item) => `${item.label} ${item.percent}%`).join(', ')}`}>
            {progress.map((item) => (
              <span key={item.label} className={`progress-${item.className}`} style={{ width: `${item.width}%` }} title={`${item.label}: ${item.count} households (${item.percent}%)`} />
            ))}
          </div>
          <div className="progress-tooltip" role="tooltip">
            {progress.map((item) => <span key={item.label}>{item.label}: <strong>{item.count} ({item.percent}%)</strong></span>)}
          </div>
        </div>
      </section>
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
