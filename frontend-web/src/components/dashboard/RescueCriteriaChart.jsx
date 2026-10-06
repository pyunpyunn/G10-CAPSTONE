import { useEffect, useMemo, useState } from 'react'
import { Chart as ChartJS, LinearScale, CategoryScale, PointElement, LineElement, Tooltip, Legend, Title } from 'chart.js'
import { Line } from 'react-chartjs-2'
import { getRescueCriteriaTimeline } from '../../api/dispatchApi'
import EmptyState from '../ui/EmptyState'
import Panel from '../ui/Panel'

ChartJS.register(LinearScale, CategoryScale, PointElement, LineElement, Tooltip, Legend, Title)

const criteria = [
  { key: 'impact', label: 'Purok impact', color: '#378ADD' },
  { key: 'special_needs', label: 'Special needs', color: '#7F77DD' },
  { key: 'unreported', label: 'Unreported members', color: '#EF9F27' },
  { key: 'no_contact', label: 'No contact channel', color: '#D85A30' },
]

export default function RescueCriteriaChart({ eventId, refreshVersion }) {
  const [points, setPoints] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [hidden, setHidden] = useState([])

  useEffect(() => {
    let cancelled = false
    if (!eventId) return () => { cancelled = true }
    Promise.resolve().then(() => {
      if (cancelled) return []
      setLoading(true)
      setError('')
      return getRescueCriteriaTimeline()
    }).then((data) => { if (!cancelled) setPoints(Array.isArray(data) ? data : []) })
      .catch(() => { if (!cancelled) setError('Rescue criteria could not be loaded. Refresh to try again.') })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [eventId, refreshVersion])

  const lastHour = Number(points.at(-1)?.hours_since_alert || 0)
  const axisMax = Math.max(20, Math.ceil(lastHour / 20) * 20)
  const data = useMemo(() => ({
    datasets: criteria.filter((item) => !hidden.includes(item.key)).map((item) => ({
      label: item.label,
      data: points.map((point) => ({ x: Number(point.hours_since_alert), y: point[item.key] == null ? null : Number(point[item.key]) })),
      borderColor: item.color,
      backgroundColor: item.color,
      borderWidth: 2.5,
      pointRadius: 2.5,
      pointHoverRadius: 5,
      tension: 0.3,
      spanGaps: false,
    })),
  }), [points, hidden])
  const options = useMemo(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    interaction: { mode: 'nearest', axis: 'x', intersect: false },
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { title: (items) => `${items[0]?.parsed.x ?? 0}h since alert`, label: (item) => `${item.dataset.label}: ${item.parsed.y == null ? 'No denominator' : `${item.parsed.y.toFixed(1)}%`}` } },
    },
    scales: {
      x: { type: 'linear', min: 0, max: axisMax, title: { display: true, text: 'Hours since the event alert' }, ticks: { stepSize: 20, callback: (hour) => `${hour}h`, maxRotation: 0 }, grid: { color: '#eef2f6' } },
      y: { min: 0, max: 100, title: { display: true, text: 'Share still open' }, ticks: { stepSize: 25, callback: (value) => `${value}%` }, grid: { color: '#dce5ed' } },
    },
  }), [axisMax])

  return (
    <Panel title="Rescue criteria over time" className="rescue-criteria-panel">
      {!eventId ? <EmptyState title="No active event" message="Rescue criteria appear when a disaster event is active." />
        : loading && points.length === 0 ? <p role="status">Loading rescue criteria…</p>
          : error ? <p role="alert">{error}</p>
            : points.length === 0 ? <EmptyState title="No measurements yet" message="Criteria will appear after the first event measurement." />
              : <>
                <div className="rescue-criteria-legend">
                  {criteria.map((item) => <button type="button" key={item.key} aria-pressed={!hidden.includes(item.key)} className={hidden.includes(item.key) ? 'is-hidden' : ''} onClick={() => setHidden((current) => current.includes(item.key) ? current.filter((key) => key !== item.key) : [...current, item.key])}><i style={{ background: item.color }} />{item.label} <b>{points.at(-1)?.[item.key] == null ? '—' : `${Number(points.at(-1)[item.key]).toFixed(1)}%`}</b></button>)}
                </div>
                <div className="rescue-criteria-plot" role="img" aria-label="Line graph of the four rescue priority criteria over time">
                  <Line data={data} options={options} />
                </div>
              </>}
    </Panel>
  )
}
