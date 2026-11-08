import { statusColor } from '../utils/statusPalette'
import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { ArrowRight } from 'lucide-react'
import { useWorkspaceQuery } from '../state/operationsApi'
import DispatchSummary from '../components/dispatch/DispatchSummary'
import DispatchStatusBadge from '../components/dispatch/DispatchStatusBadge'
import DispatchSidePanel from '../components/dispatch/DispatchSidePanel'
import FieldCommunicationPanel from '../components/dispatch/FieldCommunicationPanel'
import { ResponseOperationsLayout } from '../components/ui/PageHeader'
import { emptySummary } from '../utils/dispatchHelpers'

export default function RescueDispatchPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const [open, setOpen] = useState(undefined)
  const { data, error: queryError, isLoading: loading, refetch } = useWorkspaceQuery({ view: 'overview' })
  const error = getDispatchErrorMessage(queryError)
  const priorities = Array.isArray(data?.priorities) ? data.priorities.filter((row) => row && typeof row === 'object') : []
  const dispatchesFor = (row) => Array.isArray(row.activeDispatches) ? row.activeDispatches : []
  const selected = open === undefined ? priorities[0]?.sitio_id : open
  useEffect(() => { if (location.state?.selectedHousehold) navigate('/rescue-management/new', { replace: true, state: location.state }) }, [location.state, navigate])
  return <ResponseOperationsLayout><section className="pg on">
    {error && <div className="form-error" role="alert">{error}<button className="bt" type="button" onClick={refetch}>Retry</button></div>}
    {loading && <div className="empty-reference" role="status">Loading dashboard…</div>}
    {!loading && data && <>
      {!data.active_event && <div className="nb">No active event · dispatch disabled</div>}
      <div className="dash"><DispatchSummary summary={data.summary || emptySummary()} /><div className="r2">
        <div className="cd pri"><div className="ch">Rescue Priority</div><div className="cb">
          {data.priorityError ? <div className="form-error" role="alert">{data.priorityError}</div> : !priorities.length ? <div className="empty-reference">No rescue priorities</div> : [
            { title:'Awaiting assignment', rows:priorities.filter((row) => !dispatchesFor(row).length) },
            { title:'Already assigned sitios', rows:priorities.filter((row) => dispatchesFor(row).length) },
          ].map((list) => <section className="sitio-priority-list" key={list.title} aria-label={list.title}><h3>{list.title}<span>{list.rows.length}</span></h3>{!list.rows.length ? <div className="mu sitio-list-empty">{list.title === 'Already assigned sitios' ? 'No sitios have active dispatches.' : 'No sitios are awaiting assignment.'}</div> : list.rows.map((row) => {
            const color = statusColor(row.band?.key || 'low')
            const activeDispatches = dispatchesFor(row).filter((assignment) => assignment && typeof assignment === 'object')
            const statuses = [...new Map(activeDispatches.filter((assignment) => assignment.status?.key).map((assignment) => [assignment.status.key, assignment.status])).values()]
            const rescuerIds = new Set(activeDispatches.flatMap((assignment) => assignment.selected_responder_ids || (assignment.responder_id ? [assignment.responder_id] : [])).map(String))
            return <div key={row.sitio_id} className={`pr${selected === row.sitio_id ? ' on' : ''}`}><button className="ph2" type="button" aria-expanded={selected === row.sitio_id} onClick={() => setOpen(selected === row.sitio_id ? null : row.sitio_id)}><span className="rk">{row.rank}</span><b>{row.sitio}</b><span className="sc"><i style={{ width: `${Math.max(0, Math.min(100, Number(row.priority_score) || 0))}%`, background: color }} /></span><em>{row.priority_score}%</em><span className="pl" style={{ '--c': color }}>{row.band?.label || 'Low'}</span></button>
              {!!activeDispatches.length && <div className="sitio-deployment-status">{statuses.map((status) => <DispatchStatusBadge key={status.key} status={status} />)}<span className="mu">{rescuerIds.size} assigned rescuers · {activeDispatches.length} active assignments</span></div>}
              <div className="px"><div className="sitio-priority-detail"><div className="xr"><div className="mts"><span>Households <b>{row.households}</b></span><span>Unsafe <b>{row.impacted_households}</b></span><span>Unreported <b>{row.unreported_members}</b></span><span>No contact <b>{row.no_contact_households}</b></span></div><button className="go" type="button" disabled={!data.active_event} onClick={() => navigate('/rescue-management/new', { state: { selectedSitio: row } })}>Dispatch <ArrowRight size={12} /></button></div>
              {!!activeDispatches.length && <div className="sitio-assignment-list">{activeDispatches.map((assignment) => <Link key={assignment.assignment_id} to={`/rescue-management/${assignment.assignment_id}/edit`}><span><b>{assignment.assignment_code}</b> · {assignment.team_name}<small>{assignment.assigned_area}</small></span><DispatchStatusBadge status={assignment.status} /><ArrowRight size={12} /></Link>)}</div>}
              </div></div></div>
          })}</section>)}
        </div></div><FieldCommunicationPanel compact recordingsOnly />
      </div></div><DispatchSidePanel lazy />
    </>}
  </section></ResponseOperationsLayout>
}

function getDispatchErrorMessage(error) {
  if (!error) return ''

  if (error.status === 'NETWORK_ERROR') return 'Cannot connect to the server. Check your connection and try again.'
  if (error.status === 401) return 'Your session has expired. Sign in again to continue.'
  if (error.status === 403) return 'You do not have permission to view dispatch information.'
  if (error.status === 404) return 'Dispatch information was not found. Refresh and try again.'
  if (error.status === 'INVALID_RESPONSE') return error.message
  if (typeof error.status === 'number' && error.status >= 500) {
    return 'Dispatch information could not be loaded because of a server error. Please try again shortly.'
  }

  return 'Dispatch information could not be loaded. Please try again.'
}
