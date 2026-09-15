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
        <div className="bc-current-details">
          <div className="bc-current-header">
            <span className="bc-label-kicker">Event Name</span>
            <strong className="bc-event-title">{activeEvent.name}</strong>
          </div>

          {/* Labeled Key-Value Details */}
          <div className="bc-grid-info">
            <div className="bc-info-item">
              <span className="bc-info-label">Event ID</span>
              <span className="bc-info-value">#{activeEvent.event_id || '-'}</span>
            </div>
            <div className="bc-info-item">
              <span className="bc-info-label">Type</span>
              <span className="bc-info-value">{activeEvent.type_name || '-'}</span>
            </div>
            <div className="bc-info-item">
              <span className="bc-info-label">Severity</span>
              <span className="bc-info-value">{activeEvent.severity_label || '-'}</span>
            </div>
            <div className="bc-info-item">
              <span className="bc-info-label">Declared</span>
              <span className="bc-info-value">{activeEvent.started_time || '-'}</span>
            </div>
            <div className="bc-info-item">
              <span className="bc-info-label">Duration</span>
              <span className="bc-info-value">{latestBroadcast?.estimated_duration || activeEvent.estimated_duration || 'Ongoing'}</span>
            </div>
            <div className="bc-info-item">
              <span className="bc-info-label">Last Updated</span>
              <span className="bc-info-value">{latestBroadcast?.sent_time || activeEvent.updated_at || '-'}</span>
            </div>
          </div>
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
              <div className="bc-log-content">
                <div className="bc-log-title">
                  <strong>{broadcast.broadcast_title}</strong>
                  <Badge tone={eventTone(broadcast.severity_key)}>{broadcast.severity_label}</Badge>
                </div>
                
                {/* Formal Message Display */}
                <div className="bc-log-message-body">
                  <span className="bc-info-label">Official Instruction</span>
                  <p>{broadcast.message}</p>
                </div>

                <div className="bc-log-meta">
                  <span><strong>Sent:</strong> {broadcast.sent_time || '-'}</span>
                  <span><strong>Target:</strong> {broadcast.scope_label || broadcast.target_area || 'All'}</span>
                  <span><strong>Recipients:</strong> {broadcast.recipient_count || 0}</span>
                  <span><strong>Status:</strong> {broadcast.status || 'Sent'}</span>
                </div>
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}