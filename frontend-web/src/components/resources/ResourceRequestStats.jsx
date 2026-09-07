export default function ResourceRequestStats({ summary = {} }) {
  const totalRequests = Array.isArray(summary.rows)
    ? summary.rows.reduce((total, row) => total + Number(row.count || 0), 0)
    : Number(summary.total || 0)

  const stats = [
    {
      label: 'Needs validation',
      value: summary.needs_validation ?? 0,
      note: 'awaiting HQ review',
    },
    {
      label: 'Verified',
      value: summary.verified ?? 0,
      note: 'approved requests',
    },
    {
      label: 'Forwarded today',
      value: summary.forwarded_today ?? 0,
      note: 'sent to TrackingAid',
    },
    {
      label: 'Total requests',
      value: totalRequests,
      note: 'shared request records',
    },
  ]

  return (
    <div className="rr-stat-row">
      {stats.map((stat) => (
        <div className="rr-stat" key={stat.label}>
          <div className="k">{stat.label}</div>
          <div className="v">{stat.value}</div>
          <div className="n">{stat.note}</div>
        </div>
      ))}
    </div>
  )
}