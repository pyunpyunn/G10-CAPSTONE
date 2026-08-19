export function readQueryParam(searchParams, key, fallback = '') {
  const value = searchParams.get(key)
  return value === null || value === undefined ? fallback : value
}

export function readQueryNumber(searchParams, key, fallback = 1) {
  const value = Number(searchParams.get(key))

  return Number.isFinite(value) && value > 0 ? value : fallback
}

export function mergeQueryParams(searchParams, updates = {}) {
  const next = new URLSearchParams(searchParams)

  Object.entries(updates).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '' || value === 'all') {
      next.delete(key)
      return
    }

    next.set(key, String(value))
  })

  return next
}

export function setQueryParams(setSearchParams, searchParams, updates = {}, options = {}) {
  setSearchParams(mergeQueryParams(searchParams, updates), options)
}
