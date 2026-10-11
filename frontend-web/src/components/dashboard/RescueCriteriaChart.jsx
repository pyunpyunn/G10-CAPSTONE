import { useEffect, useId, useMemo, useState } from 'react'
import { Chart as ChartJS, LinearScale, CategoryScale, PointElement, LineElement, Tooltip, Legend, Title } from 'chart.js'
import { createPortal } from 'react-dom'
import { Line } from 'react-chartjs-2'
import { getRescueCriteriaFeed } from '../../api/dispatchApi'
import Panel from '../ui/Panel'
import { useRealtimeVersion } from '../../utils/useRealtimeVersion'

ChartJS.register(LinearScale, CategoryScale, PointElement, LineElement, Tooltip, Legend, Title)

const sitioColors = [
  '#2563eb', '#dc2626', '#059669', '#7c3aed', '#d97706', '#0891b2',
  '#db2777', '#65a30d', '#4f46e5', '#ea580c', '#0d9488', '#9333ea',
  '#a16207', '#0284c7', '#be123c', '#15803d', '#6d28d9', '#475569',
]
const criterionLabels = { unsafe_reports: 'Unsafe reports', special_needs: 'Special needs', no_contact: 'No contact', unreported_members: 'Unreported members' }

function seriesSignature(series) {
  return JSON.stringify(series)
}

