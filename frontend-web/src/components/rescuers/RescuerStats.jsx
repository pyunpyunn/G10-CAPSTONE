const statItems = [
  { key: 'registered', label: 'Registered rescuers', sub: 'verified local roster' },
  { key: 'on_duty', label: 'On duty', sub: 'available or deployed', tone: 'green' },
  { key: 'deployed', label: 'Deployed', sub: 'active event', tone: 'purple' },
  { key: 'training_due', label: 'Training due', sub: 'refresh needed', tone: 'amber' },
]

export default function RescuerStats({ summary = {} }) {
  return (
    <div className="ra-stat-row">
      {statItems.map((item) => (
        <div className="ra-stat" key={item.key}>
          <div className="k">{item.label}</div>
          <div className={`v ${item.tone ? `ra-${item.tone}` : ''}`}>{summary[item.key] ?? 0}</div>
          <div className="n">{item.sub}</div>
        </div>
      ))}
    </div>
  )
}

