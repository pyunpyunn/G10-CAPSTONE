import { useEffect, useMemo } from 'react'
import { useModuleData } from '../../utils/useModuleData'
import {
  CircleMarker,
  GeoJSON,
  MapContainer,
  TileLayer,
  Tooltip,
  useMap,
} from 'react-leaflet'
import 'leaflet/dist/leaflet.css'
import {
  CloudSun,
  Map,
  PackageCheck,
  Thermometer,
  Wind,
} from 'lucide-react'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faArrowRight } from '@fortawesome/free-solid-svg-icons'
import { getMappingOverview } from '../../api/mappingApi'
import { getWeatherWorkspace } from '../../api/weatherApi'
import {
  defaultWorkspace,
  markerGroups,
  percent as mapPercent,
} from '../../utils/mappingHelpers'
import { outsideBoundaryMask } from '../../utils/boundaryMask'
import { statusTone } from '../../utils/dashboardHelpers'
import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'
import LoadingState from '../ui/LoadingState'

export default function DashboardOverview({
  dashboard,
  hasActiveEvent,
  onOpenModule,
  disasterAction,
}) {
  const { data: weatherWorkspace } = useModuleData(getWeatherWorkspace, ['weather', 'disasters'])
  const latestSavedWeather = weatherWorkspace?.latest_snapshot || null

  return (
    <aside className="dashboard-overview" aria-label="Dashboard side information">
      <div className="dashboard-layout-actions">{disasterAction}</div>
      <WeatherCard weather={latestSavedWeather || dashboard.weather} hasActiveEvent={hasActiveEvent} onOpenWeather={() => onOpenModule('/weather')} />
      <DashboardMapCard
        hasActiveEvent={hasActiveEvent}
        reportedHouseholds={dashboard.households.reported}
        onOpenMap={() => onOpenModule('/mapping')}
      />
      <RequestCard requests={dashboard.requests} onOpenRequests={() => onOpenModule('/resources-requests')} />
    </aside>
  )
}

function WeatherCard({ weather, hasActiveEvent, onOpenWeather }) {
  const hasWeather = Boolean(weather)

  return (
    <section className="overview-card dashboard-weather-card">
      <div className="panel-head">
        <span className="panel-title"><CloudSun size={15} />Weather update</span>
        <button className="panel-link" type="button" onClick={onOpenWeather}>
          Full view <FontAwesomeIcon icon={faArrowRight} />
        </button>
      </div>

      {hasWeather ? (
        <div className="dashboard-weather-compact">
          <div className="dashboard-weather-icon">
            <CloudSun size={30} />
          </div>
          <div className="dashboard-weather-main">
            <span>{weather.condition_name || 'Current weather'}</span>
            <strong>{weather.temperature ?? '-'} C</strong>
            {weather.observed_at && <small>Updated {weather.observed_at}</small>}
          </div>
          <div className="dashboard-weather-metrics">
            <span><Thermometer size={13} />Temp</span>
            <strong>{weather.temperature ?? '-'} C</strong>
            <span><Wind size={13} />Wind</span>
            <strong>{weather.wind_speed ?? '-'} km/h</strong>
          </div>
        </div>
      ) : (
        <EmptyState
          title={hasActiveEvent ? 'No weather snapshot yet' : 'No weather alert'}
          message={hasActiveEvent ? 'Refresh Weather Updates to save the latest snapshot.' : 'Latest weather appears after a saved snapshot.'}
        />
      )}
    </section>
  )
}

