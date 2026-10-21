import L from 'leaflet'
import { createPortal } from 'react-dom'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useOutletContext } from 'react-router-dom'
import { getMappingOverview, getHouseholdRoute, getEvacuationRoute } from '../api/mappingApi'
import HazardMapControls from '../components/mapping/HazardMapControls'
import HazardPlanning from '../components/mapping/HazardPlanning'
import { hazardFeatures } from '../utils/hazardMapping'
import MappingScene from '../components/mapping/MappingScene'
import { pointReady, siteColor } from '../utils/mappingDisplay'
import { useModuleData } from '../utils/useModuleData'
import { normalizeWorkspaceData, isActiveRouteStatus, isHouseholdRouteAllowed, labelize } from '../utils/mappingHelpers'
import { canOpenWebPage } from '../utils/personnelAccess'
import { statusColor } from '../utils/statusPalette'

const filters = [{ key:'unsafe', label:'Unsafe' }, { key:'unchecked', label:'Unchecked' }, { key:'evacuated', label:'Evacuated' }, { key:'safe', label:'Safe' }]
const groupKey = row => ['needs_help','needs_assistance','injured','missing','trapped','displaced','not_evacuated','deceased','unreachable'].includes(row.status_key) ? 'unsafe' : ['safe_at_home','active','returned','checked'].includes(row.status_key) ? 'safe' : ['relocated'].includes(row.status_key) ? 'evacuated' : row.status_key || 'unchecked'
const shown = (value, suffix = '') => value === null || value === undefined || value === '' ? '—' : `${value}${suffix}`
export default function MappingPage() {
  const navigate = useNavigate()
  const { user } = useOutletContext()
  const [purokId, setPurokId] = useState('')
  const [hazardType, setHazardType] = useState('Flood')
  const loader = useCallback(async () => normalizeWorkspaceData(await getMappingOverview(purokId ? { purok_id:purokId } : {})), [purokId])
  const { data: workspace, error, loading, refresh } = useModuleData(loader, ['mapping','households','dispatch','disasters'], { keepPreviousData:true })
  const [layers, setLayers] = useState({ hazards:true, households:true, evacuation:true, office:true, routes:true, teams:true })
  const [visibleStatuses, setVisibleStatuses] = useState(Object.fromEntries(filters.map(f => [f.key,true])))
  const [layersOpen, setLayersOpen] = useState(true)
  const [tab, setTab] = useState('households')
  const [panel, setPanel] = useState('normal')
  const [basemap, setBasemap] = useState('street')
  const [fullscreen, setFullscreen] = useState(false)
  const [selection, setSelection] = useState(null)
  const [preview, setPreview] = useState(null)
  const [siteRoute, setSiteRoute] = useState(null)
  const [routeState, setRouteState] = useState(null)
  const map = useRef(null), requestVersion = useRef(0)
  const onMapReady = useCallback(value => { map.current = value }, [])
  const active = Boolean(workspace?.active_event)
  const [liveTeams, setLiveTeams] = useState(null)
  const trackingEventId = workspace?.active_event?.event_id
  useEffect(() => {
    if (!trackingEventId) return
    let cancelled = false, running = false
    const update = async () => {
      if (running || document.hidden) return
      running = true
      try {
        const current = await getMappingOverview()
        if (!cancelled && current.active_event?.event_id === trackingEventId) setLiveTeams({ eventId: trackingEventId, rows: current.rescue_teams || [] })
      } catch { /* Keep the last recorded positions during temporary connection loss. */ }
      finally { running = false }
    }
    const timer = window.setInterval(update, 5000)
    return () => { cancelled = true; window.clearInterval(timer) }
  }, [trackingEventId])
  const mapWorkspace = useMemo(() => workspace && liveTeams && liveTeams.eventId === trackingEventId ? { ...workspace, rescue_teams: liveTeams.rows } : workspace, [workspace, liveTeams, trackingEventId])
  const households = useMemo(() => active ? workspace.households.filter(row => visibleStatuses[groupKey(row)] !== false) : [], [active, workspace, visibleStatuses])
  const sites = active ? workspace.evacuation_sites : []
  const routes = active ? workspace.dispatch_routes.filter(isActiveRouteStatus) : []
  const selected = useMemo(() => {
    if (!selection || !workspace) return null
    const rows = selection.kind === 'household' ? households : selection.kind === 'site' ? workspace.evacuation_sites : selection.kind === 'route' ? workspace.dispatch_routes : selection.kind === 'team' ? mapWorkspace.rescue_teams : workspace.rescue_offices
    if (selection.kind !== 'office' && !active) return null
    if (selection.kind === 'household' && !layers.households || selection.kind === 'site' && !layers.evacuation || selection.kind === 'route' && !layers.routes || selection.kind === 'office' && !layers.office || selection.kind === 'team' && !layers.teams) return null
    const row = rows.find(item => String(selection.id) === String(item.route_id ?? item.id))
    return row ? { kind:selection.kind, row } : null
  }, [selection, workspace, mapWorkspace, households, active, layers])
  const eventId = workspace?.active_event?.event_id
  const selectedPreview = selected?.kind === 'household' && preview?.householdId === selected.row.household_id && preview?.eventId === eventId ? preview.route : null
  const selectedSiteRoute = selected?.kind === 'site' && String(siteRoute?.siteId) === String(selected.row.id) && siteRoute?.eventId === eventId ? siteRoute : null
  const selectedRouteState = selected?.kind === 'household' && routeState?.householdId === selected.row.household_id && routeState?.eventId === eventId ? routeState : null
  useEffect(() => {
    const onKey = event => { if (event.key === 'Escape') { setFullscreen(false); setSelection(null); requestVersion.current++ } }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])
  const select = useCallback(async (kind, row) => {
    setSelection({ kind, id:row.route_id ?? row.id }); setPreview(null); setSiteRoute(null)
    const version = ++requestVersion.current
    if (kind === 'site' && pointReady(row)) {
      setSiteRoute({ siteId:row.id, eventId, loading:true })
      try {
        const route = await getEvacuationRoute(row.id)
        if (requestVersion.current === version) setSiteRoute({ siteId:row.id, eventId, route })
      } catch (failure) {
        if (requestVersion.current === version) setSiteRoute({ siteId:row.id, eventId, error:failure.response?.data?.message || 'A route to this center cannot be generated right now.' })
      }
      return
    }
    if (kind !== 'household' || !active || !pointReady(row)) return
    setRouteState({ householdId:row.household_id, eventId, loading:true })
    try {
      const route = await getHouseholdRoute(row.household_id)
      if (requestVersion.current === version) { setPreview({ householdId:row.household_id, eventId, route }); setRouteState(null) }
    } catch (failure) { if (requestVersion.current === version) setRouteState({ householdId:row.household_id, eventId, error:failure.response?.data?.message || 'A route cannot be generated right now.' }) }
  }, [active, eventId])
  const liveLayers = { ...layers, households:active && layers.households, evacuation:active && layers.evacuation, routes:active && layers.routes, teams:active && layers.teams }
  const plottedRoutes = selectedPreview ? [...routes, { ...selectedPreview, route_id:'household-preview', status:'on_demand', route_name:`Route to ${selected.row.label}` }] : routes
  function changePurok(value) { requestVersion.current++; setSelection(null); setPreview(null); setSiteRoute(null); setPurokId(value) }
  function clear() { requestVersion.current++; setSelection(null); setPreview(null); setSiteRoute(null); setRouteState(null) }
  const layerItems = [['hazards','Hazard areas'],['households','Households'],['evacuation','Evacuation'],['office','Rescue office'],['routes','Routes'],['teams','Rescue teams']]
  const planningFeatures = hazardFeatures(workspace?.hazard_layers, purokId, hazardType).filter(feature => feature.properties.kind === 'hazard')
  function fitHazards() {
    const bounds = L.geoJSON(planningFeatures.length ? { type:'FeatureCollection', features:planningFeatures } : workspace.barangay.boundary).getBounds()
    if (bounds.isValid()) map.current?.fitBounds(bounds, { padding:[40,40], maxZoom:18, animate:false })
  }
  const row = selected?.row
  const content = <section className={`mapping-reference ${fullscreen ? 'mapping-fullscreen' : ''}`} aria-label="Mapping">
    {loading && !workspace ? <div className="empty" role="status">Loading map…</div> : error && !workspace ? <div className="form-error" role="alert">{error}<button type="button" onClick={refresh}>Retry</button></div> : workspace && <div className={`mapw ${fullscreen ? 'full' : ''}`}>
      <MappingScene workspace={mapWorkspace} planningFeatures={planningFeatures} hazardType={hazardType} households={households} sites={sites} routes={plottedRoutes} siteRoute={selectedSiteRoute?.route} layers={liveLayers} basemap={basemap} selected={selected} onSelect={select} onReady={onMapReady} fullscreen={fullscreen} />
      <div className={`fl lay ${layersOpen ? '' : 'min'}`}><button type="button" className="lh" onClick={() => setLayersOpen(!layersOpen)} aria-expanded={layersOpen}>Layers<i>⌃</i></button>{layerItems.map(([key,label]) => <div className={`ly ${liveLayers[key] ? 'on' : ''} ${!active && !['office','hazards'].includes(key) ? 'dis' : ''}`} key={key}><button type="button" className="layer-toggle" aria-pressed={liveLayers[key]} disabled={!active && !['office','hazards'].includes(key)} onClick={() => { setLayers(current => ({ ...current, [key]:!current[key] })); clear() }}><span className="sw"><i /></span>{label}</button>{key === 'households' && active && layers.households && <span className="sd">{filters.map(filter => <button type="button" className={`filter-dot ${visibleStatuses[filter.key] ? '' : 'off'}`} key={filter.key} style={{ '--c':statusColor(filter.key) }} title={filter.label} aria-label={`Show ${filter.label.toLowerCase()} households`} aria-pressed={visibleStatuses[filter.key]} onClick={() => setVisibleStatuses(current => ({ ...current,[filter.key]:!current[filter.key] }))} />)}</span>}</div>)}{layers.hazards && <HazardMapControls workspace={workspace} hazardType={hazardType} onHazardType={setHazardType} onFitHazards={fitHazards} />}</div>
      <div className="fl tr"><span className={`ev ${active ? '' : 'off'}`}><i />{workspace.active_event?.name || 'No active event'}</span><select value={purokId} onChange={event => changePurok(event.target.value)} aria-label="Filter map by purok"><option value="">All puroks</option>{workspace.filters.purok_options?.map(purok => <option key={purok.purok_id} value={purok.purok_id}>{purok.sitio_name} · {purok.purok_name}</option>)}</select><button type="button" className="ib" onClick={() => setFullscreen(!fullscreen)} aria-label={fullscreen ? 'Exit full map' : 'Full map'}>⤢</button></div>
      {active && <aside className={`fl sheet ${panel === 'wide' ? 'wide' : panel === 'hidden' ? 'hide' : ''}`} aria-label="Map records"><div className="sh1"><div className="sg2">{[['households','Households'],['sites','Evacuation'],['routes','Routes']].map(([key,label]) => <button type="button" key={key} className={tab === key ? 'on' : ''} aria-pressed={tab === key} onClick={() => setTab(key)}>{label}</button>)}</div><button type="button" className="ib" onClick={() => setPanel(panel === 'wide' ? 'normal' : 'wide')} aria-label={panel === 'wide' ? 'Collapse records' : 'Expand records'}>⇤</button><button type="button" className="ib" onClick={() => setPanel('hidden')} aria-label="Hide records">›</button></div><div className="lst">
        {tab === 'households' && <><div className="un">Barangay total: {shown(workspace.summary.total_households)} households · {shown(workspace.summary.no_verified_geotag)} unlocated</div>{!households.length && <div className="empty">No households match this filter.</div>}{households.map(h => <button type="button" className={`it ${selected?.kind === 'household' && String(row.id) === String(h.id) ? 'on' : ''}`} key={h.id} onClick={() => select('household',h)}><i className="dt" style={{ '--c':statusColor(h.status_key) }} /><span>{h.label} <em>{h.purok}</em></span><em className="xt">{h.status_label}</em><em>{h.accuracy_m == null ? '—' : `±${h.accuracy_m} m`}</em></button>)}</>}
        {tab === 'sites' && <>{!sites.length && <div className="empty">No evacuation centers available.</div>}{sites.map(site => <button type="button" className={`it ${selected?.kind === 'site' && row.id === site.id ? 'on' : ''}`} key={site.id} onClick={() => select('site',site)}><span>{site.name}</span><div className="vm"><i style={{ width:`${site.capacity && site.occupancy != null ? Math.min(100,site.occupancy/site.capacity*100) : 0}%`, '--c':siteColor(site) }} /></div><em className="xt">{shown(site.occupancy)}/{shown(site.capacity)}</em><em>{shown(site.vacancy)} left</em></button>)}</>}
        {tab === 'routes' && <>{!routes.length && <div className="empty">No active dispatch routes.</div>}{routes.map(route => <button type="button" className={`it ${selected?.kind === 'route' && row.route_id === route.route_id ? 'on' : ''}`} key={route.route_id} onClick={() => select('route',route)}><i className="dt" style={{ '--c':statusColor(route.status) }} /><span>{route.team_name || route.route_name}</span><em className="xt">{labelize(route.status)}</em><em className="xt">{shown(route.duration_min,' min')}</em><em>{shown(route.distance_km,' km')}</em></button>)}</>}
      </div></aside>}
      {active && panel === 'hidden' && <button type="button" className="fl stab" style={{ display:'block' }} onClick={() => setPanel('normal')}>Panel</button>}
      <div className="fl zm"><button type="button" aria-label="Zoom in" onClick={() => map.current?.zoomIn()}>+</button><button type="button" aria-label="Zoom out" onClick={() => map.current?.zoomOut()}>−</button></div>
      <button type="button" className={`bm ${basemap === 'street' ? 's2' : ''}`} disabled={!workspace.barangay.basemaps?.satellite?.tile_url} onClick={() => setBasemap(basemap === 'street' ? 'satellite' : 'street')} aria-label={basemap === 'street' ? 'Satellite basemap' : 'Street basemap'}><i /><span>{basemap === 'street' ? 'Satellite' : 'Map'}</span></button>
      {selected && <div className="fl det on" aria-label="Map details"><div className="dh"><b>{row.label || row.name || row.route_name || row.team_name}</b>{selected.kind === 'household' && <span className="bg" style={{ '--c':statusColor(row.status_key) }}>{row.status_label}</span>}{['route','team'].includes(selected.kind) && <span className="bg" style={{ '--c':statusColor(row.status) }}>{labelize(row.status)}</span>}<button type="button" className="x" onClick={clear} aria-label="Close map details">✕</button></div>
        {selected.kind === 'household' && <><Fact label="Purok" value={row.purok} /><Fact label="Accuracy" value={row.accuracy_m == null ? null : `±${row.accuracy_m} m`} />{selectedRouteState?.loading ? <div role="status">Finding route…</div> : selectedRouteState?.error ? <div role="alert">{selectedRouteState.error}</div> : <><Fact label="Distance" value={selectedPreview?.distance_km == null ? null : `${selectedPreview.distance_km} km`} /><Fact label="Duration" value={selectedPreview?.duration_min == null ? null : `${selectedPreview.duration_min} min`} /></>}<button type="button" className="bt" disabled={!isHouseholdRouteAllowed(row) || !canOpenWebPage(user,'/rescue-management/new')} onClick={() => navigate('/rescue-management/new',{ state:{ selectedHousehold:row } })}>Route dispatch</button></>}
        {selected.kind === 'site' && <><Fact label="Type" value={row.center_type} /><Fact label="Vacant" value={`${shown(row.vacancy)} / ${shown(row.capacity)}`} /><Fact label="Status" value={row.status} />{selectedSiteRoute?.loading ? <div role="status">Finding route from barangay hall…</div> : selectedSiteRoute?.error ? <div role="alert">{selectedSiteRoute.error}</div> : selectedSiteRoute?.route && <><Fact label="From" value={selectedSiteRoute.route.origin_name} /><Fact label="Distance" value={`${Number(selectedSiteRoute.route.distance_km).toFixed(2)} km`} /><Fact label="Duration" value={`${selectedSiteRoute.route.duration_min} min`} /></>}</>}
        {selected.kind === 'route' && <><Fact label="Team" value={row.team_name} /><Fact label="Area" value={row.assigned_area} /><Fact label="Distance" value={row.distance_km == null ? null : `${row.distance_km} km`} /><Fact label="Duration" value={row.duration_min == null ? null : `${row.duration_min} min`} /><button type="button" className="bt l" onClick={clear}>Show all</button></>}
        {selected.kind === 'office' && <Fact label="Role" value="Dispatch origin" />}{selected.kind === 'team' && <><Fact label="Responder" value={row.responder_name} /><Fact label="Area" value={row.assigned_area} /><Fact label="Last seen" value={row.logged_at} /></>}
      </div>}
    </div>}
    {workspace && <HazardPlanning workspace={workspace} purokId={purokId} hazardType={hazardType} user={user} refresh={refresh} />}
  </section>
  return fullscreen ? createPortal(content, document.body) : content
}
function Fact({ label, value }) { return <div className="dr"><span>{label}</span><b>{value ?? '—'}</b></div> }
