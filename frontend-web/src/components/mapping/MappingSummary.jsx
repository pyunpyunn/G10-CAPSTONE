import { Building2, CheckCircle2, Crosshair, LocateFixed } from 'lucide-react'

export default function MappingSummary({ summary, hasActiveEvent }) {
  const gpsReadyText = summary.average_accuracy_m === null
    ? 'No accuracy saved'
    : `${summary.average_accuracy_m} m average`

  return (
    <div className="map-summary-grid">
      <SummaryCard icon={LocateFixed} label="GPS-tagged households" value={summary.gps_tagged_households} detail="Saved household geotags" tone="green" />
      <SummaryCard icon={Crosshair} label="No verified geotag" value={summary.no_verified_geotag} detail="Hidden from map markers" tone="gray" />
      <SummaryCard icon={CheckCircle2} label="Average GPS accuracy" value={gpsReadyText} detail="From household mobile setup" tone="blue" />
      <SummaryCard icon={Building2} label="Pinned evacuation sites" value={summary.evacuation_sites} detail={hasActiveEvent ? 'Visible during event' : 'Hidden until event'} tone="amber" />
    </div>
  )
}

function SummaryCard({ icon: Icon, label, value, detail, tone }) {
  return (
    <article className={`map-summary-card ${tone}`}>
      <span className="map-summary-icon"><Icon size={18} /></span>
      <div>
        <span className="map-summary-label">{label}</span>
        <strong>{value}</strong>
        <span>{detail}</span>
      </div>
    </article>
  )
}
