import { Users, Activity, ChartColumn, HeartHandshake, Files, FileText, Sheet, Table2, Check, Download, Link2, Unplug, Send } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { generateEvaTrackReport, getExternalReportProviders } from '../../api/evaTrackReportApi'
import { statusColor } from '../../utils/statusPalette'

const typeIcons = {demographics:Users,dromic:Activity,utilization:ChartColumn,vulnerable:HeartHandshake,general:Files}
const descriptions = { demographics:'Households & people', dromic:'Disaster response', utilization:'Utilization', vulnerable:'Additional support', general:'Overview' }
const label = value => value === 'dromic' ? 'DROMIC' : value.charAt(0).toUpperCase() + value.slice(1)
export default function EvaTrackReportsPanel() {
  const [providers, setProviders] = useState([]), [provider, setProvider] = useState('evatrack')
  const configuration = providers.find(item => item.key === provider)
  const [type, setType] = useState(''), [format, setFormat] = useState('pdf')
  const [start, setStart] = useState(''), [end, setEnd] = useState(''), [busy, setBusy] = useState(false)
  const [error, setError] = useState(''), [report, setReport] = useState(null), [loading, setLoading] = useState(true)
  const [now, setNow] = useState(() => Date.now())
  useEffect(() => {
    let cancelled = false
    getExternalReportProviders().then(data => {
      if (!cancelled) { setProviders(data); setType(data.find(item => item.key === 'evatrack')?.report_types?.[0] || '') }
    }).catch(failure => { if (!cancelled) setError(failure.response?.data?.message || 'Cannot load EvaTrack connection settings.') })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [])
  useEffect(() => { if (!report) return; const timer = window.setInterval(() => setNow(Date.now()), 1000); return () => window.clearInterval(timer) }, [report])
  function field(setter, value) { setter(value); setReport(null); setError('') }
  async function generate(event) {
    event.preventDefault()
    if (busy || provider !== 'evatrack' || !configuration?.implemented || !configuration?.configured || !type) return
    setBusy(true); setError(''); setReport(null)
    try {
      const data = await generateEvaTrackReport({ report_type:type, format, ...(start ? {start_date:start} : {}), ...(end ? {end_date:end} : {}) })
      setReport(data); setNow(Date.now())
    } catch (failure) { const details = failure.response?.data; setError(details?.errors ? Object.values(details.errors).flat().join(' ') : details?.message || 'EvaTrack could not generate the report.') }
    finally { setBusy(false) }
  }
  const expired = report && (!Number.isFinite(Date.parse(report.expires_at)) || Date.parse(report.expires_at) <= now)
  return <section className="situation-reference external-report-reference" aria-label="External Reports"><div className="wrap">
    <div className="hd provider-toolbar"><label className="provider-selector">Source<select aria-label="Report source" value={provider} disabled={loading || busy} onChange={event => { const next = event.target.value; setProvider(next); setType(providers.find(item => item.key === next)?.report_types?.[0] || ''); setReport(null); setError('') }}>{providers.length ? providers.map(item => <option key={item.key} value={item.key}>{item.name}</option>) : <option value="evatrack">Loading…</option>}</select></label><Link className="bt" to="/situation">← Situation Reporting</Link><span className="sp" /><button form="external-report-request" className="bt p" type="submit" disabled={loading || busy || !configuration?.implemented || !configuration?.configured || !type}><Send size={16} />{busy ? 'Requesting…' : 'Request report'}</button></div>
    {error && <div className="notice error" role="alert">{error}</div>}
    <form id="external-report-request" className="cd external-form" onSubmit={generate}>
      {!loading && !configuration?.implemented ? <div className="external-unavailable"><Unplug size={32} /><b>{configuration?.name || 'External reports'}</b><span>Report integration unavailable</span></div> : <div className="external-fields"><fieldset disabled={busy || loading}><legend><span className="step-number">1</span>Report type</legend><div className="report-types">{configuration?.report_types?.map(value => { const Icon = typeIcons[value] || Files; return <button type="button" className={`bt ${type === value ? 'p' : ''}`} key={value} aria-pressed={type === value} onClick={() => field(setType, value)}><span className="type-icon"><Icon size={25} /></span><strong>{label(value)}</strong>{type === value && <Check size={17} className="type-check" />}<small>{descriptions[value] || 'EvaTrack'}</small></button>})}</div>{!loading && !configuration?.report_types?.length && <p className="mu">No records found</p>}</fieldset>
        <div className="external-options"><fieldset disabled={busy}><legend><span className="step-number">2</span>File format</legend><div className="format-tiles">{[['pdf','PDF','Print',FileText],['xlsx','Excel','Spreadsheet',Sheet],['csv','CSV','Data',Table2]].map(([value,title,hint,Icon]) => <button type="button" key={value} className={`format-tile ${format === value ? 'selected' : ''}`} aria-pressed={format === value} onClick={() => field(setFormat,value)}><Icon size={24} /><b>{title}</b><small>{hint}</small>{format === value && <Check size={13} className="format-check" />}</button>)}</div></fieldset><div className="date-caption">Date range <span>Optional · All dates if blank</span></div><label>From<input type="date" aria-label="Start date" value={start} disabled={busy} onChange={event => field(setStart,event.target.value)} /></label><label>To<input type="date" aria-label="End date" value={end} min={start || undefined} disabled={busy} onChange={event => field(setEnd,event.target.value)} /></label></div>
      </div>}<div className="report-footer"><span className="mu">{configuration?.configured ? <><Link2 size={15} />{label(type || 'general')}<span className="selection-divider">/</span>{format === 'xlsx' ? 'Excel' : format.toUpperCase()}</> : <><Unplug size={15} />Not connected</>}</span><span className="sp" /></div>
    </form>
    {report && <section className="cd external-result" aria-label="Generated report"><div className="rh"><b>{report.file_name}</b><span className="sp" /><span className="bg" style={{'--c':statusColor(expired ? 'unchecked' : 'available')}}>{expired ? 'Expired' : 'Generated'}</span></div><div className="external-download"><p>{expired ? 'Download link expired. Generate the report again.' : `Link expires: ${new Intl.DateTimeFormat('en-PH',{timeZone:'Asia/Manila',dateStyle:'medium',timeStyle:'short'}).format(new Date(report.expires_at))}`}</p>{!expired && <a className="bt p" href={report.download_url} target="_blank" rel="noopener noreferrer"><Download size={16} />Download prepared report</a>}</div></section>}
  </div></section>
}
