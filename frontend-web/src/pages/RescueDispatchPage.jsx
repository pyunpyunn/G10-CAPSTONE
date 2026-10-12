import { useCallback, useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { ArrowRight } from 'lucide-react'
import { getDispatchDashboard, getSitioPriorities } from '../api/dispatchApi'
import DispatchSummary from '../components/dispatch/DispatchSummary'
import DispatchStatusBadge from '../components/dispatch/DispatchStatusBadge'
import DispatchSidePanel from '../components/dispatch/DispatchSidePanel'
import FieldCommunicationPanel from '../components/dispatch/FieldCommunicationPanel'
import { ResponseOperationsLayout } from '../components/ui/PageHeader'
import { useModuleData } from '../utils/useModuleData'
import { emptySummary, groupSitioDeployments } from '../utils/dispatchHelpers'

export default function RescueDispatchPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const [open, setOpen] = useState(undefined)
  const loader = useCallback(async () => {
    const [workspace, priorities] = await Promise.allSettled([getDispatchDashboard({ per_page: 5 }), getSitioPriorities()])
    if (workspace.status === 'rejected') throw workspace.reason
    return { ...workspace.value, priorities: groupSitioDeployments(priorities.status === 'fulfilled' && Array.isArray(priorities.value) ? priorities.value : [], workspace.value?.risk_areas || [], workspace.value?.active_dispatches || []), priorityError: priorities.status === 'rejected' ? 'Rescue priorities could not be loaded. Please refresh.' : '' }
  }, [])
  const { data, error, loading } = useModuleData(loader, ['dispatch', 'households', 'disasters', 'accounts'])
  const selected = open === undefined ? data?.priorities?.[0]?.sitio_id : open
  useEffect(() => { if (location.state?.selectedHousehold) navigate('/rescue-management/new', { replace: true, state: location.state }) }, [location.state, navigate])
  return <ResponseOperationsLayout><section className="pg on">
    {error && <div className="form-error" role="alert">{error}</div>}
    {loading && <div className="empty-reference" role="status">Loading dashboard…</div>}
    {!loading && data && <>
      {!data.active_event && <div className="nb">No active event · dispatch disabled</div>}
      <div className="dash"><DispatchSummary summary={data.summary || emptySummary()} /><div className="r2">
        <div className="cd pri"><div className="ch">Rescue Priority</div><div className="cb">
          {data.priorityError ? <div className="form-error" role="alert">{data.priorityError}</div> : !data.priorities.length ? <div className="empty-reference">No rescue priorities</div> : [
            { title:'Awaiting deployment', rows:data.priorities.filter((row) => !row.activeDispatches.length) },
            { title:'Deployed sitios', rows:data.priorities.filter((row) => row.activeDispatches.length) },
          ].map((list) => <section className="sitio-priority-list" key={list.title} aria-label={list.title}><h3>{list.title}<span>{list.rows.length}</span></h3>{!list.rows.length ? <div className="mu sitio-list-empty">{list.title === 'Deployed sitios' ? 'No sitios have active dispatches.' : 'All listed sitios have active dispatches.'}</div> : list.rows.map((row) => {
            const color = row.band?.key === 'high' ? '#dc2626' : row.band?.key === 'moderate' ? '#d97706' : '#2563eb'
            const statuses = [...new Map(row.activeDispatches.map((assignment) => [assignment.status.key, assignment.status])).values()]
            const rescuerIds = new Set(row.activeDispatches.flatMap((assignment) => assignment.selected_responder_ids || (assignment.responder_id ? [assignment.responder_id] : [])).map(String))
            return <div key={row.sitio_id} className={`pr${selected === row.sitio_id ? ' on' : ''}`}><button className="ph2" type="button" aria-expanded={selected === row.sitio_id} onClick={() => setOpen(selected === row.sitio_id ? null : row.sitio_id)}><span className="rk">{row.rank}</span><b>{row.sitio}</b><span className="sc"><i style={{ width: `${Math.max(0, Math.min(100, Number(row.priority_score) || 0))}%`, background: color }} /></span><em>{row.priority_score}%</em><span className="pl" style={{ '--c': color }}>{row.band?.label || 'Low'}</span></button>
              {!!row.activeDispatches.length && <div className="sitio-deployment-status">{statuses.map((status) => <DispatchStatusBadge key={status.key} status={status} />)}<span className="mu">{rescuerIds.size} assigned rescuers · {row.activeDispatches.length} active assignments</span></div>}
              <div className="px"><div className="sitio-priority-detail"><div className="xr"><div className="mts"><span>Households <b>{row.households}</b></span><span>Unsafe <b>{row.impacted_households}</b></span><span>Unreported <b>{row.unreported_members}</b></span><span>No contact <b>{row.no_contact_households}</b></span></div><button className="go" type="button" disabled={!data.active_event} onClick={() => navigate('/rescue-management/new', { state: { selectedSitio: row } })}>Dispatch <ArrowRight size={12} /></button></div>
              {!!row.activeDispatches.length && <div className="sitio-assignment-list">{row.activeDispatches.map((assignment) => <Link key={assignment.assignment_id} to={`/rescue-management/${assignment.assignment_id}/edit`}><span><b>{assignment.assignment_code}</b> · {assignment.team_name}<small>{assignment.assigned_area}</small></span><DispatchStatusBadge status={assignment.status} /><ArrowRight size={12} /></Link>)}</div>}
              </div></div></div>
          })}</section>)}
        </div></div><FieldCommunicationPanel compact recordingsOnly />
      </div></div><DispatchSidePanel teams={data.team_coverage || []} responders={data.responders || []} />
    </>}
  </section></ResponseOperationsLayout>
}
