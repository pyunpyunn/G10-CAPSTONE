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
        {isActive && <ActiveEventDetails activeEvent={activeEvent} />}
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

function ActiveEventDetails({ activeEvent }) {
  const weather = activeEvent.latest_weather || {}

  return (
    <div className="bc-event-details">
      <EventDetail label="Event ID" value={activeEvent.event_id} />
      <EventDetail label="Duration" value={activeEvent.duration_label || 'Active'} />
      <EventDetail label="Status buttons" value={`${activeEvent.status_sent_count || 0}/4 sent`} />
      <EventDetail label="Weather" value={weatherLabel(weather)} />
    </div>
  )
}

function EventDetail({ label, value }) {
  return (
    <div className="bc-event-detail">
      <span>{label}</span>
      <strong>{value || '-'}</strong>
    </div>
  )
}

function weatherLabel(weather) {
  const parts = [weather.condition || 'No weather snapshot']

  if (weather.temperature !== null && weather.temperature !== undefined) {
    parts.push(`${weather.temperature} C`)
  }

  if (weather.wind_speed !== null && weather.wind_speed !== undefined) {
    parts.push(`${weather.wind_speed} km/h wind`)
  }

  return parts.join(' - ')
}
