export default function DispatchSummary({ summary }) {
  const totalAssignments = Number(summary.dispatch_progress?.total) || 0
  const metrics = [
    { label: 'Available', value: String(summary.available || 0), tone: 'neutral' },
    { label: 'Dispatched', value: String(summary.dispatched || 0), tone: 'purple' },
    { label: 'On-scene', value: String(summary.on_scene || 0), tone: 'safe' },
    { label: 'Response', value: `${summary.response_rate || 0}%`, tone: 'evacuated' },
  ]
  const progress = [
    { label: 'Dispatched', value: Number(summary.dispatch_progress?.dispatched) || 0, className: 'dispatched' },
    { label: 'On-scene', value: Number(summary.dispatch_progress?.on_scene) || 0, className: 'on-scene' },
    { label: 'Returning', value: Number(summary.dispatch_progress?.returning) || 0, className: 'standby' },
    { label: 'Completed', value: Number(summary.dispatch_progress?.completed) || 0, className: 'completed' },
    { label: 'Cancelled', value: Number(summary.dispatch_progress?.cancelled) || 0, className: 'standby' },
  ].map((item) => ({ ...item, percent: totalAssignments > 0 ? Math.round(item.value / totalAssignments * 100) : 0 }))

  return (
    <>
      <section className="summary-section dispatch-summary-section">
        <div className="summary-header-row">
          <span>Dispatch summary</span>
          <span className="summary-total-badge">{summary.total_teams || 0} teams</span>
        </div>
        <div className="summary-grid compact-summary-grid">
          {metrics.map((metric) => (
            <div className={`metric ${metric.tone}`} key={metric.label}>
              <span>{metric.label}</span>
              <strong>{metric.value}</strong>
            </div>
          ))}
        </div>
      </section>

      <section className="progress-section dispatch-progress-section">
        <div className="side-heading">
          <span>Dispatch progress</span>
          <span>{totalAssignments} assignments</span>
        </div>
        <div className="progress-track dispatch-progress-track" tabIndex={0} title={progress.map((item) => `${item.label}: ${item.value} (${item.percent}%)`).join("; ")} aria-label={`Dispatch assignment progress: ${progress.map((item) => `${item.label} ${item.value} (${item.percent}%)`).join(", ")}`}>
          {progress.map((item) => (
            <span key={item.label} className={`dispatch-progress-${item.className}`} style={{ width: `${totalAssignments > 0 ? item.value / totalAssignments * 100 : 0}%` }} title={`${item.label}: ${item.value} (${item.percent}%)`} />
          ))}
        </div>

      </section>
    </>
  )
}
