// Coalesce focus/mount bursts, renew successful registrations sparingly, and honor 429 backoff.
export function createPushRegistrationGate({ register, storage, now = Date.now, lifetime = 6 * 60 * 60 * 1000 }) {
  const pending = new Map()
  const receipts = new Map()
  const failures = new Map()
  const storageKey = key => `resqperation:web-push:v2:${key}`
  return {
    run(key, { force = false } = {}) {
      if (!key) return Promise.resolve('granted')
      if (pending.has(key)) return pending.get(key)
      const failure = failures.get(key)
      if (failure && now() < failure.until) return Promise.reject(failure.error)
      let saved = receipts.get(key) || 0
      try { saved = Math.max(saved, Number(storage?.getItem(storageKey(key))) || 0) } catch { /* memory still works */ }
      if (!force && saved > now()) return Promise.resolve('granted')
      const promise = Promise.resolve().then(() => register(key)).then(() => {
        const expires = now() + lifetime
        receipts.set(key, expires)
        failures.delete(key)
        try { storage?.setItem(storageKey(key), String(expires)) } catch { /* private browsing */ }
        return 'granted'
      }).catch(error => {
        const limited = error.response?.status === 429
        const retry = Number(error.response?.headers?.['retry-after'])
        const seconds = limited && Number.isFinite(retry) && retry > 0 ? retry : 60
        if (limited) error.message = 'Notification setup is temporarily busy. Wait a minute, then try again.'
        failures.set(key, { until: now() + Math.max(60, seconds) * 1000, error })
        throw error
      }).finally(() => pending.delete(key))
      pending.set(key, promise)
      return promise
    },
    forget(key) {
      receipts.delete(key)
      failures.delete(key)
      try { storage?.removeItem(storageKey(key)) } catch { /* private browsing */ }
    },
  }
}
