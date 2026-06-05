import { ShieldCheck } from 'lucide-react'

export default function ResourceRequestNotice({ note }) {
  return (
    <div className="rr-note">
      <span>
        <ShieldCheck size={16} />
        {note || 'RESQPERATION validates requests only. TrackingAid owns release and delivery after handoff.'}
      </span>
      <strong>Shared DB - no duplicate system log</strong>
    </div>
  )
}
