import { Info } from 'lucide-react'

export default function ResourceRequestNotice({ note }) {
  return (
    <div className="rr-note" role="note">
      <span>
        <Info size={15} aria-hidden="true" />
        {note || 'Review requests from EvaTrack, field teams, evacuation sites, and the HQ desk before forwarding them to TrackingAid.'}
      </span>
    </div>
  )
}