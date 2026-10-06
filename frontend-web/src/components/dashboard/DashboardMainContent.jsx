import { X } from 'lucide-react'
import { useState } from 'react'
import RescueCriteriaChart from './RescueCriteriaChart'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faArrowRight } from '@fortawesome/free-solid-svg-icons'
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
  refreshVersion,
}) {
  const [isStandbyStripVisible, setIsStandbyStripVisible] = useState(true)

  return (
    <div className="dashboard-main">
      {hasActiveEvent && (
        <ActiveEventBanner
          activeEvent={dashboard.active_event}
          onOpenBroadcast={(eventId) => onOpenModule(`/broadcast?event_id=${encodeURIComponent(eventId)}`)}
        />
      )}

      {!hasActiveEvent && isStandbyStripVisible && (
        <div className="standby-strip">
          <strong>NO ACTIVE DISASTER</strong>
          <button
            className="standby-strip-close"
            type="button"
            aria-label="Dismiss no active disaster message"
            title="Dismiss"
            onClick={() => setIsStandbyStripVisible(false)}
          >
            <X size={16} aria-hidden="true" />
          </button>
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
            alwaysShowChart
            onManage={() => onOpenModule('/dispatch')}
          />
        </div>
      </Panel>

      <RescueCriteriaChart
        eventId={dashboard.active_event?.event_id}
        refreshVersion={refreshVersion}
      />

      <div className="sep">
        Recent activity log <span>showing latest event reports only</span>
      </div>
      <ActivityLog activities={dashboard.recent_activity} onViewAll={() => onOpenModule('/archive')} />
    </div>
  )
}

function ActiveEventBanner({ activeEvent, onOpenBroadcast }) {
  if (!activeEvent) {
    return (
      <div 
        className="event-banner standby" 
        onClick={() => onOpenBroadcast()}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => e.key === 'Enter' && onOpenBroadcast()}
      >
        <div className="event-main-col">
          <span className="event-kicker">System Status</span>
          <strong className="event-name">Dashboard Standby</strong>
        </div>
        <div className="event-meta-col">
          <span className="standby-hint">
            Click to declare event <FontAwesomeIcon icon={faArrowRight} />
          </span>
        </div>
      </div>
    )
  }

  const tone = eventTone(activeEvent.severity_key)

  return (
    <div 
      className={`event-banner active-state tone-${tone}`}
      onClick={() => onOpenBroadcast(activeEvent.event_id)}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => e.key === 'Enter' && onOpenBroadcast(activeEvent.event_id)}
    >
      {/* Left Section: White/Light Event Info */}
      <div className="event-main-col">
        <span className="event-kicker">Active Disaster Event</span>
        <h2 className="event-name">{activeEvent.name}</h2>
      </div>

      {/* Right Section: Darker Translucent Container */}
      <div className="event-meta-col">
        <div className="meta-card">
          <div className="meta-group">
            <span className="meta-label">Type</span>
            <span className="meta-value">{activeEvent.type}</span>
          </div>

          <div className="meta-divider" aria-hidden="true" />

          <div className="meta-group">
            <span className="meta-label">Declared</span>
            <span className="meta-value">{activeEvent.started_time || 'Time not set'}</span>
          </div>

          <div className="meta-divider" aria-hidden="true" />

          <div className="meta-group">
            <span className="meta-label">Severity</span>
            <div className="meta-value">
              <Badge tone={tone}>{activeEvent.severity}</Badge>
            </div>
          </div>
        </div>
      </div>
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

function ChartCard({ title, bars = [], emptyTitle, emptyMessage, alwaysShowChart = false, onManage }) {
  const hasValues = bars.some((bar) => Number(bar.value) > 0)
  const axis = makeAxis(bars)

  return (
    <div className="dispatch-chart">
      <div className="dispatch-chart-head">
        <div className="dispatch-chart-title">{title}</div>
        <button className="chart-manage-button" type="button" aria-label={`Open ${title}`} onClick={onManage}>
          <FontAwesomeIcon icon={faArrowRight} />
        </button>
      </div>
      {hasValues || alwaysShowChart ? (
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

function ActivityLog({ activities = [], onViewAll }) {
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
        <button className="btn btn-secondary btn-sm" type="button" onClick={onViewAll}>View all logs</button>
      </div>
    </div>
  )
}
