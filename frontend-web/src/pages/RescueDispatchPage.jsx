import { useCallback, useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { getDispatchDashboard, getSitioPriorities } from '../api/dispatchApi'
import DispatchSummary from '../components/dispatch/DispatchSummary'
import DispatchSidePanel from '../components/dispatch/DispatchSidePanel'
import FieldCommunicationPanel from '../components/dispatch/FieldCommunicationPanel'
import EmptyState from '../components/ui/EmptyState'
import PageHeader from '../components/ui/PageHeader'
import LoadingState from '../components/ui/LoadingState'
import { useModuleData } from '../utils/useModuleData'
import { emptySummary } from '../utils/dispatchHelpers'

export default function RescueDispatchPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const [selectedSitioId, setSelectedSitioId] = useState(null)
  const loader = useCallback(async () => {
    const [workspace, priorities] = await Promise.allSettled([getDispatchDashboard({ per_page: 5 }), getSitioPriorities()])
    if (workspace.status === 'rejected') throw workspace.reason
    return { ...workspace.value, priorities: priorities.status === 'fulfilled' ? priorities.value : [],
      priorityError: priorities.status === 'rejected' ? 'Rescue priorities could not be loaded. Please refresh to try again.' : '' }
  }, [])
  const { data, error, loading, refresh } = useModuleData(loader)
  useEffect(() => {
    if (location.state?.selectedHousehold) navigate('/rescue-management/new', { replace: true, state: location.state })
  }, [location.state, navigate])
  return <main className="ops-page dispatch-page response-operations-page">
    <PageHeader title="Dispatch Dashboard" subtitle={data?.active_event?.name || data?.area_label || 'Response Operations'}
      actions={<div className="response-panel-actions"><button className="btn btn-secondary" type="button" onClick={refresh}>Refresh dashboard</button>
        <Link className="button review" to="/rescue-management">Rescue Management</Link></div>} />
    {error && <div className="form-error" role="alert">{error}</div>}
    {loading && <LoadingState />}
    {!loading && data && <>
      {!data.active_event && <div className="standby-strip"><strong>No active disaster event</strong><span>Dispatch creation becomes available when a disaster event is declared.</span></div>}
      <div className="dispatch-workspace-grid">
        <div className="dispatch-main-panel">
          <FieldCommunicationPanel compact recordingsOnly />
          <section className="panel sitio-ranking-panel dispatch-priority-panel" aria-label="Rescue Priority Panel">
            <div className="panel-head"><span className="panel-title">Rescue Priority</span></div>
            {data.priorityError ? <div className="form-error" role="alert">{data.priorityError}</div> : !data.priorities.length ?
              <EmptyState title="No sitio priorities yet" message="Sitio rankings appear when an active disaster event is available." /> :
              <ol className="sitio-priority-list" aria-label="Sitio ranking list">
                {data.priorities.map((row) => <li key={row.sitio_id} className={selectedSitioId === row.sitio_id ? 'is-selected' : ''}>
                  <button className="sitio-priority-choice" type="button" aria-pressed={selectedSitioId === row.sitio_id}
                    aria-describedby={`dispatch-sitio-${row.sitio_id}`} onClick={() => setSelectedSitioId(row.sitio_id)}>
                    <span className="sitio-priority-rank">{row.rank}</span>
                    <span className="sitio-priority-name">{row.sitio}</span>
                    <span className={`sitio-priority-chip ${row.band?.key || 'low'}`}>{row.band?.label || 'Low'}</span>
                    <strong>{Number(row.priority_score || 0).toFixed(1)}%</strong>
                  </button>
                  <span id={`dispatch-sitio-${row.sitio_id}`} className="sitio-priority-tooltip" role="tooltip">
                    <b>{row.sitio} summary</b><span>{row.households} households</span>
                    <span>{row.impacted_households} unsafe / affected</span><span>{row.unreported_members} unreported members</span>
                    <span>{row.no_contact_households} no contact channel</span>
                  </span>
                </li>)}
              </ol>}
          </section>
        </div>
        <aside className="side-panel dispatch-side-panel" aria-label="Dispatch summary, progress and team coverage">
          <DispatchSummary summary={data.summary || emptySummary()} />
          <DispatchSidePanel teams={data.team_coverage || []} />
        </aside>
      </div>
    </>}
  </main>
}
