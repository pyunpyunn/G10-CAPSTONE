import { Users } from 'lucide-react'
import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'

export default function RescuerTeamGrid({ teams = [], showStatus = false }) {
  if (!teams.length) return <EmptyState title="No teams yet" message="Add a rescue team to organize your responders." />
  return <div className="ra-team-grid">{teams.map((team) => {
    const status = team.duty_status_display
    return <article className="ra-team" key={team.team_id || `${team.team_code}-${team.team_name}`}>
      <div className="ra-team-name"><strong>{team.team_code || '—'}</strong><span>{team.team_name || 'Unnamed team'}</span></div>
      {showStatus && status && <Badge tone={status.tone}>{status.label}</Badge>}
      <span className="ra-team-count"><Users size={14} />{team.member_count} members</span>
      <span>{team.active_dispatch_count} active assignments · {team.deployed_count} deployed</span>
    </article>
  })}</div>
}
