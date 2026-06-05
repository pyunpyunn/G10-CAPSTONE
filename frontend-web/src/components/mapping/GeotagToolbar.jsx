import { Crosshair, EyeOff, ShieldCheck, Smartphone } from 'lucide-react'

export default function GeotagToolbar() {
  return (
    <div className="geotag-toolbar">
      <span className="geotag-chip"><ShieldCheck size={14} /> GPS verification active</span>
      <span className="geotag-chip"><Smartphone size={14} /> Household mobile submissions</span>
      <span className="geotag-chip"><Crosshair size={14} /> Accuracy threshold visible</span>
      <span className="geotag-chip muted"><EyeOff size={14} /> Records without coordinates hidden</span>
    </div>
  )
}
