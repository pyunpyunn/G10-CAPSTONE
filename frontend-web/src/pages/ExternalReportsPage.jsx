import { useCallback, useEffect, useRef, useState } from 'react'
import { ArrowUpRight, ArrowRight, Inbox, Send, Check, X, RefreshCw, Download, Layers, FileText, Users, CloudRain, HeartPulse, Route, House, Package, ClipboardList, Search, ExternalLink } from 'lucide-react'
import EvaTrackReportsPanel from '../components/archive/EvaTrackReportsPanel'
import HouseholdExportScope from '../components/situation/HouseholdExportScope'
import { getExternalReportProviders, getReportRequestInbox, refreshReportRequestFile } from '../api/evaTrackReportApi'
import { downloadBackendReport } from '../api/reportApi'
import { exportSituationReport, getSituationWorkspace } from '../api/situationReportApi'
import { reportSections, reportSectionNumbers } from '../utils/situationSections'
import { situationErrorMessage } from '../utils/situationReportHelpers'
import '../styles/report-exchange.css'
import '../styles/situation-html.css'

const systems = { evatrack: 'EvaTrack', safetrack: 'SafeTrack', trackingaid: 'TrackingAid' }
const sectionIcons = { I: CloudRain, II: Users, III: HeartPulse, IV: House, V: Route, VI: House, VII: Package, VIII: ClipboardList }
const impactReports = [
  { key: 'sitio-ranking', title: 'Sitio Ranking Export', description: 'Rank sitios by reported household status, priority, or both. Includes the highest recorded quantities and when they were recorded.', icon: Layers },
  { key: 'purok-impact', title: 'Purok Impact by Sitio Over Time', description: 'See the recorded impact trend for each sitio as a line graph, matching the dashboard chart.', icon: Route },
  { key: 'past-event-impact', title: 'Past Disaster Events — Overall Impact in Mambaling', description: 'See completed-event impact trends as a line graph, with overall totals called out above the chart.', icon: CloudRain },
]
const titleFor = (key, version = '4.0') => version !== '4.0' ? `Section ${key} (older template)` : reportSections.find(([number]) => number === key)?.[1] || key
const statusLabel = status => ({ accepted: 'Successful', failed: 'Unsuccessful', pending: 'Legacy pending', rejected: 'Legacy rejected' })[status] || status
const formatDate = value => value ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Manila' }).format(new Date(/[zZ]|[+-]\d\d:\d\d$/.test(value) ? value : `${value.replace(' ', 'T')}+08:00`)) : '—'

