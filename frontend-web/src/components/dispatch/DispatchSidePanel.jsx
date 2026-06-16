import { useState } from 'react'
import { Search, XCircle } from 'lucide-react'
import EmptyState from '../ui/EmptyState'
import Modal from '../ui/Modal'
import RefreshOverlay from '../ui/RefreshOverlay'
import DispatchStatusBadge from './DispatchStatusBadge'
import {
  dispatchStatuses,
  initials,
} from '../../utils/dispatchHelpers'

export default function DispatchSidePanel({
  teams,
  responders,
  logs,
  historyLogs = [],
  dispatches,
  filter,
  setFilter,
  searchText,
  setSearchText,
  onSearch,
  isUpdating = false,
}) {
  return (
    <aside className="dp-side-panel dp-info-panel">
      <CoveragePanel teams={teams} />
      <ResponderAvailability responders={responders} />
      <DispatchLog logs={logs} historyLogs={historyLogs} />
      <DispatchTable
        dispatches={dispatches}
        filter={filter}
        setFilter={setFilter}
        searchText={searchText}
        setSearchText={setSearchText}
        onSearch={onSearch}
        isUpdating={isUpdating}
      />
    </aside>
  )
}

function CoveragePanel({ teams }) {
  return (
    <section className="dp-side-card">
      <div className="dp-side-head">
        <span className="dp-side-title">Team coverage accuracy</span>
      </div>
      <div className="dp-side-body">
        {teams.length === 0 ? (
          <EmptyState title="No team coverage yet" message="Coverage appears after dispatch outcomes are reported." />
        ) : (
          teams.map((team) => (
            <div className="dp-perf-row" key={team.team_id || team.team_name}>
              <span className="dp-perf-name"><span className="team-dot" />{team.team_code || initials(team.team_name)}</span>
              <div className="dp-perf-bar-wrap">
                <div className="dp-perf-bar" style={{ width: `${team.coverage_percent || 0}%` }} />
              </div>
              <span className="dp-perf-pct">{team.assigned_households ? `${team.coverage_percent || 0}%` : '0%'}</span>
            </div>
          ))
        )}
      </div>
      <div className="log-perf-note">Coverage accuracy = reported household outcomes divided by households assigned.</div>
    </section>
  )
}

