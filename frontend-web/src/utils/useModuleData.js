import { useCallback, useEffect, useState } from 'react'

export function useModuleData(loader) {
  const [version, setVersion] = useState(0)
  const [result, setResult] = useState(null)
  useEffect(() => {
    let cancelled = false
    loader().then((data) => {
      if (!cancelled) setResult({ loader, version, data, error: '' })
    }).catch((failure) => {
      if (!cancelled) setResult({ loader, version, data: null, error: failure.response?.data?.message || 'Records could not be loaded. Please try again.' })
    })
    return () => { cancelled = true }
  }, [loader, version])
  const current = result?.loader === loader && result?.version === version
  const refresh = useCallback(() => setVersion((value) => value + 1), [])
  return { data: current ? result.data : null, error: current ? result.error : '', loading: !current,
    refresh }
}