export default function ExternalReportsPage() {
  const [tab, setTab] = useState('inbox'), [filter, setFilter] = useState(''), [search, setSearch] = useState(''), [systemFilter, setSystemFilter] = useState('')
  const [inbox, setInbox] = useState(null), [providers, setProviders] = useState([]), [events, setEvents] = useState([])
  const [page, setPage] = useState(1), [loading, setLoading] = useState(true), [error, setError] = useState(''), [notice, setNotice] = useState('')
  const [outgoing, setOutgoing] = useState(null), [selected, setSelected] = useState(null), [section, setSection] = useState(null), [impactReport, setImpactReport] = useState(null)
  const loadVersion = useRef(0)
  const loadInbox = useCallback(async (quiet = false) => {
    const version = ++loadVersion.current
    if (!quiet) setLoading(true)
    try {
      const data = await getReportRequestInbox({ status: filter || undefined, source_system: systemFilter || undefined, search: search.trim() || undefined, page })
      if (version === loadVersion.current) { setInbox(data); setError('') }
    } catch (failure) { if (version === loadVersion.current) setError(situationErrorMessage(failure, 'Could not load the request inbox.')) }
    finally { if (version === loadVersion.current) setLoading(false) }
  }, [filter, page, systemFilter, search])
  useEffect(() => {
    const timer = window.setTimeout(() => loadInbox(), 200)
    return () => { clearTimeout(timer) }
  }, [loadInbox])
  useEffect(() => {
    let cancelled = false
    getExternalReportProviders().then(data => { if (!cancelled) setProviders(data) }).catch(() => { /* The request form provides connection errors. */ })
    getSituationWorkspace().then(data => { if (!cancelled) setEvents(data.events || []) }).catch(() => { if (!cancelled) setNotice('Disaster events could not be loaded. Refresh before accepting or exporting a report.') })
    return () => { cancelled = true }
  }, [])
  useEffect(() => {
    const update = () => { if (!document.hidden) loadInbox(true) }
    const timer = window.setInterval(update, 30000)
    window.addEventListener('focus', update)
    return () => { clearInterval(timer); window.removeEventListener('focus', update) }
  }, [loadInbox])
  const counts = inbox?.counts || {}
  const rows = inbox?.requests || []
  function changeFilter(value) { setFilter(value); setPage(1); setTab('inbox') }
  function resolved(row) {
    setSelected(row)
    if (row.status === 'accepted') setNotice('The automatically prepared report is ready to download.')
    else if (row.status === 'failed') setNotice('The report request was recorded as unsuccessful.')
    loadInbox(true)
  }
  return <section className="exchange-page" aria-label="External report exchange">
    <div className="exchange-toolbar">
      <nav className="exchange-tabs" aria-label="Report workflows">
        {[['inbox', Inbox, 'Request log'], ['request', ArrowUpRight, 'Request a report'], ['sections', Layers, 'Situation reports'], ['impact', CloudRain, 'Impact reports']].map(([key, Icon, text]) => <button key={key} aria-current={tab === key ? 'page' : undefined} className={tab === key ? 'active' : ''} onClick={() => setTab(key)}><Icon size={17} />{text}{key === 'inbox' && Boolean(counts.successful) && <span>{counts.successful}</span>}</button>)}
      </nav>
      <button className="ex-button primary" onClick={() => setTab('request')}><Send size={16} />New request</button>
    </div>
    {error && <div className="exchange-alert error" role="alert">{error}<button className="ex-button" onClick={() => loadInbox()}>Retry</button></div>}
    {notice && <div className="exchange-alert" role="status">{notice}<button aria-label="Dismiss notice" onClick={() => setNotice('')}><X size={17} /></button></div>}
    {tab === 'inbox' && <main className="exchange-inbox">
      <div className="exchange-inbox-tools">
        <div className="exchange-filter">{[['accepted', 'Successful', counts.successful ?? counts.accepted], ['failed', 'Unsuccessful', counts.failed], ['', 'All', counts.total ?? inbox?.total]].map(([key, title, count]) => <button key={key} className={filter === key ? 'active' : ''} aria-pressed={filter === key} onClick={() => changeFilter(key)}>{title}<span>{Number(count) || 0}</span></button>)}</div>
        <div className="exchange-tools-right"><label className="exchange-search"><Search size={16} /><input aria-label="Search requests" placeholder="Search requests…" value={search} onChange={event => { setSearch(event.target.value); setPage(1) }} /></label><select className="exchange-system-filter" aria-label="Filter by requesting system" value={systemFilter} onChange={event => { setSystemFilter(event.target.value); setPage(1) }}><option value="">All systems</option>{Object.entries(systems).map(([key, name]) => <option key={key} value={key}>{name}</option>)}</select><button className="ex-button icon" aria-label="Refresh requests" disabled={loading} onClick={() => loadInbox()}><RefreshCw size={16} className={loading ? 'spin' : ''} /></button></div>
      </div>
      <div className="exchange-list-heading" aria-hidden="true"><span>Request / system</span><span>Requested report</span><span>Format</span><span>Received</span><span /></div>
      <div className="exchange-request-list" aria-busy={loading}>
        {loading && !inbox ? <div className="exchange-empty" role="status">Loading requests…</div> : rows.length ? rows.map(row => <button key={row.id} className="exchange-request-card" onClick={() => setSelected(row)}>
          <span className="exchange-request-origin"><span className={`exchange-system-avatar ${row.source_system}`}>{systems[row.source_system]?.slice(0, 2)}</span><span><strong>{systems[row.source_system]}</strong><small>{row.reference || row.id.slice(0, 8)}</small></span></span>
          <span className="exchange-request-body"><span className="exchange-request-subject">{row.sections.length === 1 ? titleFor(row.sections[0], row.api_version) : `${row.sections.length} report sections`}</span><span className={`exchange-badge ${row.status}`}>{statusLabel(row.status)}</span></span>
          <span className="exchange-file-type"><FileText size={14} />{row.format === 'xlsx' ? 'Excel' : row.format.toUpperCase()}</span>
          <span className="exchange-request-date">{formatDate(row.created_at)}</span>
          <span className="exchange-review-action">View details<ArrowRight size={16} /></span>
        </button>) : <div className="exchange-empty"><span className="exchange-empty-icon"><Inbox size={27} /></span><h3>{search || systemFilter ? 'No matching requests' : filter === 'accepted' ? 'No successful requests' : filter === 'failed' ? 'No unsuccessful requests' : 'No external requests'}</h3><p>{search || systemFilter ? 'Adjust your filters.' : 'External report requests will appear here.'}</p></div>}
      </div>
      {inbox && inbox.last_page > 1 && <div className="exchange-pagination"><button className="ex-button" disabled={page === 1 || loading} onClick={() => setPage(page - 1)}>Previous</button><span>Page {page} of {inbox.last_page}</span><button className="ex-button" disabled={page >= inbox.last_page || loading} onClick={() => setPage(page + 1)}>Next</button></div>}
    </main>}
    {tab === 'request' && <div className="exchange-workflow-panel">
      <div className="exchange-panel-heading"><h2>Select a system</h2></div>
      <div className="exchange-provider-grid">{Object.entries(systems).map(([key, name]) => { const config = providers.find(item => item.key === key); return <button key={key} className="exchange-provider-card" onClick={() => setOutgoing(key)}><span className={`exchange-system-avatar ${key}`}>{name.slice(0, 2)}</span><span className={`exchange-badge ${config?.configured ? 'accepted' : 'neutral'}`}>{config ? config.configured ? 'Configured' : 'Not configured' : 'Unavailable'}</span><h3>{name}</h3><span className="exchange-provider-bottom">Request report<ArrowUpRight size={17} /></span></button> })}</div>
    </div>}
    {tab === 'sections' && <div className="exchange-workflow-panel">
      <div className="exchange-panel-heading"><h2>Situation report sections</h2><a className="ex-button" href="/situation">Situation Reporting<ExternalLink size={14} /></a></div>
      <div className="exchange-section-list">{reportSections.map(([key, title]) => { const Icon = sectionIcons[key] || FileText; return <button key={key} className="exchange-section-card" onClick={() => setSection(key)}><span className="exchange-icon"><Icon size={20} /></span><span className="exchange-section-name"><small>SECTION {reportSectionNumbers[key]}</small><strong>{title}</strong></span><span className="exchange-section-formats">PDF · Excel · CSV</span><span className="exchange-section-action">Export<ArrowUpRight size={16} /></span></button> })}</div>
    </div>}
    {tab === 'impact' && <div className="exchange-workflow-panel">
      <div className="exchange-panel-heading"><h2>Disaster impact reports</h2><p>Exports are generated from recorded event, household, member, and responder assessment data.</p></div>
      <div className="exchange-provider-grid">{impactReports.map(({ key, title, description, icon: Icon }) => <button key={key} className="exchange-provider-card impact-report-card" onClick={() => setImpactReport(key)}><span className="exchange-icon"><Icon size={20} /></span><h3>{title}</h3><p>{description}</p><span className="exchange-provider-bottom">Export report<ArrowUpRight size={17} /></span></button>)}</div>
    </div>}
    {outgoing && <ExchangeDialog title={`Request from ${systems[outgoing]}`} eyebrow="NEW REQUEST" onClose={() => setOutgoing(null)} wide><EvaTrackReportsPanel initialProvider={outgoing} compact /></ExchangeDialog>}
    {selected && <RequestDetails key={selected.id} request={selected} onClose={() => setSelected(null)} onResolved={resolved} />}
    {section && <SectionExport section={section} events={events} onClose={() => setSection(null)} />}
    {impactReport && <ImpactReportExport reportType={impactReport} events={events} onClose={() => setImpactReport(null)} />}
  </section>
}

