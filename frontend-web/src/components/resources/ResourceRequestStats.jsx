export default function ResourceRequestStats({ summary = {}, period = 'week', onPeriodChange }) {

  const stats = [
    {
      label: 'Needs validation',
      value: summary.needs_validation ?? 0,
      note: 'awaiting HQ review',
    },
    {
      label: 'Validated and Forwarded',
      value: summary.validated_and_forwarded ?? 0,
      note: 'ready or sent to TrackingAid',
    },
    {
      label: 'Acknowledged',
      value: summary.acknowledged ?? 0,
      note: 'confirmed by TrackingAid',
    },
    {
      label: 'Total requests',
      value: summary.total_requests ?? 0,
      note: `created ${periodLabel(period)}`,
    },
  ]

  return (
    <div className="rr-stat-stack">
      <label className="rr-period-filter">
        <span>Total request period</span>
        <select value={period} onChange={(event) => onPeriodChange(event.target.value)}>
          <option value="week">This week</option>
          <option value="month">This month</option>
          <option value="year">This year</option>
        </select>
      </label>
      <div className="rr-stat-row">
      {stats.map((stat) => (
        <div className="rr-stat" key={stat.label}>
          <div className="k">{stat.label}</div>
          <div className="v">{stat.value}</div>
          <div className="n">{stat.note}</div>
        </div>
      ))}
      </div>
    </div>
  )
}

function periodLabel(period) {
  return { week: 'this week', month: 'this month', year: 'this year' }[period] || 'this week'
}