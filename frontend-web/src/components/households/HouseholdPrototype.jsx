import { statusColor } from '../../utils/statusPalette'
import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'

import { statuses, bandColor } from '../../utils/householdPrototype'

const factors = [
  { key: 'impact', label: 'Unsafe households', color: statusColor('unsafe') },
  { key: 'special_needs', label: 'Special needs', color: statusColor('warning') },
  { key: 'unreported', label: 'Unchecked', color: statusColor('unchecked') },
  { key: 'no_contact', label: 'No mobile / geotag', color: '#1b3050' },
]
export function HouseholdStatus({ status }) {
  return <span className="bg" style={{ '--c': statusColor(status?.key) }}>{status?.label || 'Unchecked'}</span>
}
export function RefreshButton({ onClick, loading }) {
  return <button type="button" className="bt ib" onClick={onClick} disabled={loading} title="Refresh" aria-label="Refresh">↻</button>
}
export function EventBadge({ event, loading }) {
  return <span className={`ev ${!event ? 'off' : ''}`}><i />{loading ? 'Loading event…' : event?.name || event?.event_name || 'Standby'}</span>
}
export function HouseholdChart({ rows, mode, setMode, selected, onSelect, emptyMessage = 'No sitio data available for this event.' }) {
  const [isolate, setIsolate] = useState(null)
  const [tooltip, setTooltip] = useState(null)
  const statusMode = mode === 'status'
  const keys = statusMode ? statuses : factors
  return <div className="cd">
    <div className="ch"><div className="sg2">{['status', 'prio'].map((key, i) => <button type="button" key={key} className={mode === key ? 'on' : ''} aria-pressed={mode === key} onClick={() => { setMode(key); setIsolate(null) }}>{i ? 'Priority' : 'Status'}</button>)}</div><span className="sp" /><div className="lgd">{keys.map((item, i) => <button type="button" key={item.key} className={isolate !== null && isolate !== i ? 'off' : ''} aria-pressed={isolate === i} onClick={() => setIsolate(isolate === i ? null : i)}><i style={{ background: item.color }} />{item.label}</button>)}</div></div>
    <div className={`cht${rows.length ? '' : ' is-empty'}`} role="group" aria-label="Household sitio chart"><div className="yax">{[100, 75, 50, 25, 0].map(tick => <span key={tick}>{tick}%</span>)}</div><div className="plot"><div className="pin" style={{ minWidth: `${Math.max(1, rows.length) * 78}px` }}><div className="area">{[0, 25, 50, 75, 100].map(tick => <div key={tick} className="gl" style={{ bottom: `${tick}%` }} />)}{!rows.length && <div className="chart-empty-message" role="status">{emptyMessage}</div>}<div className={`cols ${selected !== null ? 'sel' : ''}`}>
      {rows.map(row => {
        const values = keys.map(item => Number((statusMode ? row.status_counts : row.contributions)?.[item.key] || 0))
        const total = Number(row.households || 0)
        const tooltip = `${row.sitio}\n${keys.map((item, i) => `${item.label}: ${statusMode ? `${values[i]} · ${total ? Math.round(values[i] / total * 100) : 0}%` : `${values[i].toFixed(1)} pts`}`).join('\n')}`
        function showTip(event) {
          const rect = event.currentTarget.getBoundingClientRect()
          setTooltip({ row, values, left: Math.max(8, Math.min((event.clientX || rect.left) + 14, window.innerWidth - 230)), top: Math.max(8, Math.min((event.clientY || rect.top) + 14, window.innerHeight - 155)) })
        }
        return <button type="button" key={row.sitio_id} className={row.sitio_id === selected ? 'on' : ''} aria-label={tooltip} onPointerMove={showTip} onPointerLeave={() => setTooltip(null)} onFocus={showTip} onBlur={() => setTooltip(null)} onClick={() => { setTooltip(null); onSelect(row) }}><div className="bar" style={{ height: `${statusMode ? total ? 100 : 0 : row.priority_score}%` }}>{values.map((value, i) => <i key={keys[i].key} style={{ flex: `${value} 1 0`, background: keys[i].color, opacity: isolate === null || isolate === i ? 1 : .18 }} />)}</div></button>
      })}
    </div></div><div className="xls">{rows.map(row => <span key={row.sitio_id}>{row.sitio}</span>)}</div></div></div></div>
    {rows.length > 0 && tooltip && <div className="chart-tooltip" role="tooltip" style={{ left: tooltip.left, top: tooltip.top }}><div className="h"><span>{tooltip.row.sitio}</span>{!statusMode && <span>{Math.round(tooltip.row.priority_score)}%</span>}</div>{keys.map((item, i) => <div key={item.key}><span>{item.label}</span><b>{statusMode ? `${tooltip.values[i]} · ${tooltip.row.households ? Math.round(tooltip.values[i] / tooltip.row.households * 100) : 0}%` : `${tooltip.values[i].toFixed(1)} pts`}</b></div>)}</div>}
  </div>
}
export function SitioDialog({ row, onClose }) {
  const ref = useRef(null)
  useEffect(() => {
    if (!row) return
    const previous = document.activeElement
    ref.current?.focus()
    function keydown(event) {
      if (event.key === 'Escape') onClose()
      if (event.key === 'Tab') {
        const elements = ref.current?.querySelectorAll('button, a[href]')
        const first = elements?.[0], last = elements?.[elements.length - 1]
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
      }
    }
    document.addEventListener('keydown', keydown)
    return () => { document.removeEventListener('keydown', keydown); previous?.focus() }
  }, [row, onClose])
  if (!row) return null
  const path = purok => `/households/_household-list?${new URLSearchParams({ sitio_id: String(row.sitio_id), ...(purok ? { purok_id: String(purok.purok_id) } : {}) })}`
  return <div className="ov on" onClick={event => { if (event.target === event.currentTarget) onClose() }}><div className="md" ref={ref} tabIndex={-1} role="dialog" aria-modal="true" aria-labelledby="hh-sitio-title">
    <div className="mh2"><h3 id="hh-sitio-title">{row.sitio}</h3><span className="pl" style={{ '--c': bandColor(row.band) }}>{row.band?.label}</span><span className="sp" /><button type="button" className="x" aria-label="Close" onClick={onClose}>✕</button></div>
    <div className="pks">{row.puroks?.length ? row.puroks.map(purok => <Link className="pk" key={purok.purok_id} to={path(purok)}><div className="t"><b>{purok.purok_name}</b><span className="mu">{purok.household_count}</span></div><div className="mini">{statuses.map(status => <i key={status.key} style={{ flex: Number(purok.status_counts?.[status.key] || 0), background: status.color }} />)}</div></Link>) : <div className="emp">No puroks recorded for this sitio.</div>}</div>
    <div className="mf"><Link className="bt p" to={path()}>All households</Link></div>
  </div></div>
}
export function HouseholdPagination({ meta, onPageChange, loading }) {
  const current = Number(meta?.current_page || 1), total = Number(meta?.total || 0)
  return <div className="pgr"><span>{total ? `${meta.from}–${meta.to} of ${total}` : '0'}</span><span className="sp" /><button type="button" disabled={loading || current <= 1} onClick={() => onPageChange(current - 1)} aria-label="Previous page">‹</button><button type="button" disabled={loading || current >= Number(meta?.last_page || 1)} onClick={() => onPageChange(current + 1)} aria-label="Next page">›</button></div>
}
