import { useEffect, useState } from 'react'
import { generateEvaTrackReport, getEvaTrackReportConfiguration } from '../../api/evaTrackReportApi'
import PageHeader from '../ui/PageHeader'

const types = ['demographics', 'dromic', 'utilization', 'vulnerable', 'general']

export default function EvaTrackReportsPanel() {
  const [configuration, setConfiguration] = useState(null)
  const [reportType, setReportType] = useState('demographics')
  const [format, setFormat] = useState('pdf')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [report, setReport] = useState(null)
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    let active = true
    getEvaTrackReportConfiguration().then((data) => {
      if (active) setConfiguration(data)
    }).catch((failure) => {
      if (active) setError(failure.response?.data?.message || 'Cannot load EvaTrack connection settings.')
    })
    return () => { active = false }
  }, [])

  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 1000)
    return () => clearInterval(timer)
  }, [])

  async function generate(event) {
    event.preventDefault()
    setError('')
    setReport(null)
    setBusy(true)
    try {
      const result = await generateEvaTrackReport({
        report_type: reportType, format,
        ...(startDate ? { start_date: startDate } : {}),
        ...(endDate ? { end_date: endDate } : {}),
      })
      setReport(result)
      setNow(Date.now())
    } catch (failure) {
      const details = failure.response?.data
      setError(details?.errors ? Object.values(details.errors).flat().join(' ') : details?.message || 'EvaTrack could not generate the report.')
    } finally {
      setBusy(false)
    }
  }

  const expired = report && (!Number.isFinite(Date.parse(report.expires_at)) || Date.parse(report.expires_at) <= now)

  return <div className="archive-workspace-main">
    <PageHeader title="EvaTrack Reports" subtitle="Generate and download reports from EvaTrack." />
    <div className="rr-message archive-message" role="status">
      {configuration === null ? 'Loading connection settings…' : configuration.configured
        ? 'Connection configured. Generate a report to check EvaTrack authentication and availability.'
        : 'EvaTrack is not connected. Configure its server URL and reports API key on the backend.'}
    </div>
    <form onSubmit={generate} style={{ display: 'grid', gap: 16, maxWidth: 600, padding: 24 }}>
      <label>Report type <select value={reportType} onChange={(event) => setReportType(event.target.value)}>
        {types.map((type) => <option key={type} value={type}>{type === 'dromic' ? 'DROMIC' : type[0].toUpperCase() + type.slice(1)}</option>)}
      </select></label>
      <label>Format <select value={format} onChange={(event) => setFormat(event.target.value)}>
        <option value="pdf">PDF</option><option value="csv">CSV</option>
      </select></label>
      <label>Start date <input type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} /></label>
      <label>End date <input type="date" min={startDate || undefined} value={endDate} onChange={(event) => setEndDate(event.target.value)} /></label>
      <button type="submit" className="btn primary" disabled={busy || !configuration?.configured}>{busy ? 'Generating report…' : 'Generate EvaTrack report'}</button>
      {error && <div className="form-error" role="alert">{error}</div>}
      {report && <div className="rr-message" role="status">
        <p>EvaTrack connection verified. Report generated: {report.file_name}</p>
        <p>{expired ? 'Download link expired. Generate the report again.' : `Link expires: ${new Date(report.expires_at).toLocaleString()}`}</p>
        {!expired && <a className="btn primary" href={report.download_url} target="_blank" rel="noopener noreferrer">View / download report</a>}
      </div>}
    </form>
    <section style={{ padding: 24 }} aria-label="Share our reports with EvaTrack">
      <h2>EvaTrack can request our reports</h2>
      <p>Connect EvaTrack to this backend with its assigned reports API key in the X-API-KEY header.</p>
      <p>Check authentication: <code>POST /api/v1/external/reports/handshake</code></p>
      <p>Request a report: <code>POST /api/v1/external/reports/generate</code></p>
      <pre>{JSON.stringify({ report_type: 'archive', category: 'resource-requests', format: 'csv' }, null, 2)}</pre>
      <p>Our API returns a signed download link. It supports archive and situation reports in PDF, CSV, and XLSX.</p>
    </section>
  </div>
}
