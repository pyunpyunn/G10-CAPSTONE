import { useCallback, useEffect, useState } from 'react'
import { getHouseholds } from '../api/householdApi'
import { getSitioPriorities } from '../api/dispatchApi'
import { HouseholdSummaryMetrics } from '../components/households/HouseholdSummary'
import { SitioPriorityChart, SitioPriorityList } from '../components/households/SitioPriorityRanking'
import LoadingState from '../components/ui/LoadingState'
import { emptySummary } from '../utils/householdStatusHelpers'

export default function HouseholdStatusPage() {
  const [payload, setPayload] = useState(null)
  const [ranking, setRanking] = useState([])
  const [selectedSitioId, setSelectedSitioId] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    setIsLoading(true)
    setError('')
    try {
      const [households, priorities] = await Promise.allSettled([
        getHouseholds({ page: 1, per_page: 1 }),
        getSitioPriorities(),
      ])
      if (households.status === 'fulfilled') setPayload(households.value)
      if (priorities.status === 'fulfilled') setRanking(priorities.value || [])
      if (priorities.status === 'rejected') setError('Sitio priorities cannot be loaded right now. Please check the backend or database connection.')
      else if (households.status === 'rejected') setError('Household summary is temporarily unavailable.')
    } catch {
      setError('Sitio priorities cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    let active = true
    Promise.resolve().then(() => { if (active) load() })
    return () => { active = false }
  }, [load])

  return (
    <main className="ops-page household-page">
      {isLoading && !payload && <LoadingState />}
      {error && <div className="form-error" role="alert">{error}</div>}
      {!isLoading && <div className="workspace-grid">
        <section className="household-workspace" aria-label="Sitio rescue priority ranking">
          {payload && !payload.active_event && <div className="standby-strip hh-standby-strip"><strong>No active disaster event</strong><span>Household reporting starts after HQ/Admin broadcasts an active event.</span></div>}
          <SitioPriorityChart rows={ranking} selectedSitioId={selectedSitioId} loading={isLoading} onRefresh={load} />
        </section>
        <aside className="side-panel" aria-label="Sitio ranking">
          {payload && <HouseholdSummaryMetrics summary={payload.summary || emptySummary()} />}
          <SitioPriorityList rows={ranking} selectedSitioId={selectedSitioId} onSelect={(id) => setSelectedSitioId((current) => String(current) === String(id) ? null : id)} />
        </aside>
      </div>}
    </main>
  )
}
