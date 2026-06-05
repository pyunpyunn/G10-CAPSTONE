import api from './client'

export async function getMappingOverview(params = {}) {
  const response = await api.get('/map/overview', { params })
  return response.data.data
}

export async function getHouseholdGeotags(params = {}) {
  const response = await api.get('/map/household-geotags', { params })
  return response.data.data
}

export async function getEvacuationSites(params = {}) {
  const response = await api.get('/map/evacuation-sites', { params })
  return response.data.data
}

export async function getDispatchRoutes(params = {}) {
  const response = await api.get('/map/dispatch-routes', { params })
  return response.data.data
}

export async function getRouteToSite(startPoint, endPoint) {
  if (!startPoint || !endPoint) {
    return null
  }

  const start = `${startPoint.longitude},${startPoint.latitude}`
  const end = `${endPoint.longitude},${endPoint.latitude}`
  const url = `https://router.project-osrm.org/route/v1/driving/${start};${end}?overview=full&geometries=geojson`
  const response = await fetch(url)
  const data = await response.json()
  const route = data.routes?.[0]

  if (!route) {
    return null
  }

  return {
    coordinates: route.geometry.coordinates.map((point) => [point[1], point[0]]),
    distance_km: Number((route.distance / 1000).toFixed(2)),
    duration_min: Math.round(route.duration / 60),
  }
}
