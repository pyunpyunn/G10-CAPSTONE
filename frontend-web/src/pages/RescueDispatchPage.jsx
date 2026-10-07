import { useEffect, useState } from 'react'
import { Route } from 'lucide-react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { getDispatchDashboard, getWelfareChecks, getRescuePriorities, getMemberCheckQueue } from '../api/dispatchApi'
import { FieldCommunicationPanel } from './FieldCommunicationPage'
import DispatchSidePanel from '../components/dispatch/DispatchSidePanel'
import DispatchSummary from '../components/dispatch/DispatchSummary'
import DispatchStatusBadge from '../components/dispatch/DispatchStatusBadge'
import DispatchTeamGrid from '../components/dispatch/DispatchTeamGrid'
import LoadingState from '../components/ui/LoadingState'
import DataFilterBar from '../components/ui/DataFilterBar'
import PageHeader from '../components/ui/PageHeader'
import {
  emptySummary,
  label,
} from '../utils/dispatchHelpers'

export default function RescueDispatchPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [teamFilter, setTeamFilter] = useState('all')
  const [welfareChecks, setWelfareChecks] = useState([])
  const [priorities, setPriorities] = useState([])
  const [memberChecks, setMemberChecks] = useState([])

  useEffect(() => {
    let ignore = false

    async function loadPage() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getDispatchDashboard({ per_page: 20 })

        if (!ignore) {
          setPayload(data)
        }
        Promise.all([
          getWelfareChecks({ per_page: 20 }).catch(() => ({ data: [] })),
          getRescuePriorities({ per_page: 20 }).catch(() => ({ data: [] })),
          getMemberCheckQueue({ per_page: 20 }).catch(() => ({ data: [] })),
        ]).then(([welfare, ranked, reminders]) => {
          if (!ignore) {
            setWelfareChecks(welfare.data || [])
            setPriorities(ranked.data || [])
            setMemberChecks(reminders.data || [])
          }
        })
      } catch {
        if (!ignore) {
          setError('Rescue dispatch records cannot be loaded right now. Please check the backend or database connection.')
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadPage()

    return () => {
      ignore = true
    }
  }, [])

  const teams = payload?.teams || []
  const riskAreas = payload?.risk_areas || []
  const dispatches = payload?.dispatches?.data || []
  const summary = payload?.summary || emptySummary()
  const hasActiveEvent = Boolean(payload?.active_event)
  const teamFilters = [
    { key: 'all', label: 'All' },
    ...[...new Set(teams.map((team) => team.status_key).filter(Boolean))]
      .map((key) => ({ key, label: label(key) })),
  ]
  const filteredTeams = teamFilter === 'all'
    ? teams
    : teams.filter((team) => team.status_key === teamFilter)
  const isInitialLoading = isLoading && !payload
  const hasBlockingError = error && !payload

  useEffect(() => {
    const selectedHousehold = location.state?.selectedHousehold

    if (!selectedHousehold || !payload || !Array.isArray(riskAreas) || riskAreas.length === 0) {
      return
    }

    const matchingArea = riskAreas.find((area) => {
      const areaName = String(area.area_name || '').toLowerCase()
      const householdPurok = String(selectedHousehold.purok || '').toLowerCase()
      return areaName === householdPurok || householdPurok.includes(areaName) || areaName.includes(householdPurok)
    })

    navigate('/dispatch/new', { replace: true, state: { selectedHousehold } })
  }, [location.state, payload, riskAreas, teams, navigate])

  function openNewDispatch(team = null) {
    navigate('/dispatch/new', { state: { team } })
  }

  function openUpdateDispatch(team) {
    const dispatch = dispatches.find((item) => item.assignment_id === team.active_assignment_id)

    if (!dispatch) {
      return
    }

    navigate('/dispatch/new', { state: { dispatch, team } })
  }

  return (
    <main className="ops-page dispatch-page">
      <PageHeader
        title="Dispatch Dashboard"
        subtitle={payload?.area_label || 'Shared database records'}
        actions={(
          <Link className={`button review ${!hasActiveEvent ? 'disabled' : ''}`} to={hasActiveEvent ? '/dispatch/new' : '/dispatch'} aria-disabled={!hasActiveEvent} onClick={(event) => !hasActiveEvent && event.preventDefault()}>
            <Route size={15} /> New dispatch
          </Link>
        )}
      />

      {isInitialLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isInitialLoading && !hasBlockingError && payload && (
        <>
          {!hasActiveEvent && (
            <div className="standby-strip">
              <strong>No active disaster event</strong>
              <span>New dispatch assignments are enabled after HQ/Admin declares an active event.</span>
            </div>
          )}

          <div className="workspace-grid dispatch-workspace-grid">
            <section className="dispatch-main-panel" aria-label="Dispatch operations overview">
              <div className="dp-side-card dispatch-assignments-card">
                <div className="dp-side-head">
                  <span className="dp-side-title">Dispatch Progress</span>
                </div>
                <div className="dp-side-body dp-table-body">
                  {dispatches.length === 0 ? (
                    <div className="empty-state compact-empty-state">
                      <h3>No dispatch assignments yet</h3>
                      <p>Use New dispatch after an active event and available responders are ready.</p>
                    </div>
                  ) : (
                    <table>
                      <thead>
                        <tr>
                          <th>Code</th>
                          <th>Team</th>
                          <th>Area</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        {dispatches.map((dispatch) => (
                          <tr key={dispatch.assignment_id}>
                            <td>{dispatch.assignment_code}</td>
                            <td>{dispatch.team_name}</td>
                            <td>{dispatch.assigned_area}</td>
                            <td><DispatchStatusBadge status={dispatch.status} /></td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  )}
                </div>
              </div>

              <main className="dp-main-column dp-team-side-panel dispatch-team-panel">
                <div className="dp-team-toolbar">
                  <span>Team status cards</span>
                  <DataFilterBar
                    className="dispatch-inline-filter"
                    filters={[{
                      id: 'team-status', label: 'Team status', value: teamFilter, onChange: setTeamFilter,
                      options: teamFilters.map((filter) => ({ value: filter.key, label: filter.label })),
                    }]}
                    onReset={() => setTeamFilter('all')}
                  />
                </div>

                <DispatchTeamGrid teams={filteredTeams} onOpenUpdate={openUpdateDispatch} onOpenNew={openNewDispatch} />
              </main>
            </section>

            <aside className="side-panel dispatch-side-panel" aria-label="Dispatch operations summary">
              <DispatchSummary summary={summary} />
              <DispatchSidePanel
                teams={teams}
                logs={payload?.activity_log || []}
                historyLogs={payload?.dispatch_history || []}
              />
            </aside>
          </div>

          <FieldCommunicationPanel compact />

          <section className="dp-side-card" aria-label="Welfare Check List">
            <div className="dp-side-head"><span className="dp-side-title">Welfare Check List</span></div>
            <div className="dp-side-body">
              {welfareChecks.length === 0 ? <p>No households currently need a contact-channel welfare check.</p> : (
                <table><thead><tr><th>Household</th><th>Purok</th><th>Contact</th><th>Action</th></tr></thead><tbody>
                  {welfareChecks.map((household) => <tr key={household.household_id}>
                    <td>{household.household_name || household.household_code || household.household_id}</td>
                    <td>{household.purok || household.address || 'Area unavailable'}</td>
                    <td>{household.contact_channel_label}</td>
                    <td><button type="button" disabled={!household.has_geotag || !hasActiveEvent} onClick={() => navigate('/dispatch/new', { state: { selectedHousehold: household, dispatchType: 'welfare_check' } })}>Route with 2 rescuers</button></td>
                  </tr>)}
                </tbody></table>
              )}
            </div>
          </section>

          <section className="dp-side-card" aria-label="Rescue priority ranking">
            <div className="dp-side-head"><span className="dp-side-title">Rescue priority</span></div>
            <div className="dp-side-body">
              {priorities.length === 0 ? <p>No households to rank for the active event.</p> : (
                <table><thead><tr><th>Household</th><th>Purok</th><th>Tier</th><th>Score</th></tr></thead><tbody>
                  {priorities.map((item) => <tr key={item.household_id}>
                    <td>{item.household_name || item.household_code || item.household_id}</td>
                    <td>{item.area_name || 'Area unavailable'}</td>
                    <td>{item.urgent_tier ? 'Urgent report' : item.no_contact_channel ? 'No contact channel' : 'Monitoring'}</td>
                    <td>{item.priority_score}</td>
                  </tr>)}
                </tbody></table>
              )}
            </div>
          </section>

          <section className="dp-side-card" aria-label="Member status check-in queue">
            <div className="dp-side-head"><span className="dp-side-title">Member status check-in queue</span></div>
            <div className="dp-side-body">
              {memberChecks.length === 0 ? <p>No pending member check-ins.</p> : (
                <table><thead><tr><th>Member</th><th>Household</th><th>Attempt</th><th>State</th></tr></thead><tbody>
                  {memberChecks.map((item) => <tr key={item.reminder_id}>
                    <td>{item.member_name || item.member_id}</td><td>{item.household_id}</td>
                    <td>{item.attempt}</td><td>{item.status}</td>
                  </tr>)}
                </tbody></table>
              )}
            </div>
          </section>
        </>
      )}

    </main>
  )
}
