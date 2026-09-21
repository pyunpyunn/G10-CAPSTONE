export default function DispatchSummary({ summary }) {
  const totalTeams = Number(summary.total_teams) || 0
  const metrics = [
    { label: 'Stand-by', value: String(summary.standby || 0), tone: 'neutral' },
    { label: 'Dispatched', value: String(summary.dispatched || 0), tone: 'purple' },
    { label: 'On-scene', value: String(summary.on_scene || 0), tone: 'safe' },
    { label: 'Response', value: `${summary.response_rate || 0}%`, tone: 'evacuated' },
  ]
  const progress = [
    { label: 'Stand-by', value: Number(summary.standby) || 0, className: 'standby' },
    { label: 'Dispatched', value: Number(summary.dispatched) || 0, className: 'dispatched' },
    { label: 'On-scene', value: Number(summary.on_scene) || 0, className: 'on-scene' },
    { label: 'Completed', value: Number(summary.completed) || 0, className: 'completed' },
  ].map((item) => ({
    ...item,
    percent: totalTeams > 0 ? Math.min(100, Math.round((item.value / totalTeams) * 100)) : 0,
  }))

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
          <span>{summary.response_rate || 0}% active</span>
        </div>
        <div className="progress-track dispatch-progress-track" aria-label="Dispatch team progress">
          {progress.map((item) => (
            <span key={item.label} className={`dispatch-progress-${item.className}`} style={{ width: `${item.percent}%` }} />
          ))}
        </div>
        <div className="progress-legend dispatch-progress-legend">
          {progress.map((item) => (
            <span key={item.label}>
              <i className={`dispatch-${item.className}-dot`} />
              {item.label} {item.percent}%
            </span>
          ))}
        </div>
      </section>
    </>
  )
}
