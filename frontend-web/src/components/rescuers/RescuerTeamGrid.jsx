export default function RescuerTeamGrid({ teams, showStatus = false }) {
  return (
    <div className="ra-team-grid">
      {teams.map((team) => (
        <div className="ra-team" key={`${team.team_code}-${team.team_name}`}>
          <div className="ra-team-name"><strong>{team.team_code || '--'}</strong><span>{team.team_name || 'Unnamed team'}</span></div>
          {showStatus && <span>{String(team.duty_status || 'available').replaceAll('_', ' ')} - {team.active_dispatch_count || 0} active assignments</span>}
          <span>
            {team.member_count || 0} members
            {team.deployed_count > 0 ? ` - ${team.deployed_count} deployed` : team.team_id ? ' - available roster' : ' - not created yet'}
          </span>
        </div>
      ))}
    </div>
  )
}

