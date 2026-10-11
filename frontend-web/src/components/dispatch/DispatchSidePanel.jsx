import EmptyState from '../ui/EmptyState'

export default function DispatchSidePanel({ teams = [] }) {
  return <section className="dp-side-card" aria-label="Team Coverage Accuracy Panel">
    <div className="dp-side-head"><span className="dp-side-title">Team Coverage Accuracy</span></div>
    <div className="dp-side-body">
      {!teams.length ? <EmptyState title="No team coverage yet" message="Coverage appears after dispatch outcomes are reported." /> : teams.map((team) => (
        <div className="response-coverage-team" key={team.team_id}>
          <div className="dp-perf-row"><span className="dp-perf-name" title={team.team_name}>{team.team_code || team.team_name}</span>
            <div className="dp-perf-bar-wrap"><div className="dp-perf-bar" style={{ width: `${team.coverage_percent || 0}%` }} /></div>
            <span className="dp-perf-pct">{team.coverage_percent || 0}%</span></div>
          <small>{team.team_name}: {team.reported_households || 0} reported / {team.assigned_households || 0} assigned</small>
        </div>
      ))}
    </div>
    <div className="log-perf-note">Reported household outcomes divided by assigned households across this event, including completed dispatches.</div>
  </section>
}
