export default function DispatchSummary({ summary }) {
  const cards = [
    { label: 'Total teams', value: summary.total_teams, sub: 'RA 10121 BDRRMC' },
    { label: 'Dispatched', value: summary.dispatched, sub: 'En route to area', tone: 'c-purple' },
    { label: 'On-scene', value: summary.on_scene, sub: 'Actively working', tone: 'c-green' },
    { label: 'Stand-by', value: summary.standby, sub: 'Ready at base' },
    { label: 'Response rate', value: `${summary.response_rate || 0}%`, sub: `${summary.active_units || 0} active units`, tone: 'c-blue' },
  ]

  return (
    <div className="dp-summary-grid">
      {cards.map((card) => (
        <div className={`dp-sum ${card.tone || ''}`} key={card.label}>
          <div className="dl">{card.label}</div>
          <div className="dv">{card.value || 0}</div>
          <div className="ds">{card.sub}</div>
        </div>
      ))}
    </div>
  )
}
