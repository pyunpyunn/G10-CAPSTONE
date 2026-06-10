import EmptyState from '../ui/EmptyState'
import DispatchStatusBadge from './DispatchStatusBadge'
import { initials } from '../../utils/dispatchHelpers'

export default function DispatchTeamGrid({ teams, onOpenUpdate, onOpenNew }) {
  if (teams.length === 0) {
    return <EmptyState title="No rescue teams yet" message="Rescue team cards will appear after HQ/Admin registers teams and responders." />
  }

  return (
    <div className="dp-team-grid">
      {teams.map((team) => (
        <TeamCard key={team.team_id || team.team_code || team.team_name} team={team} onOpenUpdate={onOpenUpdate} onOpenNew={onOpenNew} />
      ))}
    </div>
  )
}

function TeamCard({ team, onOpenUpdate, onOpenNew }) {
  const assigned = team.assigned_households || 0
  const coverage = team.coverage_percent || 0
  const hasActiveAssignment = Boolean(team.active_assignment_id)
  const canDispatch = hasActiveAssignment || team.is_available

  return (
    <article className={`dp-team-card status-${team.status_key}`}>
      <div className="dp-team-head">
        <div className="dp-team-icon">{initials(team.team_name)}</div>
        <div>
          <div className="dp-team-name">{team.team_name}</div>
          <div className="dp-team-law">{team.team_type} - {team.member_count} members</div>
        </div>
        <DispatchStatusBadge status={{ key: team.status_key, label: team.status_label, tone: team.status_key === 'on_scene' ? 'green' : team.status_key === 'dispatched' ? 'purple' : 'gray' }} />
      </div>

      <div className="dp-team-body">
        <div className="dp-team-stat">
          <div className="dp-team-stat-val">{assigned || '-'}</div>
          <div className="dp-team-stat-lbl">HH assigned</div>
        </div>
        <div className="dp-team-stat">
          <div className="dp-team-stat-val dp-green-text">{coverage ? `${coverage}%` : '-'}</div>
          <div className="dp-team-stat-lbl">Coverage</div>
        </div>
      </div>

      <div className="dp-team-outcomes">
        <OutcomeTile label="Safe" value={team.outcomes?.safe} className="safe" />
        <OutcomeTile label="Evac" value={team.outcomes?.evacuated} className="evac" />
        <OutcomeTile label="Unsafe" value={team.outcomes?.unsafe} className="unsafe" />
        <OutcomeTile label="Pending" value={team.outcomes?.pending} className="pending" />
      </div>

      <div className="dp-coverage-bar">
        <div className="dp-coverage-fill" style={{ width: `${coverage}%` }} />
      </div>
      <div className="dp-coverage-lbl">{coverage ? `Coverage accuracy: ${coverage}%` : 'No field outcome yet'}</div>

      <div className="dp-team-footer">
        <span className="dp-team-area">{team.assigned_area} {team.assigned_time ? `- ${team.assigned_time}` : ''}</span>
        <button
          className={`btn btn-${hasActiveAssignment ? 'secondary' : 'primary'} btn-sm`}
          type="button"
          disabled={!canDispatch}
          onClick={() => (hasActiveAssignment ? onOpenUpdate(team) : onOpenNew())}
        >
          {hasActiveAssignment ? 'Update' : team.is_available ? 'Dispatch' : 'No available responder'}
        </button>
      </div>
    </article>
  )
}

function OutcomeTile({ label, value, className }) {
  return (
    <div className={`dp-outcome ${className}`}>
      <div className="dp-outcome-val">{value || 0}</div>
      <div className="dp-outcome-lbl">{label}</div>
    </div>
  )
}
