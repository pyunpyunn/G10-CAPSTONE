import { useEffect, useMemo, useState } from 'react'
import { ChevronDown, Search } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { getHouseholdGeotags, getMappingOverview, getRouteToSite } from '../api/mappingApi'
import MappingMap from '../components/mapping/MappingMap'
import MappingSidebar from '../components/mapping/MappingSidebar'
import LoadingState from '../components/ui/LoadingState'
import RefreshOverlay from '../components/ui/RefreshOverlay'
import {
  apiErrorMessage,
  defaultWorkspace,
  isActiveRouteStatus,
  isHouseholdRouteAllowed,
  normalizeWorkspaceData,
} from '../utils/mappingHelpers'

export default function MappingPage() {
  const navigate = useNavigate()
  const [workspace, setWorkspace] = useState(defaultWorkspace)
  const [isLoading, setIsLoading] = useState(true)
  const [hasLoaded, setHasLoaded] = useState(false)
  const [error, setError] = useState('')
  const [purok, setPurok] = useState('all')
  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')
  const [layers, setLayers] = useState({
    households: true,
    evacuationSites: true,
    rescueTeams: true,
    routes: true,
  })
  const [selectedRoute, setSelectedRoute] = useState(null)
  const [selectedHousehold, setSelectedHousehold] = useState(null)
  const [routeLoadingId, setRouteLoadingId] = useState('')
  const [routeError, setRouteError] = useState('')
  const [isMapFullscreen, setIsMapFullscreen] = useState(false)

  const hasActiveEvent = Boolean(workspace.active_event)
  const barangay = workspace?.barangay || defaultWorkspace.barangay
  const filters = workspace?.filters || defaultWorkspace.filters
  const mapCenter = useMemo(() => [
    Number(barangay?.center?.latitude ?? defaultWorkspace.barangay.center.latitude),
    Number(barangay?.center?.longitude ?? defaultWorkspace.barangay.center.longitude),
  ], [barangay?.center?.latitude, barangay?.center?.longitude])
  const mapBounds = useMemo(() => Array.isArray(barangay?.bounds) ? barangay.bounds : defaultWorkspace.barangay.bounds, [barangay?.bounds])
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
  const visibleHouseholds = useMemo(() => households.filter((household) => (
    String(household.label || '').toLowerCase().includes(search.trim().toLowerCase())
  )), [households, search])

  const closestRescueTeam = useMemo(() => rescueTeams[0] || null, [rescueTeams])

  useEffect(() => {
    let ignore = false

    async function loadPage() {
      setIsLoading(true)
      setError('')
      setRouteError('')

      try {
        const [data, householdGeotags] = await Promise.all([
          getMappingOverview({ purok, status }),
          getHouseholdGeotags({ purok, status }),
        ])

        if (!ignore) {
          setWorkspace(normalizeWorkspaceData({ ...(data || {}), households: householdGeotags }))
          setHasLoaded(true)
        }
      } catch (loadError) {
        if (!ignore) {
          setWorkspace(defaultWorkspace)
          setError(apiErrorMessage(loadError))
          setHasLoaded(false)
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadPage()

    return () => {
      ignore = true
    }
  }, [purok, status])

  useEffect(() => {
    function closeFullscreen(event) {
      if (event.key === 'Escape') {
        setIsMapFullscreen(false)
      }
    }

    window.addEventListener('keydown', closeFullscreen)

    return () => window.removeEventListener('keydown', closeFullscreen)
  }, [])

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

    if (!isHouseholdRouteAllowed(household)) {
      setSelectedHousehold(household)
      setSelectedRoute(null)
      setRouteError('Only red-status households can be routed for dispatch or rescue guidance.')
      return
    }

    if (!household.latitude || !household.longitude) {
      setSelectedHousehold(household)
      setRouteError('Only geotagged households can receive a rescue route.')
      setSelectedRoute(null)
      return
    }

    if (!closestRescueTeam) {
      setSelectedHousehold(household)
      setRouteError('No rescue team GPS point is available for route generation.')
      setSelectedRoute(null)
      return
    }

    setSelectedHousehold(household)
    setRouteLoadingId(household.id)
    setRouteError('')
    setSelectedRoute(null)

    try {
      const route = await getRouteToSite(closestRescueTeam, household)

      if (!route) {
        setRouteError('A route cannot be generated for this household right now.')
        return
      }

      setSelectedRoute({
        route_id: `household-${household.id}`,
        route_name: `Route to ${household.label}`,
        team_name: closestRescueTeam.team_name || 'Rescue team',
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
          <section className="mapmate-filter-bar" aria-label="Map filters">
            <div className="mapmate-filter-heading"><span>View</span><strong>Map filters</strong></div>
            <label className="mapmate-search"><Search size={14} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search household" aria-label="Search household" /></label>
            <SelectField label="Area" value={purok} onChange={setPurok} options={[{ key: 'all', label: 'All puroks' }, ...((filters?.puroks || []).map((item) => ({ key: item, label: item })))]} />
            <SelectField label="Status" value={status} onChange={setStatus} options={((filters?.statuses || []).map((item) => ({ key: item.key, label: item.label })))} />
            <span className="mapmate-result-count">{visibleHouseholds.length} results</span>
          </section>

          <div className="mapmate-grid">
            <main className="mapmate-main">
              <RefreshOverlay active={isRefreshing}>
                <MappingMap
                  workspace={workspace}
                  hasActiveEvent={hasActiveEvent}
                  layers={layers}
                  households={visibleHouseholds}
                  evacuationSites={evacuationSites}
                  rescueTeams={rescueTeams}
                  visibleRoutes={visibleRoutes}
                  selectedRoute={selectedRoute}
                  selectedHousehold={selectedHousehold}
                  mapCenter={mapCenter}
                  mapBounds={mapBounds}
                  onChangeLayer={changeLayer}
                  onSelectHousehold={handleHouseholdSelection}
                  onRouteToDispatch={handleDispatchRoute}
                  isFullscreen={isMapFullscreen}
                  onToggleFullscreen={() => setIsMapFullscreen((current) => !current)}
                />
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

function SelectField({ label, value, onChange, options }) {
  return (
    <label className="mapmate-select-field">
      <span>{label}</span>
      <div><select value={value} onChange={(event) => onChange(event.target.value)}>{options.map((option) => <option key={option.key} value={option.key}>{option.label}</option>)}</select><ChevronDown size={13} /></div>
    </label>
  )
}
