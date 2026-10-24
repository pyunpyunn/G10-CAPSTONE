import SitioPinLayer from './SitioPinLayer'
import { pointReady, siteColor } from '../../utils/mappingDisplay'
import { useEffect, useMemo, useRef, useState } from 'react'
import L from 'leaflet'
import { CircleMarker, GeoJSON, ImageOverlay, MapContainer, Marker, Polyline, Popup, TileLayer, Tooltip, useMap, useMapEvents } from 'react-leaflet'
import 'leaflet/dist/leaflet.css'
import { outsideBoundaryMask } from '../../utils/boundaryMask'
import { escapeMarkerLabel } from '../../utils/mappingHelpers'
import { riskColors, visibleHazardLayers } from '../../utils/hazardMapping'
import { statusColor } from '../../utils/statusPalette'

function pin(site) {
  const color = siteColor(site)
  return L.divIcon({ className: 'reference-map-pin', html: `<svg width="28" height="36" viewBox="0 0 28 36" aria-hidden="true"><path d="M14 34C10 28 2 22 2 14a12 12 0 0 1 24 0c0 8-8 14-12 20z" fill="${color}" stroke="#fff" stroke-width="2"/><circle cx="14" cy="14" r="4" fill="#fff"/></svg>`, iconSize: [28, 36], iconAnchor: [14, 36] })
}
const officeIcon = () => L.divIcon({ className: 'reference-office-icon', html: '<span aria-hidden="true">+</span>', iconSize: [30, 30], iconAnchor: [15, 15] })
function MapController({ workspace, selected, fullscreen, onReady, siteRoute, routes }) {
  const map = useMap()
  const fittedRoutes = useRef('')
  const focus = workspace.barangay
  useEffect(() => {
    onReady(map)
    const observer = new ResizeObserver(() => {
      if (map.getPane('mapPane') && map.getContainer().isConnected) map.invalidateSize()
    })
    observer.observe(map.getContainer())
    return () => { observer.disconnect(); onReady(null) }
  }, [map, onReady])
  const fitKey = JSON.stringify([focus.boundary, focus.bounds, focus.center, focus.zoom])
  useEffect(() => {
    const boundaryBounds = focus.boundary ? L.geoJSON(focus.boundary).getBounds() : null
    if (boundaryBounds?.isValid()) map.setView(boundaryBounds.getCenter(), 16, { animate: false })
    else if (Array.isArray(focus.bounds) && focus.bounds.length === 2) map.setView(L.latLngBounds(focus.bounds).getCenter(), 16, { animate: false })
    else map.setView([Number(focus.center.latitude), Number(focus.center.longitude)], 16, { animate: false })
  // Keep the user's zoom when live GPS data refreshes.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [map, fitKey])
  useEffect(() => {
    const key = `${workspace.active_event?.event_id}:${routes.map(route => route.route_id).join(',')}`
    if (selected || !routes.length || fittedRoutes.current === key) return
    const coordinates = routes.flatMap(route => route.coordinates || [])
    const bounds = L.latLngBounds(coordinates)
    if (bounds.isValid()) { map.fitBounds(bounds, { padding: [60, 60], maxZoom: 17, animate: false }); fittedRoutes.current = key }
  }, [map, routes, selected, workspace.active_event?.event_id])
  useEffect(() => {
    if (!selected) return
    const row = selected.row
    if (selected.kind === 'route' && row.coordinates?.length > 1) map.fitBounds(row.coordinates, { padding: [50, 50], maxZoom: 18 })
    else if (pointReady(row)) map.flyTo([Number(row.latitude), Number(row.longitude)], Math.max(map.getZoom(), 17), { duration: .5 })
  }, [map, selected])
  useEffect(() => {
    if (siteRoute?.coordinates?.length >= 2) map.fitBounds(siteRoute.coordinates, { padding:[60,60], maxZoom:17, animate:false })
  }, [map, siteRoute])
  useEffect(() => { map.invalidateSize() }, [map, fullscreen])
  return null
}
export default function MappingScene({ workspace, sitioPin, planningFeatures = [], hazardType = '', households, sites, routes, siteRoute, layers, basemap, selected, onSelect, onReady, fullscreen }) {
  const focus = workspace.barangay
  const center = focus.center
  const mask = useMemo(() => outsideBoundaryMask(focus.boundary), [focus.boundary])
  const tile = focus.basemaps?.[basemap] || focus.basemaps?.street || { tile_url: focus.tile_url, attribution: '&copy; OpenStreetMap contributors', max_zoom: 19 }
  if (!pointReady(center)) return <div className="empty">Map coordinates have not been configured.</div>
  return <MapContainer className="map-canvas" center={[Number(center.latitude), Number(center.longitude)]} zoom={16} zoomControl={false} attributionControl scrollWheelZoom>
    <CoordinateReadout />
    <MapController workspace={workspace} selected={selected} fullscreen={fullscreen} onReady={onReady} siteRoute={siteRoute} routes={layers.routes ? routes : []} />
    {sitioPin && <SitioPinLayer key={sitioPin.id} sitio={sitioPin} />}
    {tile.tile_url && <TileLayer key={basemap} url={tile.tile_url} attribution={tile.attribution} maxZoom={tile.max_zoom || 19} />}
    {mask && <GeoJSON key={`mask-${JSON.stringify(focus.boundary)}`} data={mask} interactive={false} style={{ weight: 0, fillColor: '#000000', fillOpacity: .5, fillRule: 'evenodd' }} />}
    {focus.boundary && <GeoJSON key={`boundary-${JSON.stringify(focus.boundary)}`} data={focus.boundary} interactive={false} style={{ color: '#7a8ba3', weight: 2, dashArray: '7 6', fillOpacity: 0 }} />}
    {layers.hazards && visibleHazardLayers(workspace.hazard_layers, hazardType).filter(layer => layer.raster).map(layer => <ImageOverlay key={layer.id} url={`${import.meta.env.BASE_URL}${layer.raster.path}`} bounds={layer.raster.bounds} opacity={0.62} attribution={layer.source} />)}
    {planningFeatures.filter(f => layers[{ hazard: 'hazards', evacuation_center: 'planningCenters', safe_route: 'safeRoutes' }[f.properties.kind]]).map(feature => <GeoJSON key={`${feature.id}-${JSON.stringify(feature)}`} data={feature} style={{ color: feature.properties.kind === 'safe_route' ? '#15803d' : feature.properties.color || riskColors[feature.properties.risk_level] || '#64748b', weight: feature.properties.kind === 'safe_route' ? 5 : 2, fillOpacity: .24 }} pointToLayer={(_feature, latlng) => L.circleMarker(latlng, { radius: 8, color: '#fff', weight: 2, fillColor: '#15803d', fillOpacity: 1 })}><Popup><b>{feature.properties.name}</b><div>{feature.properties.hazard_type} · {feature.properties.classification || `${feature.properties.risk_level} risk`}</div><div>{feature.properties.kind.replaceAll('_', ' ')}</div>{feature.properties.destination && <div>Destination: {feature.properties.destination}</div>}<div>Source: {feature.properties.source}</div><div>{feature.properties.builtin ? `Source date: ${feature.properties.source_date}` : `Assessed: ${feature.properties.assessed_at}`}</div>{feature.properties.reviewed_by && <div>Reviewed by: {feature.properties.reviewed_by}</div>}</Popup></GeoJSON>)}
    {layers.routes && routes.map(route => Array.isArray(route.coordinates) && route.coordinates.length >= 2 ? <Polyline key={route.route_id} positions={route.coordinates} eventHandlers={{ click: () => onSelect('route', route) }} pathOptions={{ color: statusColor(route.status === 'on_demand' ? 'assigned' : route.status), weight: String(selected?.row?.route_id) === String(route.route_id) ? 5 : 4, dashArray: '10 10', className: 'reference-route-flow', opacity: selected?.kind === 'route' && String(selected.row.route_id) !== String(route.route_id) ? .28 : .95 }}><Tooltip permanent={false} opacity={1} direction="center">{route.team_name || route.route_name}{route.distance_km != null ? ` · ${route.distance_km} km` : ''}</Tooltip></Polyline> : null)}
    {siteRoute?.coordinates?.length >= 2 && <Polyline positions={siteRoute.coordinates} pathOptions={{ color:'#38bdf8', weight:5, opacity:1 }}><Tooltip permanent direction="center">{`${Number(siteRoute.distance_km).toFixed(2)} km · ${siteRoute.origin_name} → ${siteRoute.destination_name}`}</Tooltip></Polyline>}
    {layers.households && households.filter(pointReady).map(household => <CircleMarker key={household.id} center={[Number(household.latitude), Number(household.longitude)]} radius={selected?.kind === 'household' && String(selected.row.id) === String(household.id) ? 8 : 5} pathOptions={{ color: '#fff', fillColor: statusColor(household.status_key), fillOpacity: 1, weight: 2 }} eventHandlers={{ click: () => onSelect('household', household) }}><Tooltip permanent={false} opacity={1} direction="right">{household.label} · {household.purok}</Tooltip></CircleMarker>)}
    {layers.evacuation && sites.filter(pointReady).map(site => <Marker key={site.id} icon={pin(site)} position={[Number(site.latitude), Number(site.longitude)]} eventHandlers={{ click: () => onSelect('site', site) }}><Tooltip permanent={false} opacity={1} direction="right">{site.name}</Tooltip></Marker>)}
    {layers.office && workspace.rescue_offices.filter(pointReady).map(office => <Marker key={office.id} position={[Number(office.latitude), Number(office.longitude)]} icon={officeIcon()} eventHandlers={{ click: () => onSelect('office', office) }}><Tooltip permanent={false} opacity={1} direction="right">{office.name}</Tooltip></Marker>)}
    {layers.teams && workspace.rescue_teams.filter(pointReady).map(team => <Marker key={team.id} position={[Number(team.latitude), Number(team.longitude)]} icon={L.divIcon({ className: 'reference-team-icon', html: `<span style="background:${statusColor(team.status)}">${escapeMarkerLabel(String(team.team_code || team.responder_name || 'R').slice(0, 2))}</span>`, iconSize: [28, 28], iconAnchor: [14, 14] })} eventHandlers={{ click: () => onSelect('team', team) }}><Tooltip permanent={false} opacity={1}>{team.team_name} · {team.responder_name} · {team.gps_is_stale ? 'Last GPS' : 'Live GPS'} · {team.logged_at}</Tooltip></Marker>)}
  </MapContainer>
}

function CoordinateReadout() {
  const [point, setPoint] = useState(null)
  useMapEvents({ click: event => setPoint(event.latlng) })
  return point ? <div className="map-coordinate-readout" role="status" aria-live="polite">Lat {point.lat.toFixed(6)} · Lng {point.lng.toFixed(6)}<button type="button" aria-label="Dismiss coordinates" onClick={event => { event.stopPropagation(); setPoint(null) }}>×</button></div> : null
}
