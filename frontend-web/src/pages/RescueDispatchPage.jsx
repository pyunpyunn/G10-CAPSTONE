import { useEffect, useState } from 'react'
import { RefreshCcw, Route } from 'lucide-react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { getDispatchDashboard } from '../api/dispatchApi'
import DispatchSidePanel from '../components/dispatch/DispatchSidePanel'
import DispatchSummary from '../components/dispatch/DispatchSummary'
import DispatchStatusBadge from '../components/dispatch/DispatchStatusBadge'
import DispatchTeamGrid from '../components/dispatch/DispatchTeamGrid'
import LoadingState from '../components/ui/LoadingState'
import {
  emptySummary,
  teamFilters,
} from '../utils/dispatchHelpers'

export default function RescueDispatchPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [teamFilter, setTeamFilter] = useState('all')
  const [dispatchFilter, setDispatchFilter] = useState('all')
  const [searchText, setSearchText] = useState('')

  useEffect(() => {
    let ignore = false

    async function loadPage() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getDispatchDashboard({
          status: dispatchFilter,
          search: searchText.trim(),
          per_page: 20,
        })

        if (!ignore) {
          setPayload(data)
        }
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
  }, [dispatchFilter, searchText])

  async function loadDispatch() {
    setIsLoading(true)
    setError('')

    try {
      const data = await getDispatchDashboard({
        status: dispatchFilter,
        search: searchText.trim(),
        per_page: 20,
      })
      setPayload(data)
    } catch {
      setError('Rescue dispatch records cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  const teams = payload?.teams || []
  const riskAreas = payload?.risk_areas || []
  const dispatches = payload?.dispatches?.data || []
  const summary = payload?.summary || emptySummary()
  const hasActiveEvent = Boolean(payload?.active_event)
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
      <header className="household-status-page-header dispatch-page-header">
        <div className="household-status-header-copy">
          <h1>Rescue Dispatch</h1>
          <p>Barangay Mambaling, Cebu City</p>
        </div>
        <div className="weather-page-actions household-status-page-actions">
          <div className="weather-live-status">
            <strong>{hasActiveEvent ? 'Live' : 'Standby'}</strong>
            <span>{hasActiveEvent ? 'Dispatch operations active' : 'Waiting for active event'}</span>
          </div>
          <button className="button secondary" type="button" onClick={loadDispatch}>
            <RefreshCcw size={16} />
            Refresh
          </button>
          <Link className={`button review ${!hasActiveEvent ? 'disabled' : ''}`} to={hasActiveEvent ? '/dispatch/new' : '/dispatch'} aria-disabled={!hasActiveEvent} onClick={(event) => !hasActiveEvent && event.preventDefault()}>
            <Route size={16} />
            New dispatch
          </Link>
        </div>
      </header>

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
                  <span className="dp-side-title">Active dispatch assignments</span>
                  <select className="dp-filter-select" value={dispatchFilter} onChange={(event) => setDispatchFilter(event.target.value)}>
                    <option value="all">All</option>
                    {teamFilters.map((filter) => (
                      <option value={filter.key} key={filter.key}>{filter.label}</option>
                    ))}
                  </select>
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
                  <select className="dp-filter-select" value={teamFilter} onChange={(event) => setTeamFilter(event.target.value)} aria-label="Filter team status cards">
                    {teamFilters.map((filter) => (
                      <option value={filter.key} key={filter.key}>{filter.label}</option>
                    ))}
                  </select>
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
        </>
      )}

    </main>
  )
}
