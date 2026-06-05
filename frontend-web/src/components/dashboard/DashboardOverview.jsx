import {
  CloudSun,
  House,
  Map,
  PackageCheck,
  Radio,
  Route,
} from 'lucide-react'
import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'
import {
  eventTone,
  statusTone,
} from '../../utils/dashboardHelpers'

export default function DashboardOverview({
  dashboard,
  hasActiveEvent,
  onOpenModule,
}) {
  return (
    <aside className="dashboard-overview" aria-label="System overview">
      <div className="overview-heading">
        <span className="overview-heading-title">System overview</span>
        <Badge tone={hasActiveEvent ? eventTone(dashboard.active_event.severity_key) : 'gray'}>
          {hasActiveEvent ? 'Active' : 'Standby'}
        </Badge>
      </div>

      <WeatherCard weather={dashboard.weather} hasActiveEvent={hasActiveEvent} onOpenWeather={() => onOpenModule('/weather')} />
      <MapCard mapSummary={dashboard.map} households={dashboard.households} hasActiveEvent={hasActiveEvent} onOpenMap={() => onOpenModule('/mapping')} />
      <RequestCard requests={dashboard.requests} onOpenRequests={() => onOpenModule('/resources-requests')} />
      <QuickActions onOpenModule={onOpenModule} />
    </aside>
  )
}

function WeatherCard({ weather, hasActiveEvent, onOpenWeather }) {
  return (
    <section className="overview-card">
      <div className="panel-head">
        <span className="panel-title"><CloudSun size={15} />Disaster alert update</span>
        <button className="panel-link" type="button" onClick={onOpenWeather}>Full view -&gt;</button>
      </div>
      {weather ? (
        <div className="wx-card">
          <div className="wx-source">{weather.source_name || 'Weather source'}</div>
          <div className="wx-msg">{weather.advisory_text || weather.advisory_title || weather.condition_name || 'Weather snapshot saved.'}</div>
          <div className="wx-time">{weather.observed_at || 'Observation time not set'}</div>
        </div>
      ) : (
        <EmptyState
          title={hasActiveEvent ? 'No weather snapshot yet' : 'No active weather alert'}
          message={hasActiveEvent ? 'Weather data will appear after PAGASA/Open-Meteo data is saved for this event.' : 'Weather snapshots are shown when linked to an active event.'}
        />
      )}
      <div className="trusted-row" aria-label="Trusted advisory sources">
        <span className="trusted-chip">PAGASA</span>
        <span className="trusted-chip">NDRRMC</span>
        <span className="trusted-chip">Open-Meteo</span>
      </div>
    </section>
  )
}

function MapCard({ mapSummary, households, hasActiveEvent, onOpenMap }) {
  return (
    <section className="overview-card">
      <div className="panel-head">
        <span className="panel-title"><Map size={15} />Map display</span>
        <button className="panel-link" type="button" onClick={onOpenMap}>Full view -&gt;</button>
      </div>
      <button className="overview-map map-mock" type="button" onClick={onOpenMap} aria-label="Open full map display">
        <div className="map-road-h one" />
        <div className="map-road-v" />
        {hasActiveEvent && households.evacuated > 0 && <span className="overview-dot evac dot-c" />}
        {hasActiveEvent && households.unsafe > 0 && <span className="overview-dot unsafe dot-d" />}
        {hasActiveEvent && households.safe_only > 0 && <span className="overview-dot safe dot-a" />}
        {hasActiveEvent && households.unchecked > 0 && <span className="overview-dot unchecked dot-e" />}
        <span className="overview-map-label">{hasActiveEvent ? 'Open full map' : 'No active map points'}</span>
      </button>
      <div className="overview-map-stats">
        <div className="overview-mini-stat"><strong>{mapSummary.evacuation_sites}</strong><span>Evac sites</span></div>
        <div className="overview-mini-stat"><strong>{mapSummary.unsafe_households}</strong><span>Unsafe</span></div>
        <div className="overview-mini-stat"><strong>{mapSummary.unchecked_households}</strong><span>Unchecked</span></div>
      </div>
    </section>
  )
}

function RequestCard({ requests, onOpenRequests }) {
  return (
    <section className="overview-card">
      <div className="panel-head">
        <span className="panel-title"><PackageCheck size={15} />Requests</span>
        <button className="panel-link" type="button" onClick={onOpenRequests}>Full view -&gt;</button>
      </div>
      <div className="requests-summary">
        <div className="request-summary-item"><strong>{requests.needs_validation}</strong><span>Needs validation</span></div>
        <div className="request-summary-item"><strong>{requests.validated}</strong><span>Validated</span></div>
        <div className="request-summary-item"><strong>{requests.released}</strong><span>Released</span></div>
      </div>
      {requests.latest.length > 0 ? (
        <div className="overview-request-table">
          <table>
            <thead><tr><th>Request from</th><th>Request</th><th>Qty</th><th>Status</th></tr></thead>
            <tbody>
              {requests.latest.map((request) => (
                <tr key={request.request_id}>
                  <td>{request.requested_by}</td>
                  <td>{request.item_name}</td>
                  <td>{request.quantity}</td>
                  <td><Badge tone={statusTone(request.status_key)}>{request.validation_status}</Badge></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <EmptyState title="No requests yet" message="Requests will appear after records are received for validation." />
      )}
    </section>
  )
}

function QuickActions({ onOpenModule }) {
  const actions = [
    { label: 'Broadcast', path: '/broadcast', icon: Radio },
    { label: 'Households', path: '/households', icon: House },
    { label: 'Dispatch', path: '/dispatch', icon: Route },
    { label: 'Requests', path: '/resources-requests', icon: PackageCheck },
  ]

  return (
    <section className="overview-card">
      <div className="panel-head">
        <span className="panel-title">Quick actions</span>
      </div>
      <div className="quick-action-grid">
        {actions.map((action) => {
          const Icon = action.icon

          return (
            <button className="quick-action-button" type="button" key={action.path} onClick={() => onOpenModule(action.path)}>
              <Icon size={15} />
              <span>{action.label}</span>
            </button>
          )
        })}
      </div>
    </section>
  )
}
