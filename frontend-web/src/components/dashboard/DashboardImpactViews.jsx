import { useCallback, useState } from 'react'
import { Line } from 'react-chartjs-2'
import Panel from '../ui/Panel'
import RescueCriteriaChart from './RescueCriteriaChart'
import { getDashboardImpact } from '../../api/dashboardApi'
import { useModuleData } from '../../utils/useModuleData'

const metrics = [
  { key: 'deaths', label: 'Deaths', color: '#dc2626' },
  { key: 'injured', label: 'Injured', color: '#d97706' },
  { key: 'missing', label: 'Missing', color: '#7c3aed' },
  { key: 'damaged_houses', label: 'Damaged houses', color: '#2563eb' },
]

export default function DashboardImpactViews({ eventId }) {
  const [view, setView] = useState('current')
  return <div className="dashboard-impact-views">
    <label className="dashboard-view-select">Impact view <select value={view} onChange={(event) => setView(event.target.value)}>
      <option value="current">Purok impact by sitio over time</option>
      <option value="history">Past disaster events — entire Mambaling</option>
    </select></label>
    {view === 'current' ? <RescueCriteriaChart eventId={eventId} /> : <HistoricalImpact />}
  </div>
}

function HistoricalImpact() {
  const [period, setPeriod] = useState('monthly')
  const [selected, setSelected] = useState(() => new Set(metrics.map((metric) => metric.key)))
  const loader = useCallback(() => getDashboardImpact(period), [period])
  const { data, loading, error, refresh } = useModuleData(loader, ['disasters', 'households', 'dispatch'])
  const rows = data?.periods || []
  const active = metrics.filter((metric) => selected.has(metric.key))
  const options = {
    responsive: true, maintainAspectRatio: false,
    scales: { y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Recorded count' } } },
    plugins: { legend: { display: false }, tooltip: { callbacks: {
      label: (context) => {
        const metric = active[context.datasetIndex]
        const total = data?.totals?.[metric.key] || 0
        return `${metric.label}: ${context.raw} (${total ? (context.raw / total * 100).toFixed(1) : '0.0'}% of this metric’s displayed total: ${total})`
      },
    } } },
  }
  return <Panel title="Past disaster events — overall impact in Mambaling" action={
    <label className="dashboard-view-select">Group by <select value={period} onChange={(event) => setPeriod(event.target.value)}>
      <option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="yearly">Yearly</option>
    </select></label>
  }>
    {loading && <p role="status">Loading recorded impact…</p>}
    {error && <div role="alert">{error} <button type="button" onClick={refresh}>Retry</button></div>}
    {!loading && !error && data && <>
      <div className="dashboard-impact-legend" role="group" aria-label="Select impact metrics">
        {metrics.map((metric) => <button type="button" key={metric.key} aria-pressed={selected.has(metric.key)}
          className={selected.has(metric.key) ? '' : 'is-hidden'}
          title={`${metric.label}: ${Number(data.totals[metric.key] || 0).toLocaleString()} total`}
          onClick={() => setSelected((current) => { const next = new Set(current); if (next.has(metric.key)) next.delete(metric.key); else next.add(metric.key); return next })}>
          <i aria-hidden="true" style={{ backgroundColor: metric.color }} />{metric.label}
        </button>)}
      </div>
      {rows.length === 0 ? <p>No completed disaster events recorded.</p> : active.length === 0 ? <p>Select a metric to display its history.</p> : <>
        <div className="dashboard-history-chart"><Line options={options} aria-label="Recorded impact of past Mambaling disaster events" data={{ labels: rows.map((row) => row.label), datasets: active.map((metric) => ({ label: metric.label, data: rows.map((row) => row[metric.key]), backgroundColor: metric.color, borderColor: metric.color, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: 0.25 })) }} /></div>
      </>}
      {rows.length > 0 && <details className="dashboard-impact-details"><summary>Recorded totals by {period === 'weekly' ? 'week' : period === 'monthly' ? 'month' : 'year'}</summary>
        <div className="dashboard-impact-table"><table><caption>Recorded totals by {period === 'weekly' ? 'week' : period === 'monthly' ? 'month' : 'year'}</caption><thead><tr><th scope="col">Period</th><th scope="col">Events</th>{metrics.map((metric) => <th scope="col" key={metric.key}>{metric.label}</th>)}</tr></thead><tbody>{rows.map((row) => <tr key={row.key}><th scope="row">{row.label}</th><td>{row.event_count}</td>{metrics.map((metric) => <td key={metric.key} title={`${data.totals[metric.key] ? (row[metric.key] / data.totals[metric.key] * 100).toFixed(1) : '0.0'}% of displayed ${metric.label.toLowerCase()} total`}>{row[metric.key]}</td>)}</tr>)}</tbody><tfoot><tr><th scope="row">Total</th><td>{data.event_count}</td>{metrics.map((metric) => <td key={metric.key}>{data.totals[metric.key]}</td>)}</tr></tfoot></table></div>
      </details>}
    </>}
  </Panel>
}
