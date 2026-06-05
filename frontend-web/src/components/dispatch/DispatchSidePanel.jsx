import { Search, XCircle } from 'lucide-react'
import EmptyState from '../ui/EmptyState'
import DispatchStatusBadge from './DispatchStatusBadge'
import {
  dispatchStatuses,
  initials,
} from '../../utils/dispatchHelpers'

export default function DispatchSidePanel({
  teams,
  responders,
  logs,
  dispatches,
  filter,
  setFilter,
  searchText,
  setSearchText,
  onSearch,
}) {
  return (
    <aside className="dp-side-panel dp-info-panel">
      <CoveragePanel teams={teams} />
      <ResponderAvailability responders={responders} />
      <DispatchLog logs={logs} />
      <DispatchTable
        dispatches={dispatches}
        filter={filter}
        setFilter={setFilter}
        searchText={searchText}
        setSearchText={setSearchText}
        onSearch={onSearch}
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
              <span className="dp-perf-pct">{team.coverage_percent ? `${team.coverage_percent}%` : '-'}</span>
            </div>
          ))
        )}
      </div>
      <div className="log-perf-note">Coverage accuracy = reported household outcomes divided by households assigned.</div>
    </section>
  )
}

function ResponderAvailability({ responders }) {
  return (
    <section className="dp-side-card">
      <div className="dp-side-head">
        <span className="dp-side-title">Rescuer availability</span>
      </div>
      <div className="dp-side-body dp-table-body">
        {responders.length === 0 ? (
          <EmptyState title="No responders yet" message="HQ/Admin-created rescuer accounts will appear here." />
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
              {responders.map((responder) => (
                <tr key={responder.responder_id}>
                  <td><strong>{responder.full_name}</strong></td>
                  <td>{responder.team_name}</td>
                  <td><DispatchStatusBadge status={responder.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </section>
  )
}

function DispatchLog({ logs }) {
  return (
    <section className="dp-side-card">
      <div className="dp-side-head">
        <span className="dp-side-title">Dispatch log</span>
        <span className="dp-side-note">Last 10</span>
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
      <div className="log-perf-note">Only the latest dispatch events load on page open.</div>
    </section>
  )
}

function DispatchTable({ dispatches, filter, setFilter, searchText, setSearchText, onSearch }) {
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
    </section>
  )
}
