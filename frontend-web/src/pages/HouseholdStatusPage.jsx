import { bandColor } from '../utils/householdPrototype'
import { useCallback, useState } from 'react'
import { Link, useNavigate, useOutletContext } from 'react-router-dom'
import { canOpenWebPage } from '../utils/personnelAccess'
import { getDispatchDashboard, getSitioPriorities } from '../api/dispatchApi'
import { useModuleData } from '../utils/useModuleData'
import { HouseholdChart } from '../components/households/HouseholdPrototype'

export default function HouseholdStatusPage() {
  const { user } = useOutletContext()
  const navigate = useNavigate()
  const [mode, setMode] = useState('status')
  const [selected, setSelected] = useState(null)
  const loader = useCallback(async () => {
    const [rows, workspace] = await Promise.all([getSitioPriorities(), getDispatchDashboard({ per_page: 1 })])
    return { rows, event: workspace.active_event, sitioCoverage: workspace.sitio_coverage || [] }
  }, [])
  const { data, error, loading } = useModuleData(loader, ['households', 'disasters'])
  const rows = data?.rows || []
  const assignedSitios = new Set((data?.sitioCoverage || []).filter(item => item.is_assigned).map(item => String(item.sitio_id)))
  const rankingGroups = [
    { title: 'Awaiting assignment', rows: rows.filter(row => !assignedSitios.has(String(row.sitio_id))) },
    { title: 'Already assigned sitios', rows: rows.filter(row => assignedSitios.has(String(row.sitio_id))) },
  ]
  function select(row) {
    setSelected(row.sitio_id)
    navigate(`/households/_household-list?${new URLSearchParams({ sitio_id: String(row.sitio_id) })}`)
  }
  return <section className="hh-prototype" aria-label="Household status">
    {error && <div className="emp" role="alert">{error}</div>}
    {loading ? <div className="cd sby" role="status">Loading household status…</div> : data && !data.event ? <div className="cd sby"><div>Standby<small>Household reporting starts after HQ/Admin broadcasts an active event.</small></div></div> : data && <div className="ovg">
      <HouseholdChart rows={rows} mode={mode} setMode={setMode} selected={selected} onSelect={select} />
      <div className="cd"><div className="ch">Ranking</div><div className="cb">
        {!rows.length && <div className="emp">No sitio ranking available</div>}
        {rankingGroups.filter(group => group.rows.length).map(group => <section className="sitio-priority-list" key={group.title} aria-label={group.title}>
          <h3>{group.title}<span>{group.rows.length}</span></h3>
          {group.rows.map(row => <div key={row.sitio_id} className={`rk ${row.sitio_id === selected ? 'on' : ''}`}>
            <button type="button" className="rh" onClick={() => select(row)} aria-label={`View ${row.sitio} households`}><span className="rn">{row.rank}</span><b>{row.sitio}</b><span className="sc"><i style={{ width: `${row.priority_score}%`, background: bandColor(row.band) }} /></span><em>{Math.round(row.priority_score)}%</em>{!assignedSitios.has(String(row.sitio_id)) && <span className="pl" style={{ '--c': bandColor(row.band) }}>{row.band?.label || 'Low'}</span>}</button>
            <div className="rx"><div><div className="mts"><span>Households <b>{row.households}</b></span><span>Unsafe <b>{row.impacted_households}</b></span><span>Special needs <b>{row.special_needs_members}</b></span><span>Unchecked members <b>{row.unchecked_members}</b></span><span>No mobile / geotag <b>{row.no_contact_households}</b></span>{canOpenWebPage(user, '/rescue-management/new') && data.event && row.households > 0 && <Link className="bt p sm sitio-dispatch" to={`/rescue-management/new?sitio_id=${row.sitio_id}`} aria-label={`Dispatch to ${row.sitio}`}>Dispatch →</Link>}</div></div></div>
          </div>)}
        </section>)}
      </div></div>
    </div>}
  </section>
}
