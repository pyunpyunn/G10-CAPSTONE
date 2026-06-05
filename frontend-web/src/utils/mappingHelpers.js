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
    name: 'Barangay Guadalupe',
    center: {
      latitude: 10.3157,
      longitude: 123.8854,
    },
    bounds: [
      [10.3028, 123.872],
      [10.3293, 123.8996],
    ],
    zoom: 16,
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

export function apiErrorMessage(error) {
  if (!error?.response) {
    return 'Cannot connect to the backend right now. The map will stay in plain barangay view until the server is available.'
  }

  return error.response.data?.message || 'Mapping data cannot be loaded right now.'
}

export function defaultRules() {
  return [
    { color: 'green', title: 'Green marker', text: 'Safe, evacuated, or checked household with GPS coordinates.' },
    { color: 'red', title: 'Red marker', text: 'Unsafe, missing, injured, or needs-help household with GPS coordinates.' },
    { color: 'gray', title: 'Grey marker', text: 'Unchecked household with GPS coordinates.' },
    { color: 'hidden', title: 'No coordinates, no marker', text: 'Households without latitude and longitude stay hidden from the map.' },
  ]
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
