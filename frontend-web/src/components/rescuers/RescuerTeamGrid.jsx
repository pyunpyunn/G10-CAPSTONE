export default function RescuerTeamGrid({ teams }) {
  return (
    <div className="ra-team-grid">
      {teams.map((team) => (
        <div className="ra-team" key={`${team.team_code}-${team.team_name}`}>
          <div className="ra-team-name"><strong>{team.team_code || '--'}</strong><span>{team.team_name || 'Unnamed team'}</span></div>
          <span>
            {team.member_count || 0} members
            {team.deployed_count > 0 ? ` - ${team.deployed_count} deployed` : team.team_id ? ' - available roster' : ' - not created yet'}
          </span>
        </div>
      ))}
    </div>
  )
}

