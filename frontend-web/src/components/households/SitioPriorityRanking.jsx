import { useState } from 'react'
import EmptyState from '../ui/EmptyState'
import Modal from '../ui/Modal'
import Panel from '../ui/Panel'
import { Link } from 'react-router-dom'

const factors = [
  { key: 'impact', label: 'Sitio impact', color: '#378ADD' },
  { key: 'special_needs', label: 'Special needs', color: '#7F77DD' },
  { key: 'unreported', label: 'Unreported members', color: '#EF9F27' },
  { key: 'no_contact', label: 'No contact channel', color: '#D85A30' },
]

export function SitioPriorityChart({ rows, loading, onRefresh }) {
  const maxScore = Math.max(20, Math.ceil(Math.max(...rows.map((row) => Number(row.priority_score) || 0), 0) / 20) * 20)
  const ticks = Array.from({ length: maxScore / 20 + 1 }, (_, index) => maxScore - index * 20)
  const slotCount = Math.max(18, rows.length)
  const emptySlotCount = slotCount - rows.length

  return (
    <Panel title="Sitio priority by criterion" className="sitio-priority-panel" action={<button className="button secondary" type="button" onClick={onRefresh} disabled={loading}>Refresh</button>}>
      {rows.length === 0 ? <EmptyState title="No Sitio ranking available" message="Sitio priorities appear when an active event has catalogued sitios." /> : (
        <>
        <div className="sitio-priority-chart-scroll">
          <div className="sitio-priority-chart" style={{ minWidth: `${Math.max(600, slotCount * 34 + 50)}px` }} role="img" aria-label={`Vertical stacked graph of sitio rescue priority; ${rows.length} sitios with data and space for at least 18 sitios`}>
            <div className="sitio-priority-legend">{factors.map((factor) => <span key={factor.key}><i style={{ background: factor.color }} />{factor.label}</span>)}</div>
            <div className="sitio-priority-y-axis">{ticks.map((tick) => <span key={tick}>{tick}%</span>)}</div>
            <div className="sitio-priority-plot" style={{ '--grid-step': `${100 / (ticks.length - 1)}%`, '--slot-count': slotCount }}>
              {rows.map((row) => <div className="sitio-priority-column" key={row.sitio_id} title={`${row.sitio}: ${Number(row.priority_score).toFixed(1)}%`}>
                <div className="sitio-priority-bar-area">
                  <strong style={{ bottom: `calc(${Math.min(100, Number(row.priority_score) / maxScore * 100)}% + 3px)` }}>{Math.round(Number(row.priority_score))}%</strong>
                  <div className="sitio-priority-stack" style={{ height: `${Math.max(0, Math.min(100, Number(row.priority_score) / maxScore * 100))}%` }}>
                    {factors.map((factor) => <span key={factor.key} style={{ height: `${Number(row.priority_score) > 0 ? Number(row.contributions?.[factor.key] || 0) / Number(row.priority_score) * 100 : 0}%`, background: factor.color }} />)}
                  </div>
                </div>
                <span className="sitio-priority-x-label" title={row.sitio}>{row.sitio}</span>
              </div>)}
              {Array.from({ length: emptySlotCount }, (_, index) => (
                <div className="sitio-priority-column sitio-priority-column--empty" key={`empty-slot-${index}`} aria-hidden="true">
                  <div className="sitio-priority-bar-area" />
                  <span className="sitio-priority-x-label" />
                </div>
              ))}
            </div>
          </div>
        </div>
        </>
      )}
    </Panel>
  )
}

export function SitioPriorityList({ rows, selectedSitioId, onSelect }) {
  const [openSitioId, setOpenSitioId] = useState(null)
  const selectedSitio = rows.find((row) => String(row.sitio_id) === String(openSitioId))

  return (
    <>
      <Panel title="Sitio ranking" className="sitio-ranking-panel">
        {rows.length === 0 ? <EmptyState title="No Sitio ranking available" message="No ranked sitios for this event." /> : (
          <ol className="sitio-priority-list" aria-label="Sitio ranking list">
            {rows.map((row) => <li key={row.sitio_id} className={String(row.sitio_id) === String(selectedSitioId) ? 'is-selected' : ''}>
              <button className="sitio-priority-choice" type="button" aria-pressed={String(row.sitio_id) === String(selectedSitioId)} onClick={() => { onSelect(row.sitio_id); setOpenSitioId(row.sitio_id) }}>
                <span className="sitio-priority-rank">{row.rank}</span>
                <span className="sitio-priority-name">{row.sitio}</span>
                <span className={`sitio-priority-chip ${row.band?.key || 'low'}`}>{row.band?.label || 'Low'}</span>
                <strong>{Number(row.priority_score).toFixed(1)}%</strong>
              </button>
              <span className="sitio-priority-tooltip" role="tooltip">
                <b>{row.sitio} summary</b>
                <span>{row.households} households</span>
                <span>{row.impacted_households} unsafe / affected</span>
                <span>{row.unreported_members} unreported members</span>
                <span>{row.no_contact_households} no contact channel</span>
              </span>
            </li>)}
          </ol>
        )}
      </Panel>

      <Modal
        title={selectedSitio ? `${selectedSitio.sitio} puroks` : 'Sitio puroks'}
        isOpen={Boolean(selectedSitio)}
        onClose={() => setOpenSitioId(null)}
        className="sitio-purok-modal"
      >
        {selectedSitio && <div className="sitio-purok-list">
          <div className="sitio-purok-list-scroll">
            <section className="sitio-purok-group" aria-label={`${selectedSitio.sitio} puroks`}>
              <h4>
                <span>{selectedSitio.sitio}</span>
                <span className="sitio-purok-actions">
                  <span>{selectedSitio.puroks?.length || 0} puroks</span>
                  <Link
                    className="button secondary"
                    to={{ pathname: '/households/_household-list', search: `?${new URLSearchParams({ sitio_id: String(selectedSitio.sitio_id), sitio: selectedSitio.sitio })}` }}
                  >
                    View all households ({selectedSitio.households || 0})
                  </Link>
                </span>
              </h4>
              {selectedSitio.puroks?.length ? selectedSitio.puroks.map((purok) => <div className="sitio-purok-row" key={purok.purok_id}>
                <span>{purok.purok_name}</span>
                <small>{purok.household_count} households</small>
                <Link className="button secondary" to={{ pathname: '/households/_household-list', search: `?${new URLSearchParams({ sitio_id: String(selectedSitio.sitio_id), purok_id: String(purok.purok_id), sitio: selectedSitio.sitio, purok: purok.purok_name })}` }}>View sitio</Link>
              </div>) : <p className="sitio-purok-empty">No puroks recorded for this sitio.</p>}
            </section>
          </div>
        </div>}
      </Modal>
    </>
  )
}