function DashboardMapCard({ hasActiveEvent, reportedHouseholds, onOpenMap }) {
  const { data, loading: isLoading } = useModuleData(getMappingOverview, ['mapping', 'households', 'dispatch', 'disasters'])
  const workspace = useMemo(() => data ? { ...defaultWorkspace, ...data } : defaultWorkspace, [data])
  const mapCenter = useMemo(() => [
    workspace.barangay.center.latitude,
    workspace.barangay.center.longitude,
  ], [workspace.barangay.center.latitude, workspace.barangay.center.longitude])
  const mapBounds = useMemo(() => workspace.barangay.bounds, [workspace.barangay.bounds])
  const boundaryMask = useMemo(() => outsideBoundaryMask(workspace.barangay.boundary), [workspace.barangay.boundary])
  const households = hasActiveEvent ? workspace.households : []

  return (
    <section className="overview-card">
      <div className="panel-head">
        <span className="panel-title"><Map size={15} />Household map</span>
        <button className="panel-link" type="button" onClick={onOpenMap}>
          Full view <FontAwesomeIcon icon={faArrowRight} />
        </button>
      </div>

      <div className="dashboard-map-preview">
        {isLoading ? (
          <LoadingState inline />
        ) : (
          <MapContainer
            center={mapCenter}
            zoom={workspace.barangay.zoom}
            minZoom={14}
            maxZoom={19}
            scrollWheelZoom
            className="dashboard-leaflet"
          >
            <FitBarangay center={mapCenter} bounds={mapBounds} zoom={workspace.barangay.zoom} />
            <TileLayer
              attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
              url={workspace.barangay.tile_url}
              maxZoom={19}
            />
            {boundaryMask && <GeoJSON
              key={`mask-${workspace.barangay.name}-${JSON.stringify(workspace.barangay.boundary)}`}
              data={boundaryMask}
              interactive={false}
              style={{ color: 'transparent', weight: 0, fillColor: '#000000', fillOpacity: 0.2, fillRule: 'evenodd' }}
            />}
            {workspace.barangay.boundary && <GeoJSON
              key={`${workspace.barangay.name}-${JSON.stringify(workspace.barangay.boundary)}`}
              data={workspace.barangay.boundary}
              style={{ color: '#1f3e5a', weight: 2, fillOpacity: 0.03 }}
            />}
            {households.map((household) => (
              <HouseholdPoint household={household} key={household.id} />
            ))}
          </MapContainer>
        )}
      </div>

      <div className="dashboard-side-metrics">
        <div><strong>{workspace.summary.gps_tagged_households ?? 0}</strong><span>GPS tagged</span></div>
        <div><strong>{workspace.summary.evacuation_sites ?? 0}</strong><span>Active evac sites</span></div>
        <div><strong>{hasActiveEvent ? reportedHouseholds ?? 0 : 0}</strong><span>Status reported</span></div>
      </div>
    </section>
  )
}

function HouseholdPoint({ household }) {
  const color = markerGroups[household.marker_group]?.color || markerGroups.gray.color

  return (
    <CircleMarker
      center={[household.latitude, household.longitude]}
      pathOptions={{ color, fillColor: color, fillOpacity: 0.9, weight: 2 }}
      radius={7}
    >
      <Tooltip>
        {household.label} - {household.status_label} - Battery {mapPercent(household.last_battery_level)}
      </Tooltip>
    </CircleMarker>
  )
}

function FitBarangay({ center, bounds, zoom }) {
  const map = useMap()

  useEffect(() => {
    if (bounds?.length === 2) {
      map.fitBounds(bounds, { padding: [12, 12], maxZoom: zoom })
      return
    }

    map.setView(center, zoom)
  }, [bounds, center, map, zoom])

  return null
}

function RequestCard({ requests, onOpenRequests }) {
  return (
    <section className="overview-card">
      <div className="panel-head">
        <span className="panel-title"><PackageCheck size={15} />Requests</span>
        <button className="panel-link" type="button" onClick={onOpenRequests}>
          Full view <FontAwesomeIcon icon={faArrowRight} />
        </button>
      </div>
      <div className="dashboard-side-metrics">
        <div><strong>{requests.needs_validation}</strong><span>Needs validation</span></div>
        <div><strong>{requests.validated}</strong><span>Validated</span></div>
        <div><strong>{requests.released}</strong><span>Released</span></div>
      </div>
      {requests.latest.length > 0 ? (
        <div className="overview-request-table">
          <table>
            <thead><tr><th>Request from</th><th>Request</th><th>Status</th></tr></thead>
            <tbody>
              {requests.latest.map((request) => (
                <tr key={request.request_id}>
                  <td>{request.requested_by}</td>
                  <td>{request.item_name}</td>
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
