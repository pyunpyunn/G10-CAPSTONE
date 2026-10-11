import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getMappingOverview, getRouteToSite } from '../api/mappingApi'
import MappingMap from '../components/mapping/MappingMap'
import MappingSidebar from '../components/mapping/MappingSidebar'
import LoadingState from '../components/ui/LoadingState'
import RefreshOverlay from '../components/ui/RefreshOverlay'
import { useModuleData } from '../utils/useModuleData'
import {
  defaultWorkspace,
  isActiveRouteStatus,
  isHouseholdRouteAllowed,
  normalizeWorkspaceData,
} from '../utils/mappingHelpers'

const loadMappingWorkspace = async () => normalizeWorkspaceData(await getMappingOverview() || {})

export default function MappingPage() {
  const navigate = useNavigate()
  const { data, error, loading: isLoading } = useModuleData(loadMappingWorkspace, ['mapping', 'households', 'dispatch', 'disasters'])
  const workspace = data || defaultWorkspace
  const hasLoaded = Boolean(data)
  const [layers, setLayers] = useState({
    households: true,
    evacuationSites: true,
    rescueTeams: true,
    rescueOffices: true,
    routes: true,
  })
  const [selectedRoute, setSelectedRoute] = useState(null)
  const [selectedHousehold, setSelectedHousehold] = useState(null)
  const [routeLoadingId, setRouteLoadingId] = useState('')
  const [routeError, setRouteError] = useState('')
  const [isMapFullscreen, setIsMapFullscreen] = useState(false)

  const hasActiveEvent = Boolean(workspace.active_event)
  const barangay = workspace?.barangay || defaultWorkspace.barangay
  const mapCenter = useMemo(() => [
    barangay?.center?.latitude,
    barangay?.center?.longitude,
  ], [barangay?.center?.latitude, barangay?.center?.longitude])
  const hasMapCenter = mapCenter.every((coordinate) => coordinate !== null && coordinate !== undefined
    && coordinate !== '' && Number.isFinite(Number(coordinate)))
  const mapBounds = Array.isArray(barangay?.bounds) ? barangay.bounds : defaultWorkspace.barangay.bounds
  const households = useMemo(() => (
    hasActiveEvent ? workspace.households : []
  ), [hasActiveEvent, workspace.households])
  const evacuationSites = useMemo(() => (
    hasActiveEvent ? workspace.evacuation_sites : []
  ), [hasActiveEvent, workspace.evacuation_sites])
  const rescueTeams = useMemo(() => (
    hasActiveEvent ? workspace.rescue_teams : []
  ), [hasActiveEvent, workspace.rescue_teams])
  const dispatchRoutes = useMemo(() => (
    hasActiveEvent ? (Array.isArray(workspace.dispatch_routes) ? workspace.dispatch_routes.filter(isActiveRouteStatus) : []) : []
  ), [hasActiveEvent, workspace.dispatch_routes])
  const visibleRoutes = selectedRoute ? [selectedRoute] : dispatchRoutes
  const visibleHouseholds = households

  const rescueOffice = useMemo(() => (
    workspace.rescue_offices.find((office) => office.latitude !== null && office.latitude !== undefined
      && office.longitude !== null && office.longitude !== undefined
      && Number.isFinite(Number(office.latitude)) && Number.isFinite(Number(office.longitude))) || null
  ), [workspace.rescue_offices])

  useEffect(() => {
    function closeFullscreen(event) {
      if (event.key === 'Escape') {
        setIsMapFullscreen(false)
      }
    }

    window.addEventListener('keydown', closeFullscreen)

    return () => window.removeEventListener('keydown', closeFullscreen)
  }, [])

  useEffect(() => {
    if (!isMapFullscreen) return undefined
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => { document.body.style.overflow = previousOverflow }
  }, [isMapFullscreen])

  function changeLayer(layerName) {
    setLayers((current) => ({
      ...current,
      [layerName]: !current[layerName],
    }))
  }

  async function showRouteToHousehold(household) {
    if (!hasActiveEvent || !household) {
      return
    }

    if (household.latitude === null || household.latitude === undefined || household.latitude === ''
      || household.longitude === null || household.longitude === undefined || household.longitude === '') {
      setSelectedHousehold(household)
      setRouteError('Only geotagged households can receive a rescue route.')
      setSelectedRoute(null)
      return
    }

    if (!rescueOffice) {
      setSelectedHousehold(household)
      setRouteError('Rescue office coordinates are not configured for route generation.')
      setSelectedRoute(null)
      return
    }

    setSelectedHousehold(household)
    setRouteLoadingId(household.id)
    setRouteError('')
    setSelectedRoute(null)

    try {
      const route = await getRouteToSite(rescueOffice, household)

      if (!route) {
        setRouteError('A route cannot be generated for this household right now.')
        return
      }

      setSelectedRoute({
        route_id: `household-${household.id}`,
        route_name: `Route to ${household.label}`,
        origin_name: rescueOffice.name || 'Barangay Mambaling Hall',
        assigned_area: household.purok || 'Selected household',
        status: 'on_demand',
        coordinates: route.coordinates,
        distance_km: route.distance_km,
        duration_min: route.duration_min,
      })
    } catch {
      setRouteError('A route to this household cannot be generated right now. Please try again later.')
    } finally {
      setRouteLoadingId('')
    }
  }

  function handleDispatchRoute(household) {
    if (!household) {
      return
    }

    if (!isHouseholdRouteAllowed(household)) {
      setSelectedHousehold(household)
      setRouteError('Only red-status households can be routed to dispatch. This household is not marked unsafe.')
      setSelectedRoute(null)
      return
    }

    setSelectedHousehold(household)
    setRouteError('')
    navigate('/dispatch', { state: { selectedHousehold: household } })
  }

  function handleHouseholdSelection(household) {
    setSelectedHousehold(household)

    if (household && hasActiveEvent) {
      showRouteToHousehold(household)
    }
  }

  function showStoredRoute(route) {
    setRouteError('')
    setSelectedRoute(route)
  }

  const isInitialLoading = isLoading && !hasLoaded
  const isRefreshing = isLoading && hasLoaded

  return (
    <section className="page mapping-page active mapmate-page">
      {isInitialLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {hasLoaded && (
        <>
          <div className="mapmate-grid">
            <main className="mapmate-main">
              <RefreshOverlay active={isRefreshing}>
                {hasMapCenter ? <MappingMap
                  workspace={workspace}
                  hasActiveEvent={hasActiveEvent}
                  layers={layers}
                  households={visibleHouseholds}
                  evacuationSites={evacuationSites}
                  rescueTeams={rescueTeams}
                  visibleRoutes={visibleRoutes}
                  selectedRoute={selectedRoute}
                  selectedHousehold={selectedHousehold}
                  routeLoadingId={routeLoadingId}
                  routeError={routeError}
                  mapCenter={mapCenter}
                  mapBounds={mapBounds}
                  onChangeLayer={changeLayer}
                  onSelectHousehold={handleHouseholdSelection}
                  onRouteToDispatch={handleDispatchRoute}
                  isFullscreen={isMapFullscreen}
                  onToggleFullscreen={() => setIsMapFullscreen((current) => !current)}
                /> : <div className="form-error" role="status">No map coordinates are configured or available in the shared database. Add a geotagged location or set the map center in backend configuration.</div>}
              </RefreshOverlay>
            </main>

            <MappingSidebar
              hasActiveEvent={hasActiveEvent}
              summary={workspace.summary}
              households={visibleHouseholds}
              evacuationSites={evacuationSites}
              dispatchRoutes={dispatchRoutes}
              selectedRoute={selectedRoute}
              routeError={routeError}
              routeLoadingId={routeLoadingId}
              selectedHousehold={selectedHousehold}
              onRouteToHousehold={showRouteToHousehold}
              onStoredRoute={showStoredRoute}
            />
          </div>
        </>
      )}
    </section>
  )
}
