export default function ResourceRequestStats({ summary = {} }) {
  const cards = [
    {
      label: 'Needs validation',
      value: summary.needs_validation || 0,
      note: 'from shared DB + teams',
      color: '#4a2e08',
    },
    {
      label: 'Verified',
      value: summary.verified || 0,
      note: 'ready for handoff',
      color: '#0f3520',
    },
    {
      label: 'Forwarded today',
      value: summary.forwarded_today || 0,
      note: 'TrackingAid handoff',
      color: '#0e2548',
    },
    {
      label: 'Returned',
      value: summary.returned || 0,
      note: 'missing info or duplicate',
      color: '#5a1010',
    },
  ]

  return (
    <div className="rr-stat-row">
      {cards.map((card) => (
        <div className="rr-stat" key={card.label}>
          <div className="k">{card.label}</div>
          <div className="v" style={{ color: card.color }}>{card.value}</div>
          <div className="n">{card.note}</div>
        </div>
      ))}
    </div>
  )
}
