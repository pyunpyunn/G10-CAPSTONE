import { Clock, Radio, Siren } from 'lucide-react'
import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'
import { eventTone } from '../../utils/broadcastHelpers'

export default function BroadcastSidePanel({ activeEvent, broadcasts }) {
  return (
    <aside className="broadcast-side-panel">
      <CurrentAlertCard activeEvent={activeEvent} latestBroadcast={broadcasts[0]} />
      <BroadcastLog broadcasts={broadcasts} />
    </aside>
  )
}

function CurrentAlertCard({ activeEvent, latestBroadcast }) {
  return (
    <section className="broadcast-card">
      <div className="broadcast-card-head">
        <span><Siren size={15} /> Current disaster alert update</span>
        <Badge tone={activeEvent ? eventTone(activeEvent.severity_key) : 'gray'}>
          {activeEvent ? 'Active' : 'Standby'}
        </Badge>
      </div>
      {activeEvent ? (
        <div className="bc-current">
          <strong>{activeEvent.name}</strong>
          <div className="bc-current-meta">
            <span>{activeEvent.type_name}</span>
            <span>{activeEvent.severity_label}</span>
            <span>{activeEvent.started_time || '-'}</span>
          </div>
          <p>{latestBroadcast?.message || 'No saved broadcast update yet for this event.'}</p>
        </div>
      ) : (
        <EmptyState title="No active alert" message="This panel opens after HQ/Admin declares the disaster broadcast." />
      )}
    </section>
  )
}

function BroadcastLog({ broadcasts }) {
  return (
    <section className="broadcast-card">
      <div className="broadcast-card-head">
        <span><Clock size={15} /> Broadcast log</span>
        <Badge tone="gray">{broadcasts.length}</Badge>
      </div>
      {broadcasts.length === 0 ? (
        <EmptyState title="No broadcast log yet" message="Saved broadcasts will appear here for the active disaster event." />
      ) : (
        <div className="bc-log-list">
          {broadcasts.map((broadcast) => (
            <article className="bc-log-item" key={broadcast.broadcast_id}>
              <div className="bc-log-icon"><Radio size={14} /></div>
              <div>
                <div className="bc-log-title">
                  <strong>{broadcast.broadcast_title}</strong>
                  <Badge tone={eventTone(broadcast.severity_key)}>{broadcast.severity_label}</Badge>
                </div>
                <p>{broadcast.message}</p>
                <div className="bc-log-meta">
                  <span>{broadcast.sent_time || '-'}</span>
                  <span>{broadcast.scope_label}</span>
                  <span>{broadcast.recipient_count} recipients</span>
                  <span>{broadcast.status}</span>
                </div>
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}
