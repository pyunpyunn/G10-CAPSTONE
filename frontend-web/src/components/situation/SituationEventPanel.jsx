export default function SituationEventPanel({ events = [], selectedEventId, selectedEvent, onSelect, actions }) {
  return (
    <div className="sitrep-event-panel">
      <div className="sitrep-event-actions">{actions}</div>
      <div className="sitrep-event-select">
        <label htmlFor="sitrepEventSelect">Disaster event log</label>
        <select
          id="sitrepEventSelect"
          value={selectedEventId}
          onChange={(event) => onSelect(event.target.value)}
        >
          <option value="">Select disaster event...</option>
          {events.map((event) => (
            <option value={event.event_id} key={event.event_id}>{event.label}</option>
          ))}
        </select>
      </div>
      <div className="sitrep-event-context">
        <ContextItem label="Disaster type" value={selectedEvent?.type || 'Select an event'} />
        <ContextItem label="Date declared" value={selectedEvent?.declared_at || '-'} />
        <ContextItem label="Date finished" value={selectedEvent?.finished_at || '-'} />
      </div>
    </div>
  )
}

function ContextItem({ label, value }) {
  return (
    <div className="sitrep-context-item">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  )
}
