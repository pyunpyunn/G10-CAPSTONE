import { Archive, ShieldCheck, Siren } from 'lucide-react'
import Badge from '../ui/Badge'
import { eventTone } from '../../utils/broadcastHelpers'

export default function BroadcastLifecycleCard({ state, activeEvent, broadcastCount, onCloseEvent }) {
  const isActive = state === 'active'

  return (
    <section className={`broadcast-lifecycle state-${state}`}>
      <div className="bc-life-icon">
        {isActive ? <Siren size={19} /> : <ShieldCheck size={19} />}
      </div>
      <div className="bc-life-copy">
        <div className="bc-life-top">
          <span>{isActive ? 'Active disaster' : 'Monitoring standby'}</span>
          <Badge tone={isActive ? eventTone(activeEvent?.severity_key) : 'gray'}>
            {isActive ? activeEvent.severity_label : 'No active event'}
          </Badge>
        </div>
        <strong>{isActive ? activeEvent.name : 'No active event declared'}</strong>
        <div className="bc-life-meta">
          <span>{isActive ? activeEvent.type_name : 'Broadcast locked'}</span>
          <span>{isActive ? `Declared ${activeEvent.started_time || '-'}` : 'Household reporting closed'}</span>
          <span>{broadcastCount} log{broadcastCount === 1 ? '' : 's'}</span>
        </div>
      </div>
      {isActive && (
        <button className="btn btn-warning btn-sm" type="button" onClick={onCloseEvent}>
          <Archive size={14} />
          Close Active Event
        </button>
      )}
    </section>
  )
}