export default function RescueCriteriaChart({ eventId }) {
  const realtimeVersion = useRealtimeVersion(['households', 'dispatch', 'disasters'])
  const [points, setPoints] = useState([])
  const [error, setError] = useState('')
  const [timing, setTiming] = useState(null)
  const [clockNow, setClockNow] = useState(() => performance.now())
  const [hiddenSitios, setHiddenSitios] = useState(() => new Set())
  const [legendExpanded, setLegendExpanded] = useState(true)
  const legendId = useId()
  const tooltipId = useId()
  const [legendTooltip, setLegendTooltip] = useState(null)
  const tooltipSitio = points.find((sitio) => sitio.sitio_id === legendTooltip?.sitioId)
  const tooltipPoint = tooltipSitio?.points?.at(-1)

  function showLegendTooltip(event, sitioId) {
    const rect = event.currentTarget.getBoundingClientRect()
    const width = Math.min(340, window.innerWidth - 24)
    setLegendTooltip({
      sitioId, width,
      left: Math.max(12, Math.min(rect.left + rect.width / 2 - width / 2, window.innerWidth - width - 12)),
      top: rect.top >= 280 ? rect.top - 8 : rect.bottom + 8,
      placement: rect.top >= 280 ? 'above' : 'below',
    })
  }

  useEffect(() => {
    const dismiss = () => setLegendTooltip(null)
    const keydown = (event) => { if (event.key === 'Escape') dismiss() }
    window.addEventListener('scroll', dismiss, true)
    window.addEventListener('resize', dismiss)
    window.addEventListener('keydown', keydown)
    return () => {
      window.removeEventListener('scroll', dismiss, true)
      window.removeEventListener('resize', dismiss)
      window.removeEventListener('keydown', keydown)
    }
  }, [])

  function toggleSitio(sitioId) {
    setHiddenSitios((current) => {
      const next = new Set(current)
      if (next.has(sitioId)) next.delete(sitioId)
      else next.add(sitioId)
      return next
    })
  }

  useEffect(() => {
    const timer = window.setInterval(() => {
      if (!document.hidden) setClockNow(performance.now())
    }, 1000)
    return () => window.clearInterval(timer)
  }, [])

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
        const nextPoints = Array.isArray(feed.data) ? feed.data.filter((sitio) => !sitio.is_demo) : []
        const receivedAt = performance.now()
        const startedAt = Date.parse(feed.meta?.started_at)
        const serverTime = Date.parse(feed.meta?.ended_at || feed.meta?.server_time)
        setTiming({
          eventId: feed.meta?.event_id,
          elapsedHours: Number.isFinite(startedAt) && Number.isFinite(serverTime)
            ? Math.max(0, (serverTime - startedAt) / 3600000)
            : Math.max(0, ...nextPoints.map((sitio) => Number(sitio.points?.at(-1)?.hours_since_alert || 0))),
          running: Boolean(feed.meta?.event_id && !feed.meta?.ended_at),
          receivedAt,
        })
        setClockNow(receivedAt)
        setPoints((current) => seriesSignature(current) === seriesSignature(nextPoints) ? current : nextPoints)
        setError('')
      } catch {
        if (!cancelled) setError('Rescue criteria could not be loaded. Retrying automatically.')
      } finally {
        controller = null
        if (!cancelled) {
          if (refreshPending) timer = window.setTimeout(refresh, 0)
          else timer = window.setTimeout(refresh, 15000)
          refreshPending = false
        }
      }
    }

    refresh()
    window.addEventListener('focus', refresh)
    window.addEventListener('online', refresh)
    window.addEventListener('rescue-criteria:changed', refresh)

    document.addEventListener('visibilitychange', refresh)
    return () => {
      cancelled = true
      controller?.abort()
      window.clearTimeout(timer)
      window.removeEventListener('focus', refresh)
      window.removeEventListener('online', refresh)
      window.removeEventListener('rescue-criteria:changed', refresh)

      document.removeEventListener('visibilitychange', refresh)
    }
  }, [eventId, realtimeVersion])

  const lastHour = Math.max(0, ...points.map((sitio) => Number(sitio.points?.at(-1)?.hours_since_alert || 0)))
  const elapsedHours = Math.max(lastHour, (timing?.elapsedHours || 0)
    + (timing?.running ? Math.max(0, clockNow - timing.receivedAt) / 3600000 : 0))
  const axisMax = Math.max(1 / 3600, elapsedHours)
  const elapsedSeconds = Math.floor(elapsedHours * 3600)
  const elapsedLabel = `${Math.floor(elapsedSeconds / 3600)}h ${String(Math.floor(elapsedSeconds / 60) % 60).padStart(2, '0')}m ${String(elapsedSeconds % 60).padStart(2, '0')}s`
  const plotSeries = useMemo(() => points.map((sitio) => {
    const ordered = [...new Map((sitio.points || []).map((point) => [Number(point.hours_since_alert), point])).values()]
      .sort((a, b) => Number(a.hours_since_alert) - Number(b.hours_since_alert))
    const latest = ordered.at(-1)
    if (latest && elapsedHours > Number(latest.hours_since_alert)) {
      ordered.push({ ...latest, hours_since_alert: elapsedHours, carriedForward: true })
    }
    return { ...sitio, points: ordered }
  }), [points, elapsedHours])
  const data = useMemo(() => ({
    datasets: plotSeries.map((sitio, index) => ({
      label: `${sitio.sitio}${sitio.is_demo ? ' (demo)' : ''}`,
      hidden: hiddenSitios.has(sitio.sitio_id),
      data: sitio.points.map((point) => ({
        x: Number(point.hours_since_alert), y: point.purok_impact == null ? null : Number(point.purok_impact),
        criteria: point.criteria, contributions: point.contributions, counts: point.counts,
        isEmpty: point.is_empty, isDemo: sitio.is_demo, carriedForward: point.carriedForward,
      })),
      borderColor: sitioColors[index % sitioColors.length],
      backgroundColor: sitioColors[index % sitioColors.length],
      borderWidth: 1.2,
      borderDash: sitio.is_demo ? [5, 3] : [],
      pointRadius: (context) => context.raw?.carriedForward ? 0 : 2,
      pointHoverRadius: 4,
      pointBorderWidth: 1,
      clip: false,
      tension: 0.35,
      cubicInterpolationMode: 'monotone',
      spanGaps: false,
    })),
  }), [plotSeries, hiddenSitios])
  const options = useMemo(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    layout: { padding: { top: 8, right: 8, bottom: 8, left: 8 } },
    events: [],
    plugins: {
      legend: { display: false },
      tooltip: { enabled: false },
    },
    scales: {
      x: {
        type: 'linear', min: 0, max: axisMax,
        title: { display: true, text: 'Hours since the disaster broadcast' },
        afterBuildTicks: (scale) => {
          const step = axisMax < 3 ? axisMax / 4 : 3 * Math.max(1, Math.ceil(axisMax / 45))
          const ticks = []
          for (let hour = 0; hour < axisMax - step * 0.25; hour += step) ticks.push({ value: hour })
          ticks.push({ value: axisMax })
          scale.ticks = ticks
        },
        ticks: { autoSkip: false, callback: (hour) => `${Number(hour.toFixed(2))}h`, maxRotation: 0 },
        grid: { color: '#eef2f6' },
      },
      y: { min: 0, max: 100, title: { display: true, text: 'Weighted purok impact per sitio' }, ticks: { stepSize: 10, callback: (value) => `${value}%` }, grid: { color: '#dce5ed' } },
    },
  }), [axisMax])

  return (
    <Panel title="Purok impact by sitio over time" className="rescue-criteria-panel" action={timing?.eventId ? <span className="rescue-criteria-elapsed">Elapsed {elapsedLabel}</span> : null}>
      {error && <p role="alert">{error}</p>}
      <div className="rescue-criteria-plot" role="img" aria-label="Line graph of weighted purok impact for each sitio over the disaster duration">
        <Line data={data} options={options} />
      </div>
      {points.length > 0 && (
        <div className="rescue-criteria-sitios">
          <div className="rescue-criteria-legend-controls">
            <button type="button" aria-expanded={legendExpanded} aria-controls={legendId} onClick={() => { setLegendExpanded((current) => !current); setLegendTooltip(null) }}>
              {legendExpanded ? 'Collapse sitios' : 'Expand sitios'} ({points.filter((sitio) => !hiddenSitios.has(sitio.sitio_id)).length}/{points.length} visible)
            </button>
            {legendExpanded && (
              <div className="rescue-criteria-legend-actions">
                <button type="button" onClick={() => setHiddenSitios(new Set())}>Show all</button>
                <button type="button" onClick={() => setHiddenSitios(new Set(points.map((sitio) => sitio.sitio_id)))}>Hide all</button>
              </div>
            )}
          </div>
          <div id={legendId} className="rescue-criteria-legend" hidden={!legendExpanded} role="group" aria-label="Toggle sitio lines">
            {points.map((sitio, index) => (
              <button key={sitio.sitio_id} type="button" aria-pressed={!hiddenSitios.has(sitio.sitio_id)}
                className={hiddenSitios.has(sitio.sitio_id) ? 'is-hidden' : ''} onClick={() => toggleSitio(sitio.sitio_id)}
                aria-describedby={legendTooltip?.sitioId === sitio.sitio_id ? tooltipId : undefined}
                onMouseEnter={(event) => showLegendTooltip(event, sitio.sitio_id)}
                onMouseLeave={(event) => { if (event.currentTarget !== document.activeElement) setLegendTooltip(null) }}
                onFocus={(event) => showLegendTooltip(event, sitio.sitio_id)} onBlur={() => setLegendTooltip(null)}>

                <i aria-hidden="true" style={{ backgroundColor: sitioColors[index % sitioColors.length] }} />
                {sitio.sitio}{sitio.is_demo ? ' (demo)' : ''}
              </button>
            ))}
          </div>
        </div>
      )}
      {legendExpanded && tooltipSitio && tooltipPoint && createPortal(
        <div id={tooltipId} role="tooltip" className={`rescue-criteria-tooltip rescue-criteria-tooltip--${legendTooltip.placement}`}
          style={{ left: legendTooltip.left, top: legendTooltip.top, width: legendTooltip.width }}>
          <strong className="rescue-criteria-tooltip-title">{tooltipSitio.sitio}{tooltipSitio.is_demo ? ' (demo)' : ''}</strong>
          <p className="rescue-criteria-tooltip-time">Latest measurement: {Number(Number(tooltipPoint.hours_since_alert).toFixed(2))}h since broadcast</p>
          <p className="rescue-criteria-tooltip-impact">Purok impact: {tooltipPoint.purok_impact == null ? 'No measurement' : `${Number(tooltipPoint.purok_impact).toFixed(1)}%`}</p>
          {tooltipPoint.is_empty ? <p>No registered households; empty baseline.</p> : (
            <dl className="rescue-criteria-tooltip-breakdown">
              {Object.entries(criterionLabels).map(([key, label]) => (
                <div key={key}>
                  <dt>{label}</dt>
                  <dd>{tooltipPoint.criteria?.[key] == null ? 'No eligible population' : `${Number(tooltipPoint.criteria[key]).toFixed(1)}%`}
                    <small>{Number(tooltipPoint.contributions?.[key] || 0).toFixed(2)} weighted points</small>
                  </dd>
                </div>
              ))}
            </dl>
          )}
          {tooltipSitio.is_demo && <p className="rescue-criteria-tooltip-note">Demo data (not a measured sitio).</p>}
        </div>, document.body,
      )}
      {points.length === 0 && <p className="rescue-criteria-hint">No active disaster event.</p>}
    </Panel>
  )
}