function ExchangeDialog({ title, eyebrow, onClose, children, wide, busy = false }) {
  const ref = useRef(null)
  useEffect(() => { ref.current.showModal() }, [])
  return <dialog ref={ref} className={`exchange-dialog ${wide ? 'wide' : ''}`} onCancel={event => { event.preventDefault(); if (!busy) onClose() }} aria-labelledby="exchange-dialog-title"><header><div><span className="exchange-eyebrow">{eyebrow}</span><h2 id="exchange-dialog-title">{title}</h2></div><button className="ex-button icon" disabled={busy} aria-label="Close dialog" onClick={onClose}><X size={21} /></button></header>{children}</dialog>
}

function EventSelect({ events, value, onChange, disabled }) {
  return <label className="exchange-field">Disaster event<select value={value} onChange={event => onChange(event.target.value)} disabled={disabled} required><option value="">Select a disaster event</option>{events.map(event => <option key={event.event_id} value={event.event_id}>{event.name} · {event.declared_at}</option>)}</select></label>
}

function RequestDetails({ request, onClose, onResolved }) {
  const [row, setRow] = useState(request), [busy, setBusy] = useState(false), [error, setError] = useState('')
  async function refreshFile() {
    setBusy(true); setError('')
    try { const result = await refreshReportRequestFile(row.id); setRow(result); onResolved(result) }
    catch (failure) { setError(situationErrorMessage(failure, 'Could not refresh the download.')) }
    finally { setBusy(false) }
  }
  return <ExchangeDialog title="External request details" eyebrow={`INCOMING · ${systems[row.source_system]} → RESQPERATION`} onClose={onClose} busy={busy} wide>
    <div className="exchange-dialog-body"><div className="exchange-detail-top"><span>{row.reference || row.id}</span><span>{formatDate(row.created_at)}</span></div><h3>Requested sections</h3><div className="exchange-requested-sections">{(row.sections || []).map(key => { const Icon = sectionIcons[key] || FileText; return <div key={key}><Icon size={20} /><span><strong>{titleFor(key, row.api_version)}</strong></span>{row.status === 'accepted' && <Check size={16} />}</div> })}</div><div className="exchange-detail-grid"><div className="exchange-detail-prepared"><span>Prepared by</span><p>{row.prepared_by}</p></div><div className="exchange-detail-purpose"><span>Purpose</span><p>{row.purpose}</p></div><div className="exchange-detail-format"><span>Format</span><p>{row.format === 'xlsx' ? 'Excel spreadsheet' : row.format.toUpperCase()}</p></div></div>
      {row.status === 'accepted' && <div className="exchange-decision-summary accepted"><strong>Automatically accepted and prepared</strong><small>{row.decided_by || 'Automatic report exchange'} · {formatDate(row.decided_at)}</small>{row.file && <div className="exchange-file-result"><FileText size={24} /><span><strong>{row.file.file_name}</strong><small>{row.file.expired ? 'Download expired.' : `Download expires ${formatDate(row.file.expires_at)}`}</small></span></div>}</div>}
      {row.status === 'failed' && <div className="exchange-decision-summary rejected"><strong>Report preparation was unsuccessful</strong><p>{row.decision_note || 'The report could not be prepared automatically. The requesting system can retry.'}</p></div>}
      {row.status === 'pending' && <p className="exchange-alert">This request predates automatic processing. Ask the requesting system to submit it again.</p>}
      {row.status === 'rejected' && <p className="exchange-alert">This request was reviewed under the former manual workflow.</p>}
      {error && <p className="exchange-alert error" role="alert">{error}</p>}
    </div><footer className="exchange-dialog-footer"><span className="exchange-footer-status">{statusLabel(row.status)}</span>{row.file?.download_url && <a className="ex-button primary" href={row.file.download_url} target="_blank" rel="noreferrer"><Download size={17} />Download report</a>}{row.status === 'accepted' && row.file?.expired && <button className="ex-button primary" disabled={busy} onClick={refreshFile}><RefreshCw size={17} />{busy ? 'Renewing…' : 'Renew download'}</button>}</footer>
  </ExchangeDialog>
}

