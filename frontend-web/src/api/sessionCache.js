import { getToken } from './token'

const cachePrefix = 'resqperation_session_cache:'

export function readSessionCache(key, maxAgeMs) {
  const storageKey = getStorageKey(key)
  if (!storageKey) return null

  try {
    const cached = JSON.parse(sessionStorage.getItem(storageKey) || 'null')
    if (!cached || Date.now() - cached.savedAt > maxAgeMs) return null
    return cached.value ?? null
  } catch {
    return null
  }
}

export function writeSessionCache(key, value) {
  const storageKey = getStorageKey(key)
  if (!storageKey) return

  try {
    sessionStorage.setItem(storageKey, JSON.stringify({ savedAt: Date.now(), value }))
  } catch {
    // Caching is optional; API requests remain the source of truth.
  }
}

export function removeSessionCache(key) {
  const storageKey = getStorageKey(key)
  if (!storageKey) return

  try {
    sessionStorage.removeItem(storageKey)
  } catch {
    // Storage may be disabled by browser settings.
  }
}

export function removeSessionCachePrefix(prefix) {
  const storagePrefix = getStorageKey(prefix)
  if (!storagePrefix) return

  try {
    for (let index = sessionStorage.length - 1; index >= 0; index -= 1) {
      const key = sessionStorage.key(index)
      if (key?.startsWith(storagePrefix)) sessionStorage.removeItem(key)
    }
  } catch {
    // Storage may be disabled by browser settings.
  }
}

export function clearSessionCache() {
  try {
    for (let index = sessionStorage.length - 1; index >= 0; index -= 1) {
      const key = sessionStorage.key(index)
      if (key?.startsWith(cachePrefix)) sessionStorage.removeItem(key)
    }
  } catch {
    // Storage may be disabled by browser settings.
  }
}

function getStorageKey(key) {
  const token = getToken()
  return token ? `${cachePrefix}${fingerprint(token)}:${key}` : null
}

function fingerprint(value) {
  let hash = 2166136261
  for (let index = 0; index < value.length; index += 1) {
    hash = Math.imul(hash ^ value.charCodeAt(index), 16777619)
  }
  return (hash >>> 0).toString(36)
}
