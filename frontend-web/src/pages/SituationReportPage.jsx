import { useEffect, useRef, useState } from 'react'
import { createSituationReport, exportSituationReport, getSituationReport, getSituationSummary, getSituationWorkspace } from '../api/situationReportApi'
import HouseholdExportScope from '../components/situation/HouseholdExportScope'
import SituationReferenceSections from '../components/situation/SituationReferenceSections'
import { reportSections } from '../utils/situationSections'
import { buildGeneratePayload, displayValue, emptyGenerateForm, situationErrorMessage } from '../utils/situationReportHelpers'
import '../styles/situation-html.css'

const sectionKeys = reportSections.map(([key]) => key)
const defaultCollapsed = sectionKeys.filter(key => key !== 'I')
function draftForm(summary) {
  const form = emptyGenerateForm(summary)
  return { ...form, report_number: '', included_sections: summary?.included_sections?.length ? summary.included_sections.filter(key => sectionKeys.includes(key)) : sectionKeys }
}
export default function SituationReportPage() {
  const [workspace, setWorkspace] = useState(null), [summary, setSummary] = useState(null)
  const [selectedEventId, setSelectedEventId] = useState(''), [savedReport, setSavedReport] = useState(null)
  const [form, setForm] = useState(emptyGenerateForm()), [collapsed, setCollapsed] = useState(defaultCollapsed)
  const [loading, setLoading] = useState(true), [busy, setBusy] = useState(false), [exporting, setExporting] = useState(false)
  const [error, setError] = useState(''), [message, setMessage] = useState('')
  const [exportFormat, setExportFormat] = useState('pdf')
  const [reportSearch, setReportSearch] = useState('')
  const [householdScope,setHouseholdScope] = useState({sitio_id:'all',purok_id:'all',include_households:true})
  const reportDetails = useRef(null)
  const requestVersion = useRef(0)
  useEffect(() => {
    let cancelled = false
    async function load() {
      try {
        const data = await getSituationWorkspace()
        if (cancelled) return
        setWorkspace(data)
        const event = data.events?.find(item => item.status === 'Active') || data.events?.[0]
        if (event) {
          setSelectedEventId(event.event_id)
          const result = await getSituationSummary(event.event_id)
          if (!cancelled) { setSummary(result.summary); setForm(draftForm(result.summary)) }
        }
      } catch (failure) { if (!cancelled) setError(situationErrorMessage(failure, 'Situation Reporting cannot be loaded right now. Please check the backend or database connection.')) }
      finally { if (!cancelled) setLoading(false) }
    }
    load()
    return () => { cancelled = true }
  }, [])
  async function selectEvent(eventId) {
    const version = ++requestVersion.current
    setHouseholdScope({sitio_id:'all',purok_id:'all',include_households:true})
    setSelectedEventId(eventId); setSavedReport(null); setSummary(null); setLoading(true); setError(''); setMessage('')
    try {
      const data = await getSituationSummary(eventId)
      if (version === requestVersion.current) { setSummary(data.summary); setForm(draftForm(data.summary)); setCollapsed(defaultCollapsed) }
    } catch (failure) { if (version === requestVersion.current) setError(situationErrorMessage(failure, 'Selected event summary cannot be loaded right now.')) }
    finally { if (version === requestVersion.current) setLoading(false) }
  }
  async function openReport(report) {
    const version = ++requestVersion.current
    setLoading(true); setSummary(null); setError(''); setMessage('')
    try {
      const data = await getSituationReport(report.sit_rep_id)
      if (version !== requestVersion.current) return
      setHouseholdScope({sitio_id:String(data.report.summary.household_scope?.sitio_id ?? 'all'),purok_id:String(data.report.summary.household_scope?.purok_id ?? 'all'),include_households:data.report.summary.include_households ?? true})
      setSelectedEventId(data.report.event_id); setSavedReport(data.report); setSummary(data.report.summary)
      setForm(emptyGenerateForm(data.report.summary)); setCollapsed(defaultCollapsed)
      setMessage(`${data.report.report_number} loaded from saved snapshots.`)
    } catch (failure) { if (version === requestVersion.current) setError(situationErrorMessage(failure, 'Saved SitRep cannot be loaded right now.')) }
    finally { if (version === requestVersion.current) setLoading(false) }
  }
  useEffect(() => {
    if (!selectedEventId || savedReport || loading || busy || exporting) return
    let cancelled = false, running = false
    const version = requestVersion.current
    async function update(event) {
      if (running || document.hidden) return
      const topics = event?.detail?.topics
      if (topics && !topics.some(topic => ['weather','households','dispatch','disasters','requests'].includes(topic))) return
      running = true
      try {
        const data = await getSituationSummary(selectedEventId)
        if (!cancelled && version === requestVersion.current) setSummary(current => current ? { ...data.summary, report:current.report } : current)
      } catch { /* Retain the last real summary during temporary connection loss. */ }
      finally { running = false }
    }
    const timer = window.setInterval(update, 60000)
    window.addEventListener('operations:changed', update)
    window.addEventListener('focus', update)
    return () => { cancelled = true; window.clearInterval(timer); window.removeEventListener('operations:changed', update); window.removeEventListener('focus', update) }
  }, [selectedEventId, savedReport, loading, busy, exporting])
  function setField(key, value) { setForm(current => ({ ...current, [key]: value })) }
  function toggle(number) { setForm(current => ({ ...current, included_sections: current.included_sections.includes(number) ? current.included_sections.filter(key => key !== number) : [...current.included_sections, number] })) }
  function validateSignatories() {
    if (savedReport) return true
    const missing = [['prepared_by', 'Prepared by'], ['reviewed_by', 'Reviewed by']].filter(([key]) => !form[key]?.trim())
    if (!missing.length) return true
    setError('Prepared by and reviewed by are required.')
    reportDetails.current?.querySelector('input')?.focus()
    return false
  }
  async function generate(event) {
    event.preventDefault()
    if (busy || savedReport || !summary) return
    if (!validateSignatories() || !event.currentTarget.reportValidity()) return
    if (!form.included_sections.length) { setError('Include at least one section.'); return }
    setBusy(true); setError(''); setMessage('')
    try {
      const data = await createSituationReport({...buildGeneratePayload(selectedEventId, form),...householdScope})
      setSavedReport(data.report); setSummary(data.report.summary); setForm(emptyGenerateForm(data.report.summary))
      setMessage(`${data.report.report_number} generated and locked.`)
      try { setWorkspace(await getSituationWorkspace()) } catch { /* Retain the successfully saved report. */ }
    } catch (failure) { setError(situationErrorMessage(failure)) }
    finally { setBusy(false) }
  }
  async function exportReport(format) {
    if (!summary || exporting) return
    if (!validateSignatories()) return
    if (!form.included_sections.length) { setError('Include at least one section.'); return }
    setExporting(true); setError(''); setMessage('Generating report. Large exports may take a moment.')
    try {
      await exportSituationReport({ ...buildGeneratePayload(selectedEventId, form), format, ...householdScope, sit_rep_id: savedReport?.sit_rep_id })
      setMessage('SitRep export generated.')
    } catch (failure) { setError(situationErrorMessage(failure, 'SitRep export cannot be generated.')); setMessage('') }
    finally { setExporting(false) }
  }
  const events = workspace?.events || [], allReports = workspace?.reports || []
  const reports = allReports.filter(report => `${report.report_number} ${report.event_name} ${report.generated_at}`.toLowerCase().includes(reportSearch.trim().toLowerCase()))
  const scopedSummary = summary ? {...summary,household:{...summary.household,details:(summary.household?.details || []).filter(h => (householdScope.sitio_id === 'all' || String(h.sitio_id) === householdScope.sitio_id) && (householdScope.purok_id === 'all' || String(h.purok_id) === householdScope.purok_id))}} : null
  const selected = summary?.event || events.find(item => item.event_id === selectedEventId)
  const locked = Boolean(savedReport), disabled = loading || busy || exporting
  return <section className="situation-html" aria-label="Situation Reporting">
    <div className="ctx"><div><label htmlFor="sitrep-event">Disaster event log</label><select id="sitrep-event" value={selectedEventId} disabled={disabled || !events.length} onChange={event => selectEvent(event.target.value)}>{!events.length && <option value="">No disaster events</option>}{events.map(event => <option key={event.event_id} value={event.event_id}>{event.name} — {event.status} event</option>)}</select></div>
      {[['Type',selected?.type],['Declared',selected?.declared_at],['Finished',selected?.finished_at === 'Ongoing' ? <Badge tone="amber">Ongoing</Badge> : selected?.finished_at],['Alert level',selected?.severity],['Area coverage',summary ? `${summary.household?.sitios?.length || 0} sitios` : null],['Situation',selected && <Badge tone={selected.status === 'Closed' ? 'gray' : 'amber'}>{selected.status}</Badge>]].map(([label,value]) => <div key={label}><span className="l">{label}</span><div className="v">{displayValue(value)}</div></div>)}
    </div>
    {error && <div className="notice err" role="alert">{error}</div>}
    {message && <div className="notice note" role="status">{message}</div>}
    <form className="wrap" noValidate onSubmit={generate}>
      <div className="paper"><div className="ph"><div><h1>Situation Report{selected?.type ? ` — ${selected.type}` : ''}</h1><p>{locked ? `SitRep ${savedReport.report_number} · saved snapshot` : 'Draft · SitRep number assigned on save'}</p></div><span><Badge tone={locked ? 'purple' : 'amber'}>{locked ? 'Locked — read-only' : 'Draft — editable'}</Badge></span></div>
        {loading ? <div className="body"><div className="empty" role="status">Loading event summary…</div></div> : summary ? <SituationReferenceSections summary={scopedSummary} included={form.included_sections} collapsed={collapsed} onCollapse={number => setCollapsed(current => current.includes(number) ? current.filter(key => key !== number) : [...current, number])} onToggle={toggle} actions={form.actions_text} onActions={value => setField('actions_text', value)} locked={locked || disabled} /> : <div className="body"><div className="empty">{error ? 'Event summary unavailable.' : 'Select a disaster event first.'}</div></div>}
        <div className="foot"><span>Saved copies cannot be edited.</span>{locked ? <button type="button" className="btn p" disabled={disabled} onClick={() => selectEvent(selectedEventId)}>Create a new report</button> : <button type="submit" className="btn p" disabled={disabled || !summary}>{busy ? 'Saving…' : 'Save & lock report'}</button>}</div>
      </div>
      <aside className="rail"><div className="card"><h3>Export</h3><div className="g2"><select aria-label="Format" value={exportFormat} disabled={disabled} onChange={event => setExportFormat(event.target.value)}><option value="pdf">PDF</option><option value="excel">Excel</option><option value="csv">CSV</option></select><button type="button" className="btn p" disabled={disabled || !summary} onClick={() => exportReport(exportFormat)}>{exporting ? 'Working…' : 'Download'}</button></div><HouseholdExportScope value={householdScope} onChange={setHouseholdScope} disabled={disabled} /></div>
        <div className="card" ref={reportDetails}><h3>Report details</h3>{[['prepared_by','Prepared by'],['reviewed_by','Reviewed by'],['period_start','Period start'],['period_end','Period end']].map(([key,label]) => <div className="fld" key={key}><label htmlFor={`sitrep-${key}`}>{label} {key.endsWith('_by') && <em>*</em>}</label><input className="in" id={`sitrep-${key}`} aria-label={label} type={key.startsWith('period_') ? 'datetime-local' : 'text'} required={key.endsWith('_by')} maxLength={key.endsWith('_by') ? 150 : undefined} min={key === 'period_end' ? form.period_start : undefined} value={form[key]} disabled={disabled} readOnly={locked} onChange={event => setField(key,event.target.value)} /></div>)}</div>
        <div className="card"><h3>Sections · {form.included_sections.length}/7 included</h3><div className="prog"><div style={{width:`${form.included_sections.length/7*100}%`}} /></div><ul className="cl">{reportSections.map(([number,title]) => <li key={number}><span className="r">{number}</span><button type="button" onClick={() => { setCollapsed(current => current.filter(key => key !== number)); requestAnimationFrame(() => document.getElementById(`sitrep-section-${number}`)?.scrollIntoView({behavior:'smooth',block:'start'})) }}>{title}</button><input type="checkbox" aria-label={`Include ${title} in export`} checked={form.included_sections.includes(number)} disabled={locked || disabled} onChange={() => toggle(number)} /></li>)}</ul><div className="g2" style={{marginTop:10}}><button type="button" className="btn" onClick={() => setCollapsed([])}>Expand all</button><button type="button" className="btn" onClick={() => setCollapsed(sectionKeys)}>Collapse all</button>{!locked && <button type="button" className="btn" style={{gridColumn:'span 2'}} disabled={disabled} onClick={() => setField('included_sections',sectionKeys)}>Include all</button>}</div><p style={{margin:'8px 0 0',color:'var(--mu)'}}>Unchecked sections are left out of the export. Collapsing only hides them here.</p></div>
      </aside>
    </form>
    <div className="sv"><div className="paper"><h2 style={{fontSize:14,margin:'14px 0 10px'}}>Saved situation reports ({allReports.length})</h2><div style={{maxWidth:420,marginBottom:10}}><label className="l" htmlFor="saved-search">Search</label><input id="saved-search" className="in" placeholder="Report number, event or date" value={reportSearch} onChange={event => setReportSearch(event.target.value)} /></div><div className="tw" style={{marginTop:0}}><table><thead><tr><th>SitRep no.</th><th>Event</th><th>Generated</th><th>Status</th><th /></tr></thead><tbody>{reports.length ? reports.map(report => <tr key={report.sit_rep_id}><td><b>{report.report_number}</b></td><td>{report.event_name}</td><td>{report.generated_at}</td><td><Badge tone="purple">Locked</Badge></td><td className="n"><button type="button" className="btn" disabled={disabled} onClick={() => openReport(report)}>Open report</button></td></tr>) : <tr><td colSpan={5}><div className="empty" style={{border:0}}>{reportSearch ? 'No saved reports match your search.' : 'No saved reports yet. Save & lock a draft to create one.'}</div></td></tr>}</tbody></table></div></div></div>
  </section>
}
function Badge({tone,children}) { return <span className="chip"><i style={{background:`var(--${tone})`}} />{children}</span> }