function SectionExport({ section, events, onClose }) {
  const [eventId, setEventId] = useState(''), [format, setFormat] = useState('pdf'), [busy, setBusy] = useState(false)
  const [error, setError] = useState(''), [done, setDone] = useState(false)
  const [preparedBy, setPreparedBy] = useState(''), [reviewedBy, setReviewedBy] = useState('')
  const [scope,setScope] = useState({sitio_id:'all',purok_id:'all',include_households:true})
  async function download(event) {
    event.preventDefault(); setBusy(true); setError(''); setDone(false)
    try { await exportSituationReport({ event_id: eventId, ...scope, format, included_sections: [section], database_only: true, prepared_by: preparedBy.trim(), reviewed_by: reviewedBy.trim() }); setDone(true) }
    catch (failure) { setError(situationErrorMessage(failure, 'Could not prepare this section.')) }
    finally { setBusy(false) }
  }

  return <ExchangeDialog title={titleFor(section)} eyebrow={`EXPORT SECTION ${section}`} onClose={onClose} busy={busy}><form onSubmit={download}><div className="exchange-dialog-body"><EventSelect events={events} value={eventId} onChange={value => { setEventId(value); setDone(false) }} disabled={busy} />
    <HouseholdExportScope external value={scope} onChange={value => { setScope(value); setDone(false); setError('') }} disabled={busy} />
    <div className="exchange-detail-grid exchange-signatories"><label className="exchange-field">Prepared by<input required maxLength={150} value={preparedBy} onChange={event => setPreparedBy(event.target.value)} disabled={busy} /></label><label className="exchange-field">Reviewed by<input required maxLength={150} value={reviewedBy} onChange={event => setReviewedBy(event.target.value)} disabled={busy} /></label></div>
    <fieldset className="exchange-formats"><legend>File format</legend>{[['pdf', 'PDF'], ['xlsx', 'Excel'], ['csv', 'CSV']].map(([key, text]) => <label key={key} className={format === key ? 'active' : ''}><input type="radio" name="format" value={key} checked={format === key} disabled={busy} onChange={() => { setFormat(key); setDone(false) }} /><FileText size={19} />{text}</label>)}</fieldset>{error && <p className="exchange-alert error" role="alert">{error}</p>}{done && <p className="exchange-alert" role="status">Your section report has been downloaded.</p>}</div><footer className="exchange-dialog-footer"><button className="ex-button" type="button" disabled={busy} onClick={onClose}>Cancel</button><button className="ex-button primary" disabled={busy || !eventId} type="submit"><Download size={17} />{busy ? 'Preparing…' : 'Download section'}</button></footer></form></ExchangeDialog>
}

