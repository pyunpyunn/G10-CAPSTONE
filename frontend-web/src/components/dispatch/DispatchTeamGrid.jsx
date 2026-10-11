import EmptyState from '../ui/EmptyState'
import DispatchStatusBadge from './DispatchStatusBadge'

export default function DispatchTeamGrid({ teams, onOpenUpdate, onOpenNew }) {
  if (teams.length === 0) {
    return <EmptyState title="No rescue teams yet" message="Rescue teams will appear after HQ/Admin registers teams and responders." />
  }

  return (
    <div className="dp-team-table-wrap">
      <table className="dp-team-table">
        <thead>
          <tr>
            <th>Abbreviation</th>
            <th>Team name</th>
            <th>Active members</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          {teams.map((team) => (
            <TeamRow key={team.team_id || team.team_code || team.team_name} team={team} onOpenUpdate={onOpenUpdate} onOpenNew={onOpenNew} />
          ))}
        </tbody>
      </table>
    </div>
  )
}

function TeamRow({ team, onOpenUpdate, onOpenNew }) {
  const hasActiveAssignment = Boolean(team.active_assignment_id)
  const canDispatch = hasActiveAssignment || team.is_available

  return (
    <tr className={`status-${team.status_key}`}>
      <td><strong>{team.team_code || '--'}</strong></td>
      <td>
        <strong>{team.team_name}</strong>
        <span className="dp-table-muted">{team.team_type || 'Response team'}</span>
      </td>
      <td>{team.active_member_count || 0} / {team.member_count || 0}</td>
      <td><DispatchStatusBadge status={{ key: team.status_key, label: team.status_label, tone: team.status_key === 'on_scene' ? 'green' : team.status_key === 'dispatched' ? 'purple' : 'gray' }} /></td>
      <td>
        <button
          className={`btn btn-${hasActiveAssignment ? 'secondary' : 'primary'} btn-sm`}
          type="button"
          disabled={!canDispatch}
          onClick={() => (hasActiveAssignment ? onOpenUpdate(team) : onOpenNew(team))}
        >
          {hasActiveAssignment ? 'Update' : 'Dispatch'}
        </button>
      </td>
    </tr>
  )
}
