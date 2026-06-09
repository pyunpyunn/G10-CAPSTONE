import {
  Radio,
  Settings,
  TriangleAlert,
} from 'lucide-react'
import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'
import Panel from '../ui/Panel'
import StatCard from '../ui/StatCard'
import {
  eventTone,
  makeAxis,
  percent,
  statusTone,
} from '../../utils/dashboardHelpers'

export default function DashboardMainContent({
  dashboard,
  stats,
  hasActiveEvent,
  onOpenModule,
}) {
  return (
    <div className="dashboard-main">
      {hasActiveEvent && (
        <ActiveEventBanner activeEvent={dashboard.active_event} onOpenBroadcast={() => onOpenModule('/broadcast')} />
      )}

      {!hasActiveEvent && (
        <div className="standby-strip">
          <strong>Standby mode</strong>
          <span>Household reporting, dispatch charts, weather snapshots, and activity logs will appear after an active disaster event is declared.</span>
        </div>
      )}

      <div className="stat-row">
        {stats.map((stat) => (
          <StatCard key={stat.label} {...stat} />
        ))}
      </div>

      <ReportingProgress households={dashboard.households} hasActiveEvent={hasActiveEvent} />

      <Panel title="Operational charts">
        <div className="dashboard-dispatch-graphs">
          <ChartCard
            title="Household status"
            bars={dashboard.households.bars}
            emptyTitle="No household reports yet"
            emptyMessage="Reports will come from household mobile users or authenticated responder field reports."
            onManage={() => onOpenModule('/households')}
          />
          <ChartCard
            title="Dispatch status - team count"
            bars={dashboard.dispatch.counts}
            emptyTitle="No dispatch yet"
            emptyMessage="Teams will appear after HQ assigns responders to an active event."
            onManage={() => onOpenModule('/dispatch')}
          />
        </div>
      </Panel>

      <Panel
        title="Team dispatch overview"
        action={<button className="panel-link" type="button" onClick={() => onOpenModule('/dispatch')}>Full dispatch -&gt;</button>}
      >
        <TeamDispatchTable teams={dashboard.dispatch.teams} />
      </Panel>

      <div className="sep">
        Recent activity log <span>showing latest event reports only</span>
      </div>
      <ActivityLog activities={dashboard.recent_activity} />
    </div>
  )
}

function ActiveEventBanner({ activeEvent, onOpenBroadcast }) {
  if (!activeEvent) {
    return (
      <div className="event-banner standby">
        <div className="event-banner-main">
          <span className="event-icon">
            <Radio size={18} />
          </span>
          <div className="event-copy">
            <span className="event-kicker">No active disaster</span>
            <strong className="event-name">Dashboard is on standby</strong>
          </div>
        </div>
        <button className="event-action" type="button" onClick={onOpenBroadcast}>Declare event -&gt;</button>
      </div>
    )
  }

  return (
    <div className={`event-banner event-${eventTone(activeEvent.severity_key)}`}>
      <div className="event-banner-main">
        <span className="event-icon">
          <TriangleAlert size={18} />
        </span>
        <div className="event-copy">
          <div className="event-topline">
            <span className="event-kicker">Active event</span>
            <Badge tone={eventTone(activeEvent.severity_key)}>{activeEvent.severity}</Badge>
          </div>
          <strong className="event-name">{activeEvent.name}</strong>
          <div className="event-meta" aria-label="Active event details">
            <span>{activeEvent.type}</span>
            <span>Declared {activeEvent.started_time || 'time not set'}</span>
          </div>
        </div>
      </div>
      <button className="event-action" type="button" onClick={onOpenBroadcast}>View details -&gt;</button>
    </div>
  )
}

