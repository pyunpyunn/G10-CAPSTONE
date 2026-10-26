import PlanningModal from '../ui/PlanningModal'
import { useEffect, useState } from 'react'
import { getOfficialWeatherAdvisories } from '../../api/weatherApi'
import { useModuleData } from '../../utils/useModuleData'
import './official-weather-outlook.css'

const load = () => getOfficialWeatherAdvisories()
const stamp = value => value ? new Intl.DateTimeFormat('en-PH', { timeZone:'Asia/Manila', month:'short', day:'numeric', hour:'numeric', minute:'2-digit' }).format(new Date(value)) : 'Not yet synced'
export default function OfficialWeatherOutlook() {
  const [open, setOpen] = useState(false)
  const { data, error, loading, refresh } = useModuleData(load, ['weather'], { keepPreviousData:true })
  useEffect(() => {
    const timer = window.setInterval(() => { if (!document.hidden) refresh() }, 600000)
    return () => window.clearInterval(timer)
  }, [refresh])
  const alerts = [...(data?.alerts || [])].sort((a,b) => Number(b.mentions_cebu)-Number(a.mentions_cebu) || b.issued_at.localeCompare(a.issued_at))
  const unavailable = error || !data || data.status === 'unavailable'
  const status = loading ? 'Syncing…' : unavailable ? 'Source unavailable' : data.status === 'stale' ? 'Saved advisories · sync delayed' : data.status === 'partial' ? 'Some bulletins unavailable' : 'Synced with PAGASA'
  return <>
    <button type="button" className="outlook-launcher" aria-haspopup="dialog" aria-expanded={open} onClick={() => setOpen(true)}><span><b>Developing weather & official warnings</b><small>DOST-PAGASA · {loading ? 'Syncing…' : `${alerts.length} advisories`} · {status}</small></span><span>View warnings ↗</span></button>
    <PlanningModal title="Official weather hazard outlook" isOpen={open} onClose={() => setOpen(false)}>
    <section className="official-weather-outlook" aria-label="Official weather hazard outlook">
    <header><div><h2>Developing weather & official warnings</h2><p>DOST-PAGASA · LPA, tropical cyclones, rainfall and flood advisories</p></div><button type="button" disabled={loading} onClick={refresh}>Check updates</button></header>
    <div className="outlook-sync" role="status"><span>{status}</span><span>Last sync: {stamp(data?.synced_at)} PHT · Auto-check every 10 min</span></div>
    <p className="outlook-scope">Philippines-wide watch. Affected areas are named by PAGASA; a national advisory does not mean Mambaling will be hit.</p>
    {loading && !data ? <p role="status">Fetching official bulletins…</p> : unavailable && !alerts.length ? <p role="alert">Official updates could not be reached. Check PAGASA directly; this does not indicate that there are no hazards.</p> : !alerts.length ? <p>{data.status === 'live' ? 'No unexpired advisories were returned by the connected PAGASA feed.' : 'No unexpired advisories are available in this saved or incomplete feed.'}</p> : <div className="outlook-alerts">{alerts.map(alert => <article key={`${alert.id}-${alert.areas}`}>
      <div className="outlook-tags"><span>{alert.category}</span>{alert.urgency === 'Future' && <span>Forecast / prepare</span>}<span>{alert.mentions_cebu ? 'Cebu named in affected areas' : 'National watch'}</span></div>
      <h3>{alert.title}</h3><p><b>Affected areas:</b> {alert.areas}</p>
      <p className="outlook-summary">{alert.description.slice(0,260)}{alert.description.length > 260 ? '…' : ''}</p>
      <p className="outlook-times">Issued {stamp(alert.issued_at)} · Valid until {stamp(alert.expires_at)} PHT{alert.onset_at ? ` · Onset ${stamp(alert.onset_at)} PHT` : ''}</p>
      <details><summary>Forecast and official instructions</summary><div className="outlook-bulletin">{alert.description}{alert.instruction ? `\n\n${alert.instruction}` : ''}</div><p>Severity: {alert.severity} · Certainty: {alert.certainty}</p></details>
      <a href={alert.source_url} target="_blank" rel="noreferrer">Original PAGASA alert ↗</a>
    </article>)}</div>}
    <footer><a href="https://bagong.pagasa.dost.gov.ph/" target="_blank" rel="noreferrer">PAGASA weather updates ↗</a><a href="https://publicalert.pagasa.dost.gov.ph/feeds/" target="_blank" rel="noreferrer">Official alert feed ↗</a><span>Free public government feed · PAGASA, CC BY 4.0</span></footer>
  </section>
    </PlanningModal>
  </>
}
