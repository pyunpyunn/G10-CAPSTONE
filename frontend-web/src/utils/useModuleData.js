import { useCallback, useEffect, useRef, useState } from 'react'
import { useRealtimeVersion } from './useRealtimeVersion'
import { createRefreshScheduler } from './refreshScheduler'

export function useModuleData(loader, topics = []) {
  const [version, setVersion] = useState(0)
  const realtimeVersion = useRealtimeVersion(topics)
  const [result, setResult] = useState(null)
  const realtimeRefresh = useRef(null)
  useEffect(() => {
    let cancelled = false
    let running = false
    let pending = false
    const scheduler = createRefreshScheduler(async () => {
      if (running) { pending = true; return }
      running = true
      pending = false
      try {
        const data = await loader()
        if (!cancelled) setResult({ loader, version, data, error: '' })
      } catch (failure) {
        if (!cancelled) setResult((previous) => ({ loader, version,
          data: previous?.loader === loader ? previous.data : null,
          error: failure.response?.data?.message || 'Records could not be loaded. Please try again.' }))
      } finally {
        running = false
        if (!cancelled && pending) scheduler.request()
      }
    })
    let initialLoad = window.setTimeout(() => scheduler.request(), 0)
    const requestRefresh = () => {
      window.clearTimeout(initialLoad)
      initialLoad = undefined
      pending = true
      scheduler.request()
    }
    realtimeRefresh.current = requestRefresh
    return () => {
      cancelled = true
      window.clearTimeout(initialLoad)
      scheduler.cancel()
      if (realtimeRefresh.current === requestRefresh) realtimeRefresh.current = null
    }
  }, [loader, version])
  useEffect(() => {
    if (realtimeVersion > 0) realtimeRefresh.current?.()
  }, [realtimeVersion])
  const current = result?.loader === loader && result?.version === version
  const refresh = useCallback(() => setVersion((value) => value + 1), [])
  return { data: current ? result.data : null, error: current ? result.error : '', loading: !current, refresh }
}
