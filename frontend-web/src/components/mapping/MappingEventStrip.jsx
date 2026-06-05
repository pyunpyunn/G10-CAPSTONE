import { EyeOff, ShieldCheck } from 'lucide-react'
import Badge from '../ui/Badge'

export default function MappingEventStrip({ activeEvent }) {
  const hasActiveEvent = Boolean(activeEvent)

  return (
    <section className={`mapping-event-strip ${hasActiveEvent ? 'active' : 'standby'}`}>
      <div className="mapping-event-icon">
        {hasActiveEvent ? <ShieldCheck size={18} /> : <EyeOff size={18} />}
      </div>
      <div>
        <span>{hasActiveEvent ? 'Active event map layers' : 'Plain barangay map'}</span>
        <strong>
          {hasActiveEvent
            ? `${activeEvent.name} - ${activeEvent.type_name}`
            : 'Operational status colors are hidden until a disaster event is declared.'}
        </strong>
      </div>
      {hasActiveEvent && <Badge tone="red">{activeEvent.severity_label}</Badge>}
    </section>
  )
}
