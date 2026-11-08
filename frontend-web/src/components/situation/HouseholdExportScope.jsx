import { useEffect, useState } from 'react'
import { getSituationSitios } from '../../api/situationReportApi'

export default function HouseholdExportScope({ value, onChange, disabled, external = false }) {
  const [sitios,setSitios] = useState(null), [error,setError] = useState(''), [retry,setRetry] = useState(0)
  useEffect(() => {
    let active = true
    getSituationSitios().then(rows => { if(active) { setSitios(rows); setError('') } }).catch(() => { if(active) setError('Could not load household locations.') })
    return () => { active = false }
  },[retry])
  const allPuroks = (sitios || []).flatMap(s => (s.puroks || []).map(p => ({ ...p, sitio_name: s.sitio_name })))
  const puroks = value.sitio_id === 'all'
    ? allPuroks
    : allPuroks.filter(p => String(p.sitio_id) === value.sitio_id)
  const fieldClass = external ? 'exchange-field' : 'fld'
  return <div className="household-export-scope"><label className="inc" style={{margin:'12px 0',display:'flex',gap:6}}><input type="checkbox" checked={value.include_households} disabled={disabled} onChange={event => onChange({...value,include_households:event.target.checked})} />Include household and member lists</label><div className="g2"><label className={fieldClass}>Sitio<select aria-label="Household sitio" value={value.sitio_id} disabled={disabled || !sitios} onChange={event => onChange({...value,sitio_id:event.target.value,purok_id:'all'})}><option value="all">All sitios</option>{(sitios || []).map(s => <option key={s.sitio_id} value={String(s.sitio_id)}>{s.sitio_name}</option>)}</select></label><label className={fieldClass}>Purok<select aria-label="Household purok" value={value.purok_id} disabled={disabled || !sitios} onChange={event => onChange({...value,purok_id:event.target.value})}><option value="all">All puroks</option>{puroks.map(p => <option key={p.purok_id} value={String(p.purok_id)}>{value.sitio_id === 'all' ? `${p.purok_name} (${p.sitio_name})` : p.purok_name}</option>)}</select></label></div>{error && <p role="alert">{error} <button type="button" onClick={() => setRetry(n => n+1)}>Retry</button></p>}</div>
}
