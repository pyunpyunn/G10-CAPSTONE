import { Route } from 'lucide-react'
import EmptyState from '../ui/EmptyState'
import { vacancyPercent } from '../../utils/mappingHelpers'
import MappingSummary from './MappingSummary'

export default function MappingSidebar({
  hasActiveEvent,
  summary,
  households,
  evacuationSites,
  dispatchRoutes,
  selectedRoute,
  selectedHousehold,
  routeError,
  routeLoadingId,
  onRouteToHousehold,
  onStoredRoute,
}) {
  return (
    <aside className="mapmate-data-rail">
      <MappingSummary summary={summary} />
      <HouseholdRegistry hasActiveEvent={hasActiveEvent} households={households} selectedHousehold={selectedHousehold} routeLoadingId={routeLoadingId} onRouteToHousehold={onRouteToHousehold} />
      <EvacuationPins
        hasActiveEvent={hasActiveEvent}
        evacuationSites={evacuationSites}
        routeError={routeError}
      />
      <DispatchRoutes dispatchRoutes={dispatchRoutes} selectedRoute={selectedRoute} onStoredRoute={onStoredRoute} />
    </aside>
  )
}

function HouseholdRegistry({ hasActiveEvent, households, selectedHousehold, routeLoadingId, onRouteToHousehold }) {
  return (
    <section className="mapmate-data-panel grow">
      <header><strong>Household registry</strong><span>{households.length}</span></header>
      {households.length === 0 ? (
        <EmptyState
          title={hasActiveEvent ? 'No geotagged households found' : 'Hidden until event'}
          message={hasActiveEvent ? 'Only households with latitude and longitude appear here.' : 'The map stays plain before a declared disaster event.'}
        />
      ) : (
        <div className="mapmate-registry-list">
          {households.map((household) => (
            <button className={`mapmate-registry-row ${selectedHousehold?.id === household.id ? 'selected' : ''}`} type="button" onClick={() => onRouteToHousehold(household)} key={household.id}>
                <i className={`status-swatch ${household.marker_group}`} />
                <div>
                  <strong>{household.label}</strong>
                  <span>{household.purok} · {household.status_label}</span>
                </div>
                <b>{routeLoadingId === household.id ? 'Routing' : (household.accuracy_m ? `${household.accuracy_m} m` : '-')}</b>
            </button>
          ))}
        </div>
      )}
    </section>
  )
}

function EvacuationPins({ hasActiveEvent, evacuationSites, routeError }) {
  return (
    <section className="mapmate-data-panel">
      <header><strong>Evacuation vacancy</strong><span>{evacuationSites.length}</span></header>
      {routeError && <div className="form-error">{routeError}</div>}
      {evacuationSites.length === 0 ? (
        <EmptyState title="No evacuation pins visible" message={hasActiveEvent ? 'Evacuation centers with coordinates will appear here.' : 'Pins are hidden until the active event starts.'} />
      ) : (
        <div className="mapmate-site-list">
          {evacuationSites.map((site) => (
            <article className="mapmate-site-row" key={site.id}>
                <span className="mapmate-site-badge">{site.pin_label}</span>
                <div>
                  <strong>{site.name}</strong>
                  <span>{site.vacancy ?? '-'} vacant · {site.capacity ? `${Math.round(((site.capacity - (site.vacancy ?? 0)) / site.capacity) * 100)}% occupied` : 'No capacity'}</span>
                  <i className="mapmate-vacancy-meter"><em style={{ width: vacancyPercent(site) }} /></i>
                </div>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}

function DispatchRoutes({ dispatchRoutes, selectedRoute, onStoredRoute }) {
  return (
    <section className="mapmate-data-panel">
      <header><strong>Dispatched routes</strong><span>{dispatchRoutes.length}</span></header>
      {dispatchRoutes.length === 0 ? <EmptyState title="No dispatched routes" message="Saved routes appear here when available." /> : (
        <div className="mapmate-route-list">
          {dispatchRoutes.map((route) => <button className={selectedRoute?.route_id === route.route_id ? 'selected' : ''} type="button" key={route.route_id} onClick={() => onStoredRoute(route)}><i /><div><strong>{route.route_name}</strong><span>{route.team_name} · {route.assigned_area}</span></div><b>{route.distance_km ? `${route.distance_km} km` : '-'}</b></button>)}
        </div>
      )}
    </section>
  )
}
