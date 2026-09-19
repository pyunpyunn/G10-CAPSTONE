export const markerGroups = {
  green: {
    label: 'Safe or evacuated',
    color: '#3a7d57',
  },
  red: {
    label: 'Unsafe or needs help',
    color: '#962020',
  },
  gray: {
    label: 'Unchecked',
    color: '#6a7f94',
  },
}

export const defaultWorkspace = {
  active_event: null,
  barangay: {
    name: 'Barangay Mambaling',
    center: {
      latitude: 10.2922,
      longitude: 123.8763,
    },
    bounds: [
      [10.2820, 123.8700],
      [10.2972, 123.8842],
    ],
    zoom: 17,
    tile_provider: 'OpenStreetMap Standard',
    tile_url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
  },
  summary: {
    gps_tagged_households: 0,
    no_verified_geotag: 0,
    average_accuracy_m: null,
    evacuation_sites: 0,
  },
  filters: {
    puroks: [],
    statuses: [
      { key: 'all', label: 'All GPS-verified statuses' },
      { key: 'green', label: 'Green - safe / evacuated / checked' },
      { key: 'red', label: 'Red - unsafe / missing / injured' },
      { key: 'gray', label: 'Grey - unchecked' },
    ],
  },
  households: [],
  evacuation_sites: [],
  rescue_teams: [],
  dispatch_routes: [],
  map_rules: [],
}

export function normalizeWorkspaceData(data = {}) {
  return {
    ...defaultWorkspace,
    ...data,
    barangay: {
      ...defaultWorkspace.barangay,
      ...(data?.barangay || {}),
      center: {
        ...defaultWorkspace.barangay.center,
        ...(data?.barangay?.center || {}),
      },
    },
    summary: {
      ...defaultWorkspace.summary,
      ...(data?.summary || {}),
    },
    filters: {
      ...defaultWorkspace.filters,
      ...(data?.filters || {}),
      puroks: Array.isArray(data?.filters?.puroks) ? data.filters.puroks : defaultWorkspace.filters.puroks,
      statuses: Array.isArray(data?.filters?.statuses) ? data.filters.statuses : defaultWorkspace.filters.statuses,
    },
    households: Array.isArray(data?.households) ? data.households : [],
    evacuation_sites: Array.isArray(data?.evacuation_sites) ? data.evacuation_sites : [],
    rescue_teams: Array.isArray(data?.rescue_teams) ? data.rescue_teams : [],
    dispatch_routes: Array.isArray(data?.dispatch_routes) ? data.dispatch_routes : [],
  }
}

export function apiErrorMessage(error) {
  if (!error?.response) {
    return 'Cannot connect to the backend right now. The map will stay in plain barangay view until the server is available.'
  }

  return error.response.data?.message || 'Mapping data cannot be loaded right now.'
}

export function isHouseholdRouteAllowed(household) {
  if (!household) {
    return false
  }

  const statusKey = String(household.status_key || household.marker_group || household.status?.key || '').toLowerCase()
  const statusLabel = String(household.status_label || household.status?.label || '').toLowerCase()

  if (['red', 'unsafe', 'missing', 'injured', 'needs_help', 'need_help', 'not_safe', 'not-safe'].includes(statusKey)) {
    return true
  }

  return ['unsafe', 'missing', 'injured', 'needs help', 'need help', 'not safe'].some((term) => statusLabel.includes(term))
}

export function defaultRules() {
  return [
    { color: 'green', title: 'Green marker', text: 'Safe, evacuated, or checked household with GPS coordinates.' },
    { color: 'red', title: 'Red marker', text: 'Unsafe, missing, injured, or needs-help household with GPS coordinates.' },
    { color: 'gray', title: 'Grey marker', text: 'Unchecked household with GPS coordinates.' },
    { color: 'hidden', title: 'No coordinates, no marker', text: 'Households without latitude and longitude stay hidden from the map.' },
  ]
}

export function isActiveRouteStatus(route = {}) {
  const rawStatus = route?.status ?? route?.route_status ?? route?.assignment_status ?? ''
  const status = String(rawStatus).trim().toLowerCase()

  if (!status) {
    return true
  }

  return !['completed', 'cancelled', 'returned', 'ended', 'closed', 'failed'].some((value) => status === value || status.includes(value))
}

export function vacancyPercent(site) {
  if (!site.capacity || site.vacancy === null || site.vacancy === undefined) {
    return '0%'
  }

  return `${Math.max(0, Math.min(100, (site.vacancy / site.capacity) * 100))}%`
}

export function percent(value) {
  if (value === null || value === undefined || value === '') {
    return '-'
  }

  return `${value}%`
}

export function labelize(value) {
  if (!value) {
    return '-'
  }

  return String(value).replaceAll('_', ' ')
}

export function escapeMarkerLabel(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')
}
