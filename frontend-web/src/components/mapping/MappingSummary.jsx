export default function MappingSummary({ summary }) {
  const gpsReadyText = summary.average_accuracy_m === null
    ? 'No accuracy saved'
    : `${summary.average_accuracy_m} m average`

  return (
    <div className="mapmate-summary-grid">
      <SummaryCard label="GPS-tagged" value={summary.gps_tagged_households} tone="green" />
      <SummaryCard label="No geotag" value={summary.no_verified_geotag} tone="gray" />
      <SummaryCard label="GPS accuracy" value={gpsReadyText} tone="blue" />
      <SummaryCard label="Evacuation sites" value={summary.evacuation_sites} tone="amber" />
    </div>
  )
}

function SummaryCard({ label, value, tone }) {
  return (
    <article className={`mapmate-metric ${tone}`}>
      <div>
        <span className="mapmate-metric-label">{label}</span>
        <strong>{value}</strong>
      </div>
    </article>
  )
}
