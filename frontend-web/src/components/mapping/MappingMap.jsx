import { useEffect } from 'react'
import L from 'leaflet'
import { EyeOff, Layers, MapPin } from 'lucide-react'
import {
  CircleMarker,
  MapContainer,
  Marker,
  Polyline,
  Popup,
  Rectangle,
  TileLayer,
  Tooltip,
  useMap,
} from 'react-leaflet'
import 'leaflet/dist/leaflet.css'
import {
  escapeMarkerLabel,
  labelize,
  markerGroups,
  percent,
} from '../../utils/mappingHelpers'

export default function MappingMap({
  workspace,
  hasActiveEvent,
  layers,
  households,
  evacuationSites,
  rescueTeams,
  visibleRoutes,
  selectedRoute,
  mapCenter,
  mapBounds,
  onChangeLayer,
}) {
  return (
    <section className="mapping-map-shell">
      <MapContainer
        center={mapCenter}
        zoom={workspace.barangay.zoom}
        minZoom={14}
        maxZoom={19}
        scrollWheelZoom
        className="mapping-leaflet"
      >
        <FitBarangay center={mapCenter} bounds={mapBounds} zoom={workspace.barangay.zoom} />
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          url={workspace.barangay.tile_url}
          maxZoom={19}
        />
        <Rectangle bounds={mapBounds} pathOptions={{ color: '#1f3e5a', weight: 2, fillOpacity: 0.03 }} />

        {hasActiveEvent && layers.households && households.map((household) => (
          <HouseholdMarker household={household} key={household.id} />
        ))}

        {hasActiveEvent && layers.evacuationSites && evacuationSites.map((site) => (
          <Marker
            icon={siteIcon(site.pin_label)}
            position={[site.latitude, site.longitude]}
            key={site.id}
          >
            <Popup>
              <MapPopupTitle title={site.name} sub={site.center_type} />
              <div className="map-popup-grid">
                <span>Vacancy</span><strong>{site.vacancy ?? '-'}</strong>
                <span>Capacity</span><strong>{site.capacity ?? '-'}</strong>
                <span>Status</span><strong>{site.status}</strong>
              </div>
            </Popup>
          </Marker>
        ))}

        {hasActiveEvent && layers.rescueTeams && rescueTeams.map((team) => (
          <Marker
            icon={teamIcon(team.team_code || 'T')}
            position={[team.latitude, team.longitude]}
            key={team.id}
          >
            <Popup>
              <MapPopupTitle title={team.team_name} sub={team.responder_name} />
              <div className="map-popup-grid">
                <span>Area</span><strong>{team.assigned_area}</strong>
                <span>Status</span><strong>{labelize(team.status)}</strong>
                <span>Battery</span><strong>{percent(team.battery_level)}</strong>
              </div>
            </Popup>
          </Marker>
        ))}

        {hasActiveEvent && layers.routes && visibleRoutes.map((route) => (
          <Polyline
            pathOptions={{ color: '#2a5a90', weight: selectedRoute ? 5 : 4, opacity: selectedRoute ? 0.9 : 0.65 }}
            positions={route.coordinates}
            key={route.route_id}
          >
            <Tooltip sticky>{route.route_name}</Tooltip>
          </Polyline>
        ))}
      </MapContainer>

      {!hasActiveEvent && (
        <div className="map-standby-note">
          <EyeOff size={15} />
          Plain map only. Household status colors, evacuation pins, team markers, and dispatch routes appear after an active disaster event is declared.
        </div>
      )}

      <div className="map-layers-box">
        <div className="mapping-layer-card">
          <div className="mapping-card-title"><Layers size={14} /> Layers</div>
          <LayerToggle label="Household GPS" active={hasActiveEvent && layers.households} disabled={!hasActiveEvent} onClick={() => onChangeLayer('households')} />
          <LayerToggle label="Evacuation pins" active={hasActiveEvent && layers.evacuationSites} disabled={!hasActiveEvent} onClick={() => onChangeLayer('evacuationSites')} />
          <LayerToggle label="Rescue teams" active={hasActiveEvent && layers.rescueTeams} disabled={!hasActiveEvent} onClick={() => onChangeLayer('rescueTeams')} />
          <LayerToggle label="Dispatch routes" active={hasActiveEvent && layers.routes} disabled={!hasActiveEvent} onClick={() => onChangeLayer('routes')} />
        </div>
      </div>

      <div className="map-legend-box">
        <div className="mapping-legend-card">
          <div className="mapping-card-title"><MapPin size={14} /> Legend</div>
          {Object.entries(markerGroups).map(([key, item]) => (
            <div className="legend-row" key={key}>
              <span className={`status-swatch ${key}`} />
              <span>{item.label}</span>
            </div>
          ))}
          <div className="legend-row">
            <span className="route-swatch" />
            <span>Rescue route</span>
          </div>
        </div>
      </div>
    </section>
  )
}

function HouseholdMarker({ household }) {
  const color = markerGroups[household.marker_group]?.color || markerGroups.gray.color

  return (
    <CircleMarker
      center={[household.latitude, household.longitude]}
      pathOptions={{ color, fillColor: color, fillOpacity: 0.92, weight: 2 }}
      radius={8}
    >
      <Popup>
        <MapPopupTitle title={household.label} sub={`${household.purok} - ${household.status_label}`} />
        <div className="map-popup-grid">
          <span>Accuracy</span><strong>{household.accuracy_m ? `${household.accuracy_m} m` : '-'}</strong>
          <span>Battery</span><strong>{percent(household.last_battery_level)}</strong>
          <span>Reported</span><strong>{household.last_reported_at || '-'}</strong>
        </div>
      </Popup>
    </CircleMarker>
  )
}

function MapPopupTitle({ title, sub }) {
  return (
    <div className="map-popup-title">
      <strong>{title}</strong>
      <span>{sub}</span>
    </div>
  )
}

function LayerToggle({ label, active, disabled, onClick }) {
  return (
    <button className="layer-row" type="button" onClick={onClick} disabled={disabled}>
      <span>{label}</span>
      <span className={`layer-tog ${active ? '' : 'off'}`} />
    </button>
  )
}

function FitBarangay({ center, bounds, zoom }) {
  const map = useMap()

  useEffect(() => {
    if (bounds?.length === 2) {
      map.fitBounds(bounds, { padding: [22, 22], maxZoom: zoom })
      return
    }

    map.setView(center, zoom)
  }, [bounds, center, map, zoom])

  return null
}

function siteIcon(label) {
  return L.divIcon({
    className: 'map-div-icon map-site-icon',
    html: `<span>${escapeMarkerLabel(label || 'E')}</span>`,
    iconSize: [34, 34],
    iconAnchor: [17, 17],
  })
}

function teamIcon(label) {
  const safeLabel = String(label || 'T').slice(0, 2).toUpperCase()

  return L.divIcon({
    className: 'map-div-icon map-team-icon',
    html: `<span>${escapeMarkerLabel(safeLabel)}</span>`,
    iconSize: [36, 36],
    iconAnchor: [18, 18],
  })
}