function ResponderAvailability({ responders }) {
  const [teamFilter, setTeamFilter] = useState('all')
  const [page, setPage] = useState(1)
  const teams = uniqueResponderTeams(responders)
  const filteredResponders = teamFilter === 'all'
    ? responders
    : responders.filter((responder) => String(responder.team_id || 'unassigned') === teamFilter)
  const perPage = 6
  const totalPages = Math.max(1, Math.ceil(filteredResponders.length / perPage))
  const currentPage = Math.min(page, totalPages)
  const visibleResponders = filteredResponders.slice((currentPage - 1) * perPage, currentPage * perPage)

  return (
    <section className="dp-side-card">
      <div className="dp-side-head">
        <span className="dp-side-title">Rescuer availability</span>
        <select
          className="dp-filter-select"
          value={teamFilter}
          onChange={(event) => {
            setTeamFilter(event.target.value)
            setPage(1)
          }}
        >
          <option value="all">All teams</option>
          {teams.map((team) => (
            <option value={team.id} key={team.id}>{team.name}</option>
          ))}
        </select>
      </div>
      <div className="dp-side-body dp-table-body">
        {responders.length === 0 ? (
          <EmptyState title="No responders yet" message="HQ/Admin-created rescuer accounts will appear here." />
        ) : filteredResponders.length === 0 ? (
          <EmptyState title="No rescuers in this team" message="Choose another team filter." />
        ) : (
          <table>
            <thead>
              <tr>
                <th>Rescuer</th>
                <th>Team</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {visibleResponders.map((responder) => (
                <tr key={responder.responder_id}>
                  <td>
                    <strong>{responder.full_name}</strong>
                    {responder.active_assignment_code && (
                      <div className="dp-table-muted">{responder.active_assignment_code} · {responder.active_assigned_area}</div>
                    )}
                  </td>
                  <td>{responder.team_name}</td>
                  <td><DispatchStatusBadge status={responder.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
      {filteredResponders.length > perPage && (
        <div className="dp-mini-pagination">
          <span>Page {currentPage} of {totalPages}</span>
          <div>
            <button type="button" disabled={currentPage === 1} onClick={() => setPage((value) => Math.max(1, value - 1))}>Prev</button>
            <button type="button" disabled={currentPage === totalPages} onClick={() => setPage((value) => Math.min(totalPages, value + 1))}>Next</button>
          </div>
        </div>
      )}
    </section>
  )
}

function uniqueResponderTeams(responders) {
  const map = new Map()

  responders.forEach((responder) => {
    const id = String(responder.team_id || 'unassigned')
    const name = responder.team_name || 'Unassigned'

    if (!map.has(id)) {
      map.set(id, { id, name })
    }
  })

  return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name))
}

function DispatchLog({ logs, historyLogs }) {
  const [isHistoryOpen, setIsHistoryOpen] = useState(false)
  const fullHistory = historyLogs?.length ? historyLogs : logs

  return (
    <section className="dp-side-card">
      <div className="dp-side-head">
        <span className="dp-side-title">Dispatch log</span>
        <button className="btn-text" type="button" onClick={() => setIsHistoryOpen(true)}>View history</button>
      </div>
      <div className="dp-side-body dp-log-body">
        {logs.length === 0 ? (
          <EmptyState title="No dispatch log yet" message="Dispatch activity will appear after assignments are created or updated." />
        ) : (
          logs.map((log) => (
            <div className="dp-log-item" key={log.assignment_id}>
              <span className="dp-log-time">{log.time || '--'}</span>
              <span className={`dp-log-dot ${log.status?.tone || 'gray'}`} />
              <div className="dp-log-text">{log.team_name} - <strong>{log.status?.label}</strong> - {log.assigned_area}</div>
            </div>
          ))
        )}
      </div>
      <Modal
        title="Dispatch History"
        isOpen={isHistoryOpen}
        onClose={() => setIsHistoryOpen(false)}
        className="dp-history-modal"
      >
        {fullHistory.length === 0 ? (
          <EmptyState title="No dispatch history yet" message="Completed and updated dispatch assignments will appear here." />
        ) : (
          <div className="dp-history-list">
            {fullHistory.map((log) => (
              <div className="dp-history-row" key={`${log.assignment_id}-${log.time}`}>
                <span className="dp-log-time">{log.time || '--'}</span>
                <span className={`dp-log-dot ${log.status?.tone || 'gray'}`} />
                <div className="dp-log-text">
                  <strong>{log.team_name}</strong> - {log.status?.label} - {log.assigned_area}
                </div>
              </div>
            ))}
          </div>
        )}
      </Modal>
    </section>
  )
}

function DispatchTable({ dispatches, filter, setFilter, searchText, setSearchText, onSearch, isUpdating }) {
  return (
    <section className="dp-side-card">
      <div className="dp-side-head">
        <span className="dp-side-title">Active dispatch assignments</span>
        <select className="dp-filter-select" value={filter} onChange={(event) => setFilter(event.target.value)}>
          <option value="all">All</option>
          {dispatchStatuses.map((status) => (
            <option value={status.value} key={status.value}>{status.label}</option>
          ))}
        </select>
      </div>
      <div className="dp-dispatch-search">
        <Search size={14} />
        <input value={searchText} placeholder="Search assignment, area, team..." onChange={(event) => setSearchText(event.target.value)} onKeyDown={(event) => event.key === 'Enter' && onSearch()} />
        {searchText && <button type="button" onClick={() => setSearchText('')}><XCircle size={14} /></button>}
      </div>
      <RefreshOverlay active={isUpdating}>
        <div className="dp-side-body dp-table-body">
          {dispatches.length === 0 ? (
            <EmptyState title="No dispatch assignments yet" message="Use New dispatch after an active event and registered responders are available." />
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
      </RefreshOverlay>
    </section>
  )
}
