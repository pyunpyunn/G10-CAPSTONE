import { Radio } from 'lucide-react'

export default function BroadcastStartPanel({ activeEvent, onCompose }) {
  return (
    <div className="broadcast-start">
      <div className="broadcast-start-copy">
        <span>Broadcast center</span>
        <strong>{activeEvent ? 'Send an update for the active event' : 'Declare the disaster before monitoring starts'}</strong>
        <small>{activeEvent ? 'The event is already active. Save updates to the event log.' : 'No active event is displayed until HQ/Admin declares one.'}</small>
      </div>
      <button className="primary-button" type="button" onClick={onCompose}>
        <Radio size={16} />
        {activeEvent ? 'Compose Update Broadcast' : 'Declare Disaster Broadcast'}
      </button>
    </div>
  )
}
