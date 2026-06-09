import { useEffect, useMemo, useState } from 'react'
import { RefreshCcw } from 'lucide-react'
import { getMappingOverview, getRouteToSite } from '../api/mappingApi'
import GeotagToolbar from '../components/mapping/GeotagToolbar'
import MappingEventStrip from '../components/mapping/MappingEventStrip'
import MappingMap from '../components/mapping/MappingMap'
import MappingSidebar from '../components/mapping/MappingSidebar'
import MappingSummary from '../components/mapping/MappingSummary'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import {
  apiErrorMessage,
  defaultWorkspace,
} from '../utils/mappingHelpers'

export default function MappingPage() {
  const [workspace, setWorkspace] = useState(defaultWorkspace)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [purok, setPurok] = useState('all')
  const [status, setStatus] = useState('all')
  const [layers, setLayers] = useState({
    households: true,
    evacuationSites: true,
    rescueTeams: true,
    routes: true,
  })
  const [selectedRoute, setSelectedRoute] = useState(null)
  const [routeLoadingId, setRouteLoadingId] = useState('')
  const [routeError, setRouteError] = useState('')

  const hasActiveEvent = Boolean(workspace.active_event)
  const mapCenter = useMemo(() => [
    workspace.barangay.center.latitude,
    workspace.barangay.center.longitude,
  ], [workspace.barangay.center.latitude, workspace.barangay.center.longitude])
  const mapBounds = useMemo(() => workspace.barangay.bounds, [workspace.barangay.bounds])
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
    hasActiveEvent ? workspace.dispatch_routes : []
  ), [hasActiveEvent, workspace.dispatch_routes])
  const visibleRoutes = selectedRoute ? [selectedRoute] : dispatchRoutes

  const closestStartPoint = useMemo(() => {
    const team = rescueTeams[0]
    const urgentHousehold = households.find((household) => household.marker_group === 'red')
    const fallback = {
      latitude: workspace.barangay.center.latitude,
      longitude: workspace.barangay.center.longitude,
    }

    return team || urgentHousehold || fallback
  }, [households, rescueTeams, workspace.barangay.center.latitude, workspace.barangay.center.longitude])

  useEffect(() => {
    let ignore = false

    async function loadPage() {
      setIsLoading(true)
      setError('')
      setRouteError('')

      try {
        const data = await getMappingOverview({ purok, status })

        if (!ignore) {
          setWorkspace({ ...defaultWorkspace, ...data })
        }
      } catch (loadError) {
        if (!ignore) {
          setWorkspace(defaultWorkspace)
          setError(apiErrorMessage(loadError))
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

  async function reloadMap() {
    setIsLoading(true)
    setError('')
    setRouteError('')
    setSelectedRoute(null)

    try {
      const data = await getMappingOverview({ purok, status })
      setWorkspace({ ...defaultWorkspace, ...data })
    } catch (loadError) {
      setWorkspace(defaultWorkspace)
      setError(apiErrorMessage(loadError))
    } finally {
      setIsLoading(false)
    }
  }

  function changeLayer(layerName) {
    setLayers((current) => ({
      ...current,
      [layerName]: !current[layerName],
    }))
  }

  async function showRouteToSite(site) {
    if (!hasActiveEvent) {
      return
    }

    setRouteLoadingId(site.id)
    setRouteError('')
    setSelectedRoute(null)

    try {
      const route = await getRouteToSite(closestStartPoint, site)

      if (!route) {
        setRouteError('A route cannot be generated for this site right now.')
        return
      }

      setSelectedRoute({
        route_id: `site-${site.id}`,
        route_name: `Route to ${site.name}`,
        team_name: 'Nearest available point',
        assigned_area: site.name,
        status: 'on_demand',
        coordinates: route.coordinates,
        distance_km: route.distance_km,
        duration_min: route.duration_min,
      })
    } catch {
      setRouteError('A route cannot be generated right now. Please try again later.')
    } finally {
      setRouteLoadingId('')
    }
  }

  function showStoredRoute(route) {
    setRouteError('')
    setSelectedRoute(route)
  }

  return (
    <section className="page mapping-page active">
      <PageHeader
        title="Mapping"
        actions={
          <>
            <button className="btn btn-secondary btn-sm" type="button" onClick={reloadMap}>
              <RefreshCcw size={14} />
              Refresh
            </button>
            <select className="mapping-select" value={purok} onChange={(event) => setPurok(event.target.value)}>
              <option value="all">All puroks</option>
              {workspace.filters.puroks.map((item) => (
                <option value={item} key={item}>{item}</option>
              ))}
            </select>
            <select className="mapping-select" value={status} onChange={(event) => setStatus(event.target.value)}>
              {workspace.filters.statuses.map((item) => (
                <option value={item.key} key={item.key}>{item.label}</option>
              ))}
            </select>
          </>
        }
      />

      {isLoading && <LoadingState message="Loading mapping workspace..." />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && (
        <>
          <MappingEventStrip activeEvent={workspace.active_event} />
          <MappingSummary summary={workspace.summary} hasActiveEvent={hasActiveEvent} />
          <GeotagToolbar />

          <div className="mapping-layout">
            <main className="mapping-main">
              <MappingMap
                workspace={workspace}
                hasActiveEvent={hasActiveEvent}
                layers={layers}
                households={households}
                evacuationSites={evacuationSites}
                rescueTeams={rescueTeams}
                visibleRoutes={visibleRoutes}
                selectedRoute={selectedRoute}
                mapCenter={mapCenter}
                mapBounds={mapBounds}
                onChangeLayer={changeLayer}
              />
            </main>

            <MappingSidebar
              hasActiveEvent={hasActiveEvent}
              households={households}
              evacuationSites={evacuationSites}
              dispatchRoutes={dispatchRoutes}
              selectedRoute={selectedRoute}
              routeError={routeError}
              routeLoadingId={routeLoadingId}
              mapRules={workspace.map_rules}
              onRouteToSite={showRouteToSite}
              onStoredRoute={showStoredRoute}
              onClearSelectedRoute={() => setSelectedRoute(null)}
            />
          </div>
        </>
      )}
    </section>
  )
}