function ReportingProgress({ households, hasActiveEvent }) {
  const safeOnlyPercent = percent(households.safe_only, households.total)
  const evacuatedPercent = percent(households.evacuated, households.total)
  const unsafePercent = percent(households.unsafe, households.total)

  return (
    <>
      <div className="progress-label">
        <span>{hasActiveEvent ? 'Reporting progress' : 'Reporting progress standby'}</span>
        <span>
          {hasActiveEvent
            ? `${households.reporting_percent}% reported (${households.reported} / ${households.total})`
            : 'No active reporting cycle'}
        </span>
      </div>
      <div className="prog-track">
        <div className="prog-seg green" style={{ width: `${safeOnlyPercent}%` }} />
        <div className="prog-seg blue" style={{ width: `${evacuatedPercent}%` }} />
        <div className="prog-seg red" style={{ width: `${unsafePercent}%` }} />
      </div>
    </>
  )
}

function ChartCard({ title, bars = [], emptyTitle, emptyMessage, onManage }) {
  const hasValues = bars.some((bar) => Number(bar.value) > 0)
  const axis = makeAxis(bars)

  return (
    <div className="dispatch-chart">
      <div className="dispatch-chart-head">
        <div className="dispatch-chart-title">{title}</div>
        <button className="chart-manage-button" type="button" aria-label={`Open ${title}`} onClick={onManage}>
          <Settings size={15} />
        </button>
      </div>
      {hasValues ? (
        <div className="team-count-chart" role="img" aria-label={title}>
          <div className="chart-y-axis" aria-hidden="true">
            {axis.map((item) => <span key={item}>{item}</span>)}
          </div>
          <div className="chart-plot" style={{ '--bar-count': bars.length }}>
            {bars.map((bar) => (
              <div className="dispatch-bar" style={{ '--bar-height': bar.height }} key={bar.label}>
                <span className="dispatch-bar-value">{bar.value}</span>
                <span className={`dispatch-bar-fill ${bar.class_name}`} />
                <span className="dispatch-bar-label">{bar.label}</span>
              </div>
            ))}
          </div>
        </div>
      ) : (
        <EmptyState title={emptyTitle} message={emptyMessage} />
      )}
    </div>
  )
}

function TeamDispatchTable({ teams = [] }) {
  if (teams.length === 0) {
    return <EmptyState title="No team assignments yet" message="Dispatch rows will appear after HQ assigns a team to an active event." />
  }

  return (
    <div className="tbl-wrap compact-table">
      <table className="dispatch-overview-table">
        <thead>
          <tr>
            <th>Team</th>
            <th>Type</th>
            <th>Status</th>
            <th>Assigned area</th>
            <th>Assigned at</th>
          </tr>
        </thead>
        <tbody>
          {teams.map((team) => (
            <tr key={`${team.team_name}-${team.assigned_time}`}>
              <td><span className="team-dot" />{team.team_name}</td>
              <td>{team.team_type}</td>
              <td><Badge tone={statusTone(team.status_key)}>{team.status}</Badge></td>
              <td>{team.assigned_area}</td>
              <td>{team.assigned_time || '-'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function ActivityLog({ activities = [] }) {
  if (activities.length === 0) {
    return (
      <div className="tbl-wrap">
        <EmptyState title="No recent event activity" message="Household reports and responder field reports will appear here after an active event receives updates." />
      </div>
    )
  }

  return (
    <div className="tbl-wrap">
      <div className="log-list">
        {activities.map((activity) => (
          <div className="log-item" key={`${activity.time}-${activity.household_name}-${activity.status}`}>
            <span className="log-time">{activity.time || '-'}</span>
            <span className={`log-dot log-${statusTone(activity.status_key)}`} />
            <div className="log-msg">
              {activity.household_name} - <Badge tone={statusTone(activity.status_key)}>{activity.status}</Badge>
              {activity.location_label ? <span className="log-extra"> {activity.location_label}</span> : null}
              {activity.battery_level !== null ? <span className="log-extra"> Battery {activity.battery_level}%</span> : null}
            </div>
          </div>
        ))}
      </div>
      <div className="table-footer">
        <span>Only the latest records load first to keep 1,000+ household operations fast.</span>
        <button className="btn btn-secondary btn-sm" type="button">View all logs</button>
      </div>
    </div>
  )
}
