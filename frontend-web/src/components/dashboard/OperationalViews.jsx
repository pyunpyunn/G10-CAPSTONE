import { useId, useState } from 'react'
import { Chart as ChartJS, ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js'
import { Bar, Pie } from 'react-chartjs-2'
import Panel from '../ui/Panel'

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend)

const colors = { 'safe-total': '#15803d', 'en-route': '#d97706', completed: '#7c3aed', 'safe-only': '#16a34a', evacuated: '#2563eb', unsafe: '#dc2626', unchecked: '#64748b', dispatched: '#d97706', 'on-scene': '#dc2626', available: '#16a34a', other: '#64748b' }

export default function OperationalViews({ dashboard, onOpenModule }) {
  const [view, setView] = useState('numbers')
  const h = dashboard.households
  const householdBars = (h.bars || []).map((bar) => bar.class_name === 'safe-total' ? { ...bar, label: 'Safe Total' } : bar)
  const teamBars = dashboard.dispatch.team_counts || dashboard.dispatch.counts || []
  const teamTotal = dashboard.dispatch.total_teams ?? teamBars.reduce((sum, bar) => sum + Number(bar.value || 0), 0)
  return (
    <Panel title="Operational overview" className="dashboard-operation-views" action={
      <label className="dashboard-view-select">Display <select value={view} onChange={(event) => setView(event.target.value)}>
        <option value="numbers">Numerical</option><option value="pie">Pie chart</option><option value="bar">Bar graph</option>
      </select></label>
    }>
      <div className="dashboard-dispatch-graphs">
        <StatusView title="Household status" totalLabel="Total households" total={h.total} bars={householdBars} view={view}
          progressValue={dashboard.active_event ? h.reported : 0} progressLabel="Reporting progress"
          onOpen={() => onOpenModule('/households')} />
        <StatusView title="Dispatch status" totalLabel="Total teams" total={teamTotal} bars={teamBars.filter((bar) => bar.class_name !== 'other')} view={view}
          progressValue={teamBars.filter((bar) => ['en-route', 'on-scene'].includes(bar.class_name)).reduce((sum, bar) => sum + Number(bar.value || 0), 0)}
          progressLabel="Teams deployed"
          onOpen={() => onOpenModule('/dispatch')} />
      </div>
    </Panel>
  )
}

function StatusView({ title, totalLabel, total, bars, view, onOpen, progressValue, progressLabel }) {
  const progressTooltipId = useId()
  const count = Math.min(Number(total) || 0, Math.max(0, Number(progressValue) || 0))
  const percentage = total > 0 ? Math.round(count / total * 100) : 0
  const chartBars = view === 'pie' ? bars.filter((bar) => bar.class_name !== 'safe-total') : bars
  const data = { labels: chartBars.map((bar) => bar.label), datasets: [{ label: title, data: chartBars.map((bar) => Number(bar.value || 0)), backgroundColor: chartBars.map((bar) => colors[bar.class_name] || '#64748b'), borderWidth: 1 }] }
  const options = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: view === 'pie', position: 'bottom' }, tooltip: { callbacks: {
      label: (context) => `${context.label}: ${Number(context.raw).toLocaleString()} (${total ? (Number(context.raw) / total * 100).toFixed(1) : '0.0'}% of ${totalLabel.toLowerCase()})`,
    } } },
    ...(view === 'bar' ? { scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } } : {}),
  }
  return <div className={`dispatch-chart dashboard-status-card ${totalLabel === 'Total households' ? 'household' : 'teams'} ${view === 'pie' ? 'is-pie' : ''}`}>
    <div className="dispatch-chart-head"><h3 className="dispatch-chart-title">{title}</h3><button type="button" className="chart-manage-button" aria-label={`Open ${title}`} onClick={onOpen}>→</button></div>
    <div className="dashboard-reporting">
      <div className="dashboard-reporting-label"><span>{progressLabel}</span><strong>{count}/{Number(total) || 0}</strong></div>
      <div className="dashboard-reporting-meter">
        <div className="dashboard-reporting-track" role="progressbar" tabIndex={0} aria-label={progressLabel} aria-valuemin={0} aria-valuemax={Number(total) || 1} aria-valuenow={count} aria-valuetext={`${count} of ${Number(total) || 0}, ${percentage}%`} aria-describedby={progressTooltipId}>
          <span style={{ width: `${percentage}%` }} />
        </div>
        <span id={progressTooltipId} className="dashboard-reporting-tooltip" role="tooltip">{percentage}%</span>
      </div>
    </div>
    {(view === 'numbers' || (view === 'pie' && bars.some((bar) => bar.class_name === 'safe-total'))) && <dl className="dashboard-status-numbers">
      {bars.filter((bar) => view === 'numbers' || bar.class_name === 'safe-total').map((bar) => <div key={bar.label}><dt><i style={{ background: colors[bar.class_name] }} />{bar.label}</dt><dd>{Number(bar.value || 0).toLocaleString()}</dd></div>)}
    </dl>}
    {view !== 'numbers' && (Number(total) > 0 ? <div className="dashboard-status-chart">{view === 'pie' ? <Pie data={data} options={options} aria-label={`${title} pie chart`} /> : <Bar data={data} options={options} aria-label={`${title} bar graph`} />}</div>
      : <p className="dashboard-count-note">No {totalLabel.toLowerCase().replace('total ', '')} recorded.</p>)}
  </div>
}
