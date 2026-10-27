import { X } from 'lucide-react'
import { useState } from 'react'
import OperationalViews from './OperationalViews'
import DashboardImpactViews from './DashboardImpactViews'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faArrowRight } from '@fortawesome/free-solid-svg-icons'
import Badge from '../ui/Badge'
import {
  eventTone,
} from '../../utils/dashboardHelpers'

export default function DashboardMainContent({
  dashboard,
  hasActiveEvent,
  onOpenModule,
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

      <OperationalViews dashboard={dashboard} onOpenModule={onOpenModule} />
      <DashboardImpactViews eventId={dashboard.active_event?.event_id} />
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
