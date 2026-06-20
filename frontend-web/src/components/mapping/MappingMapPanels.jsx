import EmptyState from '../ui/EmptyState'
import {
  defaultRules,
  labelize,
} from '../../utils/mappingHelpers'

export default function MappingMapPanels({
  hasActiveEvent,
  dispatchRoutes,
  selectedRoute,
  mapRules,
  onStoredRoute,
  onClearSelectedRoute,
}) {
  return (
    <div className="mapping-map-panels">
      <DispatchRoutes
        hasActiveEvent={hasActiveEvent}
        dispatchRoutes={dispatchRoutes}
        selectedRoute={selectedRoute}
        onStoredRoute={onStoredRoute}
        onClearSelectedRoute={onClearSelectedRoute}
      />
      <MapRules mapRules={mapRules} />
    </div>
  )
}

function DispatchRoutes({
  hasActiveEvent,
  dispatchRoutes,
  selectedRoute,
  onStoredRoute,
  onClearSelectedRoute,
}) {
  return (
    <section className="panel">
      <div className="panel-head">
        <span className="panel-title">Rescue dispatched routes</span>
        {selectedRoute && (
          <button className="btn btn-secondary btn-sm" type="button" onClick={onClearSelectedRoute}>
            Show all
          </button>
        )}
      </div>
      {dispatchRoutes.length === 0 ? (
        <EmptyState title="No route lines visible" message={hasActiveEvent ? 'Saved responder routes will appear after dispatch tracking.' : 'Routes are hidden without an active event.'} />
      ) : (
        <div className="map-route-list">
          {dispatchRoutes.map((route) => (
            <button className="map-route-card" type="button" onClick={() => onStoredRoute(route)} key={route.route_id}>
              <span className="route-team-dot" />
              <div>
                <strong>{route.route_name}</strong>
                <span>{route.team_name} - {route.assigned_area}</span>
              </div>
              <span className="route-chip">{route.distance_km ? `${route.distance_km} km` : labelize(route.status)}</span>
            </button>
          ))}
        </div>
      )}
    </section>
  )
}

function MapRules({ mapRules }) {
  const rules = mapRules.length ? mapRules : defaultRules()

  return (
    <section className="panel">
      <div className="panel-head"><span className="panel-title">Geotag map rules</span></div>
      <div className="geo-rule-list">
        {rules.map((rule) => (
          <article className="geo-rule-card" key={rule.title}>
            <span className={`status-swatch ${rule.color}`} />
            <div>
              <strong>{rule.title}</strong>
              <span>{rule.text}</span>
            </div>
          </article>
        ))}
      </div>
    </section>
  )
}
