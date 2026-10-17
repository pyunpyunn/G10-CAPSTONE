import { Download, LockKeyhole, ClipboardCheck, ChevronDown, FolderOpen, CalendarDays, UserRound, ArrowRight, Check, ListFilter } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { createSituationReport, exportSituationReport, getSituationReport, getSituationSummary, getSituationWorkspace } from '../api/situationReportApi'
import ReportNotice from '../components/situation/ReportNotice'
import SituationReferenceSections from '../components/situation/SituationReferenceSections'
import { reportSections } from '../utils/situationSections'
import { buildGeneratePayload, displayValue, emptyGenerateForm, situationErrorMessage } from '../utils/situationReportHelpers'
import { statusColor } from '../utils/statusPalette'

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
  const [pickerOpen, setPickerOpen] = useState(false)
  const [exportFormat, setExportFormat] = useState('pdf')
  const [reportSearch, setReportSearch] = useState('')
  const [missingSignatories, setMissingSignatories] = useState([])
  const validationDialog = useRef(null), reportDetails = useRef(null)
  const requestVersion = useRef(0), picker = useRef(null), sectionOptions = useRef(null)
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
  useEffect(() => {
    const close = event => {
      if (!picker.current?.contains(event.target)) setPickerOpen(false)
      if (sectionOptions.current && !sectionOptions.current.contains(event.target)) sectionOptions.current.open = false
    }
    const escape = event => {
      if (event.key !== 'Escape') return
      setPickerOpen(false)
      if (sectionOptions.current?.open) {
        sectionOptions.current.open = false
        sectionOptions.current.querySelector('summary')?.focus()
      }
    }
    document.addEventListener('pointerdown', close); document.addEventListener('keydown', escape)
    return () => { document.removeEventListener('pointerdown', close); document.removeEventListener('keydown', escape) }
  }, [])
  async function selectEvent(eventId) {
    const version = ++requestVersion.current
    setPickerOpen(false); setSelectedEventId(eventId); setSavedReport(null); setSummary(null); setLoading(true); setError(''); setMessage('')
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
    setMissingSignatories(missing)
    reportDetails.current.open = true
    validationDialog.current.showModal()
    return false
  }
  function focusMissingSignatory() {
    const label = missingSignatories[0]?.[1]
    if (label) reportDetails.current?.querySelector(`input[aria-label="${label}"]`)?.focus()
  }
  async function generate(event) {
    event.preventDefault()
    if (busy || savedReport || !summary) return
    if (!validateSignatories() || !event.currentTarget.reportValidity()) return
    if (!form.included_sections.length) { setError('Include at least one section.'); return }
    setBusy(true); setError(''); setMessage('')
    try {
      const data = await createSituationReport(buildGeneratePayload(selectedEventId, form))
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
      await exportSituationReport({ ...buildGeneratePayload(selectedEventId, form), format, sit_rep_id: savedReport?.sit_rep_id })
      setMessage('SitRep export generated.')
    } catch (failure) { setError(situationErrorMessage(failure, 'SitRep export cannot be generated.')); setMessage('') }
    finally { setExporting(false) }
  }
  const events = workspace?.events || [], allReports = workspace?.reports || []
  const reports = allReports.filter(report => `${report.report_number} ${report.event_name} ${report.generated_at}`.toLowerCase().includes(reportSearch.trim().toLowerCase()))
  const selected = summary?.event || events.find(item => item.event_id === selectedEventId)
  const locked = Boolean(savedReport), disabled = loading || busy || exporting
  return <section className="situation-reference situation-workspace" aria-label="Situation Reporting"><div className="wrap"><div className="view">
    <div className="rh report-top">{summary && <div className="report-top-actions"><div className="report-identity"><b>{locked ? summary.report?.report_number : form.report_number || summary.report?.report_number}</b>{locked && <span className="bg" style={{ '--c':statusColor(savedReport.report_status) }}>{savedReport.report_status}</span>}</div><span className="sp" /><div className="report-export"><label className="named-field"><select aria-label="Download format" value={exportFormat} disabled={disabled} onChange={event => setExportFormat(event.target.value)}><option value="pdf">PDF</option><option value="excel">Excel</option><option value="csv">CSV</option></select></label><button type="button" className="bt p" disabled={disabled} onClick={() => exportReport(exportFormat)}><Download size={16} />{exporting ? 'Preparingâ€¦' : 'Download report'}</button></div></div>}<div className="hd"><div className="evp" ref={picker}><button type="button" className={`evb ${pickerOpen ? 'on' : ''}`} disabled={!events.length || disabled} onClick={() => setPickerOpen(!pickerOpen)} aria-expanded={pickerOpen} aria-controls="sitrep-event-options" aria-label="Disaster event log"><span className="event-picker-icon"><ListFilter size={21} /></span><span className="event-picker-label"><b>Disaster event log</b><span className="mu">{selected?.type || 'Choose an event'}</span></span><ChevronDown size={19} className="event-picker-chevron" aria-hidden="true" /></button>{pickerOpen && <div className="evl" id="sitrep-event-options"><div className="event-list-heading">Disaster events<span>{events.length}</span></div>{events.map(event => <button type="button" className={`evi ${event.event_id === selectedEventId ? 'on' : ''}`} key={event.event_id} onClick={() => selectEvent(event.event_id)}><span className="event-choice-icon" style={{ '--event-color':statusColor(event.status) }}>{event.event_id === selectedEventId ? <Check size={18} /> : <CalendarDays size={18} />}</span><div><b>{event.name}</b><span>{event.type} Â· {event.declared_at}</span></div><em className="event-status" style={{ '--c':statusColor(event.status) }}>{event.status}</em></button>)}</div>}</div>
      {selected && <div className="rng event-date-range"><CalendarDays size={19} aria-hidden="true" /><span><small>Date declared</small><b>{displayValue(selected.declared_at)}</b></span><ArrowRight size={17} aria-hidden="true" /><span><small>Date finished</small><b>{displayValue(selected.finished_at)}</b></span></div>}<span className="sp" />
    </div></div>
    {error && <ReportNotice key={`error:${error}`} error onDismiss={() => setError('')}>{error}{!workspace && <button className="bt sm" type="button" onClick={() => window.location.reload()}>Retry</button>}{workspace && selectedEventId && !summary && !loading && <button className="bt sm" type="button" onClick={() => selectEvent(selectedEventId)}>Retry</button>}</ReportNotice>}
    {message && <ReportNotice key={message} duration={exporting || busy || loading ? 0 : 5000} onDismiss={() => setMessage('')}>{message}</ReportNotice>}
    {loading && <div className="cd emp" role="status">Loading...</div>}
    {!loading && !events.length && workspace && <div className="cd emp">Select a disaster event first.</div>}
    {!loading && summary && <form className="cd report-document" noValidate onSubmit={generate}>
      <details className="report-details" ref={reportDetails} onInvalidCapture={event => { event.currentTarget.open = true }}><summary><UserRound size={20} aria-hidden="true" /><b>Report details</b><ChevronDown size={20} aria-hidden="true" /></summary><section className="report-setup" aria-label="Report details"><div className="setup-block"><div className="setup-fields"><label className="named-field">Prepared by<input aria-label="Prepared by" value={form.prepared_by} required maxLength={150} readOnly={locked} disabled={busy} onChange={event => setField('prepared_by', event.target.value)} /></label><label className="named-field">Reviewed by<input aria-label="Reviewed by" value={form.reviewed_by} required maxLength={150} readOnly={locked} disabled={busy} onChange={event => setField('reviewed_by', event.target.value)} /></label></div></div><div className="setup-block"><div className="setup-fields"><label>Period start<input aria-label="Period start" type="datetime-local" value={form.period_start} readOnly={locked} disabled={busy} onChange={event => setField('period_start', event.target.value)} /></label><label>Period end<input aria-label="Period end" type="datetime-local" value={form.period_end} readOnly={locked} disabled={busy} min={form.period_start || undefined} onChange={event => setField('period_end', event.target.value)} /></label></div></div><div className="report-context"><div><span>Alert level</span><b>{displayValue(summary.event?.severity)}</b></div><div><span>Area coverage</span><b>{displayValue(summary.event?.coverage)}</b></div><div><span>Situation status</span><b>{displayValue(summary.event?.situation_status)}</b></div></div></section></details>
      <div className="section-tools"><span className="mu"><b>{form.included_sections.length}/{sectionKeys.length}</b> sections {locked && 'Â· Locked'}</span><details className="section-options" ref={sectionOptions}><summary aria-label="Section options">Options <ChevronDown size={18} aria-hidden="true" /></summary><div onClick={event => { if (event.target.closest('button')) sectionOptions.current.open = false }}><button type="button" className="bt sm" onClick={() => setCollapsed([])}><ChevronDown size={15} />Expand all</button><button type="button" className="bt sm" onClick={() => setCollapsed(sectionKeys)}><ChevronDown size={15} className="rotate-up" />Collapse all</button>{!locked && <button type="button" className="bt sm" disabled={disabled} onClick={() => setField('included_sections', sectionKeys)}><ClipboardCheck size={15} />Include all</button>}</div></details></div>
      <SituationReferenceSections summary={summary} included={form.included_sections} collapsed={collapsed} onCollapse={number => setCollapsed(current => current.includes(number) ? sectionKeys.filter(key => key !== number) : sectionKeys)} onToggle={toggle} actions={form.actions_text} onActions={value => setField('actions_text', value)} locked={locked || busy || exporting} />
      <div className="report-footer"><span className="mu"><LockKeyhole size={14} /> Saved copies cannot be edited.</span><span className="sp" />{locked ? <button type="button" className="bt" disabled={disabled} onClick={() => selectEvent(selectedEventId)}>Create a new report</button> : <button type="submit" className="bt p" disabled={disabled || !form.included_sections.length}><LockKeyhole size={16} />{busy ? 'Savingâ€¦' : 'Save & lock report'}</button>}</div>
    </form>}
    <details className="cd saved-reports" aria-label="Saved SitRep snapshots"><summary className="saved-reports-toggle"><FolderOpen size={18} aria-hidden="true" /><b>Saved Situation Reports</b><span className="saved-count">{allReports.length}</span><ChevronDown size={19} aria-hidden="true" /></summary><div className="rh saved-report-filters"><input className="saved-search" aria-label="Search saved reports" placeholder="Search reportsâ€¦" value={reportSearch} onChange={event => setReportSearch(event.target.value)} /><span className="mu">{allReports.length ? `${reports.length} of ${allReports.length} saved reports` : 'No saved reports yet'}</span></div>{reports.length ? <div className="at"><table><thead><tr><th>SitRep number</th><th>Selected event</th><th>Generated</th><th>Status</th><th /></tr></thead><tbody>{reports.map(report => <tr key={report.sit_rep_id}><td>{report.report_number}</td><td>{report.event_name}</td><td>{report.generated_at}</td><td><span className="bg" style={{ '--c':statusColor(report.report_status) }}>{report.report_status}</span></td><td><button type="button" className="bt sm" disabled={disabled} onClick={() => openReport(report)}><FolderOpen size={14} />Open report</button></td></tr>)}</tbody></table></div> : <div className="emp">{reportSearch ? 'No saved reports match your search.' : 'Saved reports will appear here after you save a report.'}</div>}</details>
    <dialog className="signatory-dialog" ref={validationDialog} aria-labelledby="signatory-dialog-title" aria-describedby="signatory-dialog-description" onClose={focusMissingSignatory}>
      <h2 id="signatory-dialog-title">Report details required</h2>
      <p id="signatory-dialog-description">Enter {missingSignatories.map(([, label]) => label).join(' and ')} to continue.</p>
      <button type="button" className="bt p" onClick={() => validationDialog.current.close()}>Complete details</button>
    </dialog>
  </div></div></section>
}
