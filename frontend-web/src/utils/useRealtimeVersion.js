import { useEffect, useState } from 'react'
import { createRefreshScheduler } from './refreshScheduler'

// Refetch mounted views without remounting them or losing filters and selections.
export function useRealtimeVersion(topics = []) {
  const [version, setVersion] = useState(0)
  const topicKey = topics.join(',')
  useEffect(() => {
    const scheduler = createRefreshScheduler(() => setVersion((value) => value + 1))
    const refresh = (event) => {
      if (document.hidden) return
      const changed = event?.detail?.topics
      if (changed && topicKey && !changed.some((topic) => topicKey.split(',').includes(topic))) return
      scheduler.request()
    }
    window.addEventListener('operations:changed', refresh)
    window.addEventListener('online', refresh)
    window.addEventListener('focus', refresh)
    const visibility = () => { if (!document.hidden) refresh() }
    document.addEventListener('visibilitychange', visibility)
    return () => {
      scheduler.cancel()
      document.removeEventListener('visibilitychange', visibility)
      window.removeEventListener('operations:changed', refresh)
      window.removeEventListener('online', refresh)
      window.removeEventListener('focus', refresh)
    }
  }, [topicKey])
  return version
}
