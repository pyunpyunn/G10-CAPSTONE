import { useState } from 'react'

export default function DispatchSummary({ summary }) {
  const [highlight, setHighlight] = useState(null)
  const total = Number(summary.dispatch_progress?.total) || 0
  const progress = [
    ['dispatched', 'Dispatched', '#3b82f6'], ['on_scene', 'On-scene', '#f59e0b'],
    ['returning', 'Returning', '#8b5cf6'], ['completed', 'Completed', '#0ea5e9'], ['cancelled', 'Cancelled', '#64748b'],
  ].map(([key, label, color]) => ({ key, label, color, value: Number(summary.dispatch_progress?.[key]) || 0 }))
  return <section className="cd strip nv" aria-label="Dispatch summary and progress">
    <div className="kk">
      <div className="kp"><span>Available</span><b>{summary.available_responders ?? summary.available ?? 0}</b><em>/ {summary.total_responders ?? summary.total_teams ?? 0}</em></div>
      <div className="kp"><span>Response rate</span><b>{summary.response_rate || 0}</b><em>%</em></div>
    </div>
    <div className="pg-b">
      <div className="bar2" role="img" aria-label={`${total} assignments: ${progress.map((item) => `${item.label} ${item.value}`).join(', ')}`}>
        {progress.map((item) => <i key={item.key} style={{ width: `${total ? item.value / total * 100 : 0}%`, background: item.color, opacity: highlight && highlight !== item.key ? .22 : 1 }} />)}
      </div>
      <div className="lg">{progress.map((item) => <button key={item.key} type="button" onMouseEnter={() => setHighlight(item.key)} onMouseLeave={() => setHighlight(null)} onFocus={() => setHighlight(item.key)} onBlur={() => setHighlight(null)}>
        <i style={{ background: item.color }} />{item.label} <b>{item.value}</b>
      </button>)}</div>
    </div>
  </section>
}
