import { Route } from 'lucide-react'
import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'
import { vacancyPercent } from '../../utils/mappingHelpers'

export default function MappingSidebar({
  hasActiveEvent,
  households,
  evacuationSites,
  routeError,
  routeLoadingId,
  onRouteToSite,
}) {
  return (
    <aside className="mapping-side">
      <HouseholdRegistry hasActiveEvent={hasActiveEvent} households={households} />
      <EvacuationPins
        hasActiveEvent={hasActiveEvent}
        evacuationSites={evacuationSites}
        routeError={routeError}
        routeLoadingId={routeLoadingId}
        onRouteToSite={onRouteToSite}
      />
    </aside>
  )
}

function HouseholdRegistry({ hasActiveEvent, households }) {
  return (
    <section className="panel">
      <div className="panel-head">
        <span className="panel-title">Household geotag registry</span>
        <Badge tone={hasActiveEvent ? 'green' : 'gray'}>{households.length}</Badge>
      </div>
      {households.length === 0 ? (
        <EmptyState
          title={hasActiveEvent ? 'No geotagged households found' : 'Hidden until event'}
          message={hasActiveEvent ? 'Only households with latitude and longitude appear here.' : 'The map stays plain before a declared disaster event.'}
        />
      ) : (
        <div className="geotag-registry-list">
          {households.slice(0, 8).map((household) => (
            <article className="geotag-registry-card" key={household.id}>
              <div className="geotag-registry-head">
                <span className={`status-swatch ${household.marker_group}`} />
                <div>
                  <strong>{household.label}</strong>
                  <span>{household.purok} - {household.status_label}</span>
                </div>
                <span className="accuracy-pill">{household.accuracy_m ? `${household.accuracy_m} m` : 'No accuracy'}</span>
              </div>
              <div className="geotag-coordinate">
                {household.latitude.toFixed(5)}, {household.longitude.toFixed(5)}
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}

function EvacuationPins({ hasActiveEvent, evacuationSites, routeError, routeLoadingId, onRouteToSite }) {
  return (
    <section className="panel">
      <div className="panel-head">
        <span className="panel-title">Evacuation pins and vacancy</span>
        <Badge tone={hasActiveEvent ? 'blue' : 'gray'}>{evacuationSites.length}</Badge>
      </div>
      {routeError && <div className="form-error">{routeError}</div>}
      {evacuationSites.length === 0 ? (
        <EmptyState title="No evacuation pins visible" message={hasActiveEvent ? 'Evacuation centers with coordinates will appear here.' : 'Pins are hidden until the active event starts.'} />
      ) : (
        <div className="map-site-list">
          {evacuationSites.map((site) => (
            <article className="map-site-card" key={site.id}>
              <div className="map-site-top">
                <span className="map-site-pin-mini">{site.pin_label}</span>
                <div>
                  <strong>{site.name}</strong>
                  <span>{site.center_type}</span>
                </div>
              </div>
              <div className="vacancy-meter">
                <span style={{ width: vacancyPercent(site) }} />
              </div>
              <div className="map-site-footer">
                <span>{site.vacancy ?? '-'} vacant of {site.capacity ?? '-'}</span>
                <button className="btn btn-secondary btn-sm" type="button" onClick={() => onRouteToSite(site)} disabled={routeLoadingId === site.id}>
                  <Route size={13} />
                  {routeLoadingId === site.id ? 'Routing' : 'Route'}
                </button>
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}
