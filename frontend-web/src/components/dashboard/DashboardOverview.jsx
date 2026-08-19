import { useEffect, useMemo, useState } from 'react'
import {
  CircleMarker,
  MapContainer,
  Rectangle,
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
import { getMappingOverview } from '../../api/mappingApi'
import {
  defaultWorkspace,
  markerGroups,
  percent as mapPercent,
} from '../../utils/mappingHelpers'
import { statusTone } from '../../utils/dashboardHelpers'
import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'
import LoadingState from '../ui/LoadingState'

export default function DashboardOverview({
  dashboard,
  hasActiveEvent,
  onOpenModule,
}) {
  return (
    <aside className="dashboard-overview" aria-label="Dashboard side information">
      <WeatherCard weather={dashboard.weather} hasActiveEvent={hasActiveEvent} onOpenWeather={() => onOpenModule('/weather')} />
      <DashboardMapCard hasActiveEvent={hasActiveEvent} onOpenMap={() => onOpenModule('/mapping')} />
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
        <button className="panel-link" type="button" onClick={onOpenWeather}>Full view -&gt;</button>
      </div>

      {hasWeather ? (
        <div className="dashboard-weather-compact">
          <div className="dashboard-weather-icon">
            <CloudSun size={30} />
          </div>
          <div className="dashboard-weather-main">
            <span>{weather.condition_name || 'Current weather'}</span>
            <strong>{weather.temperature ?? '-'} C</strong>
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

function DashboardMapCard({ hasActiveEvent, onOpenMap }) {
  const [workspace, setWorkspace] = useState(defaultWorkspace)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    let ignore = false

    async function loadMap() {
      setIsLoading(true)

      try {
        const data = await getMappingOverview()

        if (!ignore) {
          setWorkspace({ ...defaultWorkspace, ...data })
        }
      } catch {
        if (!ignore) {
          setWorkspace(defaultWorkspace)
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadMap()

    return () => {
      ignore = true
    }
  }, [hasActiveEvent])

  const mapCenter = useMemo(() => [
    workspace.barangay.center.latitude,
    workspace.barangay.center.longitude,
  ], [workspace.barangay.center.latitude, workspace.barangay.center.longitude])
  const mapBounds = useMemo(() => workspace.barangay.bounds, [workspace.barangay.bounds])
  const households = hasActiveEvent ? workspace.households : []

  return (
    <section className="overview-card">
      <div className="panel-head">
        <span className="panel-title"><Map size={15} />Household map</span>
        <button className="panel-link" type="button" onClick={onOpenMap}>Full view -&gt;</button>
      </div>

      <div className="dashboard-map-preview">
        {isLoading ? (
          <LoadingState />
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
            <Rectangle bounds={mapBounds} pathOptions={{ color: '#1f3e5a', weight: 2, fillOpacity: 0.02 }} />
            {households.map((household) => (
              <HouseholdPoint household={household} key={household.id} />
            ))}
          </MapContainer>
        )}
      </div>

      <div className="dashboard-side-metrics">
        <div><strong>{workspace.summary.gps_tagged_households || households.length}</strong><span>GPS tagged</span></div>
        <div><strong>{workspace.summary.evacuation_sites || 0}</strong><span>Evac sites</span></div>
        <div><strong>{hasActiveEvent ? households.length : 0}</strong><span>Status points</span></div>
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
        <button className="panel-link" type="button" onClick={onOpenRequests}>Full view -&gt;</button>
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
