import { ShieldCheck } from 'lucide-react'

export default function ResourceRequestNotice({ note }) {
  return (
    <div className="rr-note">
      <span>
        <ShieldCheck size={16} />
        {note || 'Validate before TrackingAid handoff.'}
      </span>
      <strong>Shared DB</strong>
    </div>
  )
}
