import { useEffect, useMemo, useState } from 'react'
import { Chart as ChartJS, LinearScale, CategoryScale, PointElement, LineElement, Tooltip, Legend, Title } from 'chart.js'
import { Line } from 'react-chartjs-2'
import { getRescueCriteriaFeed } from '../../api/dispatchApi'
import Panel from '../ui/Panel'
import { useRealtimeVersion } from '../../utils/useRealtimeVersion'

ChartJS.register(LinearScale, CategoryScale, PointElement, LineElement, Tooltip, Legend, Title)

const criteria = [
  { key: 'impact', label: 'Purok impact', weightKey: 'impact_weight', color: '#378ADD' },
  { key: 'special_needs', label: 'Special needs', weightKey: 'vulnerability_weight', color: '#7F77DD' },
  { key: 'unreported', label: 'Unreported members', weightKey: 'unreported_weight', color: '#EF9F27' },
  { key: 'no_contact', label: 'No contact channel', weightKey: 'no_contact_weight', color: '#D85A30' },
]

function seriesSignature(points) {
  return points.map((point) => [point.hours_since_alert, point.impact, point.special_needs, point.unreported, point.no_contact, point.rescue_priority_version].join(':')).join('|')
}

function settingsSignature(settings) {
  return ['version', 'impact_weight', 'vulnerability_weight', 'unreported_weight', 'no_contact_weight']
    .map((key) => settings?.[key] ?? '')
    .join(':')
}

export default function RescueCriteriaChart({ eventId, refreshVersion }) {
  const realtimeVersion = useRealtimeVersion(['households', 'dispatch', 'disasters'])
  const [points, setPoints] = useState([])
  const [weights, setWeights] = useState(null)
  const [lastUpdated, setLastUpdated] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let cancelled = false
    let timer
    let controller
    let refreshPending = false

    async function refresh() {
      if (cancelled || document.hidden) return
      if (controller) {
        refreshPending = true
        return
      }
      window.clearTimeout(timer)
      controller = new AbortController()
      try {
        const feed = await getRescueCriteriaFeed({ signal: controller.signal })
        if (cancelled) return
        const nextPoints = Array.isArray(feed.data) ? feed.data : []
        setPoints((current) => seriesSignature(current) === seriesSignature(nextPoints) ? current : nextPoints)
        setWeights((current) => settingsSignature(current) === settingsSignature(feed.meta?.settings) ? current : feed.meta?.settings || null)
        const nextUpdated = new Date()
        setLastUpdated((current) => current && current.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) === nextUpdated.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) ? current : nextUpdated)
        setError('')
      } catch {
        if (!cancelled) setError('Rescue criteria could not be loaded. Retrying automatically.')
      } finally {
        controller = null
        if (!cancelled) {
          if (refreshPending) timer = window.setTimeout(refresh, 0)
          refreshPending = false
        }
      }
    }

    refresh()
    window.addEventListener('focus', refresh)
    window.addEventListener('online', refresh)

    document.addEventListener('visibilitychange', refresh)
    return () => {
      cancelled = true
      controller?.abort()
      window.clearTimeout(timer)
      window.removeEventListener('focus', refresh)
      window.removeEventListener('online', refresh)

      document.removeEventListener('visibilitychange', refresh)
    }
  }, [eventId, refreshVersion, realtimeVersion])

  const lastHour = Number(points.at(-1)?.hours_since_alert || 0)
  const axisMax = Math.max(24, Math.ceil(lastHour / 3) * 3)
  const data = useMemo(() => ({
    datasets: criteria.map((item) => ({
      label: `${item.label} (${weights?.[item.weightKey] ?? '—'}%)`,
      data: points.map((point) => ({ x: Number(point.hours_since_alert), y: point[item.key] == null ? null : Number(point[item.key]) })),
      borderColor: item.color,
      backgroundColor: item.color,
      borderWidth: 2,
      pointRadius: 3,
      pointHoverRadius: 5,
      tension: 0,
      spanGaps: false,
    })),
  }), [points, weights])
  const options = useMemo(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    interaction: { mode: 'nearest', axis: 'x', intersect: false },
    plugins: {
      legend: { display: true, position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 18 } },
      tooltip: { callbacks: { title: (items) => `${items[0]?.parsed.x ?? 0}h since alert`, label: (item) => `${item.dataset.label}: ${item.parsed.y == null ? 'No denominator' : `${item.parsed.y.toFixed(1)}% open`}` } },
    },
    scales: {
      x: { type: 'linear', min: 0, max: axisMax, title: { display: true, text: 'Hours since the event alert' }, ticks: { stepSize: 3, callback: (hour) => `${hour}h`, maxRotation: 0 }, grid: { color: '#eef2f6' } },
      y: { min: 0, max: 100, title: { display: true, text: 'Share still open' }, ticks: { stepSize: 10, callback: (value) => `${value}%` }, grid: { color: '#dce5ed' } },
    },
  }), [axisMax])

  return (
    <Panel title="Rescue criteria over time" className="rescue-criteria-panel">
      {error && <p role="alert">{error}</p>}
      <p className="rescue-criteria-hint">Share still open for each criterion, by hours since the event alert. Lower is better.</p>
      <div className="rescue-criteria-plot" role="img" aria-label="Line graph of the four rescue priority criteria over time">
        <Line data={data} options={options} />
      </div>
      <p className="rescue-criteria-hint">
        {points.length === 0
          ? 'No active event measurements.'
          : `Weights are fixed settings; plotted open shares update automatically.${lastUpdated ? ` Last update ${lastUpdated.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}.` : ''}`}
      </p>
    </Panel>
  )
}
