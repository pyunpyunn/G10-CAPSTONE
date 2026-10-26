import { store, saveViewState, removeViewState } from '../state/apiStore'

// Existing screen APIs now read/write the Redux session slice, not browser
// storage. Redux resets these records on logout, account change and invalidation.
export function readSessionCache(key, maxAgeMs) {
  const entry = store.getState().serverData.views[key]
  return entry && Date.now() - entry.savedAt <= maxAgeMs ? entry.value : null
}
export function writeSessionCache(key, value) { saveViewState(key, value) }
export function removeSessionCache(key) { removeViewState(key) }
export function removeSessionCachePrefix(prefix) { removeViewState(prefix) }
export function clearSessionCache() {
  removeViewState('')
  // Remove the obsolete persisted cache left by earlier releases.
  try {
    for (let index = sessionStorage.length - 1; index >= 0; index--) {
      const key = sessionStorage.key(index)
      if (key?.startsWith('resqperation_session_cache:')) sessionStorage.removeItem(key)
    }
  } catch { /* Browser storage can be unavailable. */ }
}

clearSessionCache()