function ImpactReportExport({ reportType, events, onClose }) {
  const report = impactReports.find(item => item.key === reportType)
  const [eventId, setEventId] = useState(''), [format, setFormat] = useState('xlsx')
  const [period, setPeriod] = useState('monthly')
  const [rankingTypes, setRankingTypes] = useState(['status', 'priority'])
  const [busy, setBusy] = useState(false), [error, setError] = useState(''), [done, setDone] = useState(false)
  const needsEvent = reportType !== 'past-event-impact'

  async function download(event) {
    event.preventDefault()
    setBusy(true); setError(''); setDone(false)
    try {
      await downloadBackendReport({
        report_type: reportType,
        ...(needsEvent ? { event_id: eventId } : {}),
        ...(reportType === 'sitio-ranking' ? { ranking_types: rankingTypes } : {}),
        ...(reportType === 'past-event-impact' ? { period } : {}),
        format,
      })
      setDone(true)
    } catch (failure) {
      setError(situationErrorMessage(failure, 'Could not prepare this impact report.'))
    } finally { setBusy(false) }
  }

  function toggleRanking(type) {
    setRankingTypes(current => current.includes(type) ? current.filter(value => value !== type) : [...current, type])
    setDone(false)
  }

  return <ExchangeDialog title={report.title} eyebrow="IMPACT REPORT" onClose={onClose} busy={busy} wide>
    <form onSubmit={download}><div className="exchange-dialog-body">
      <p className="impact-report-description">{report.description}</p>
      {needsEvent && <EventSelect events={events} value={eventId} onChange={value => { setEventId(value); setDone(false) }} disabled={busy} />}
      {reportType === 'sitio-ranking' && <fieldset className="exchange-formats impact-ranking-options"><legend>Ranking types</legend>
        { [['status', 'Status ranking'], ['priority', 'Priority ranking']].map(([key, label]) => <label key={key} className={rankingTypes.includes(key) ? 'active' : ''}><input type="checkbox" checked={rankingTypes.includes(key)} disabled={busy} onChange={() => toggleRanking(key)} />{label}</label>)}
      </fieldset>}
      {reportType === 'sitio-ranking' && <p className="impact-report-note">The report page presents rankings as readable highlights. Excel also includes separate bar graphs for the selected ranking type(s).</p>}
      {reportType === 'purok-impact' && <p className="impact-report-note">The report graph follows the dashboard’s sitio impact timeline. Excel includes a separate graph sheet; sample/demo sitios are excluded.</p>}
      {reportType === 'past-event-impact' && <>
        <label className="exchange-field">Group impact by<select value={period} disabled={busy} onChange={event => { setPeriod(event.target.value); setDone(false) }}><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></label>
        <p className="impact-report-note">Uses the dashboard’s selected time grouping and latest assessment per household, so reassessments do not inflate totals.</p>
      </>}
      <fieldset className="exchange-formats"><legend>File format</legend>{[['pdf', 'PDF'], ['xlsx', 'Excel']].map(([key, text]) => <label key={key} className={format === key ? 'active' : ''}><input type="radio" name="impact-format" value={key} checked={format === key} disabled={busy} onChange={() => { setFormat(key); setDone(false) }} /><FileText size={19} />{text}</label>)}</fieldset>
      {error && <p className="exchange-alert error" role="alert">{error}</p>}{done && <p className="exchange-alert" role="status">Your report has been downloaded.</p>}
    </div><footer className="exchange-dialog-footer"><button className="ex-button" type="button" disabled={busy} onClick={onClose}>Cancel</button><button className="ex-button primary" disabled={busy || (needsEvent && !eventId) || (reportType === 'sitio-ranking' && !rankingTypes.length)} type="submit"><Download size={17} />{busy ? 'Preparing…' : 'Download report'}</button></footer></form>
  </ExchangeDialog>
}
