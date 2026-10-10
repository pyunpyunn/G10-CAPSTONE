import { useCallback, useState } from 'react'
import { getHouseholds } from '../api/householdApi'
import { getSitioPriorities } from '../api/dispatchApi'
import { HouseholdSummaryMetrics } from '../components/households/HouseholdSummary'
import { SitioPriorityChart, SitioPriorityList } from '../components/households/SitioPriorityRanking'
import LoadingState from '../components/ui/LoadingState'
import { emptySummary } from '../utils/householdStatusHelpers'
import { useModuleData } from '../utils/useModuleData'

export default function HouseholdStatusPage() {
  const [selectedSitioId, setSelectedSitioId] = useState(null)
  const loader = useCallback(async () => {
    const [households, priorities] = await Promise.allSettled([
      getHouseholds({ page: 1, per_page: 1 }), getSitioPriorities(),
    ])
    return {
      payload: households.status === 'fulfilled' ? households.value : null,
      ranking: priorities.status === 'fulfilled' ? priorities.value || [] : [],
      error: priorities.status === 'rejected' ? 'Sitio priorities cannot be loaded right now. Please check the backend or database connection.'
        : households.status === 'rejected' ? 'Household summary is temporarily unavailable.' : '',
    }
  }, [])
  const { data, error: loadError, loading: isLoading, refresh: load } = useModuleData(loader, ['households', 'disasters'])
  const payload = data?.payload || null
  const ranking = data?.ranking || []
  const error = loadError || data?.error || ''
  return (
    <main className="ops-page household-page">
      {isLoading && !payload && <LoadingState />}
      {error && <div className="form-error" role="alert">{error}</div>}
      {!isLoading && <div className="workspace-grid">
        <section className="household-workspace" aria-label="Sitio rescue priority ranking">
          {payload && !payload.active_event && <div className="standby-strip hh-standby-strip"><strong>No active disaster event</strong><span>Household reporting starts after HQ/Admin broadcasts an active event.</span></div>}
          <SitioPriorityChart rows={ranking} loading={isLoading} onRefresh={load} />
        </section>
        <aside className="side-panel" aria-label="Sitio ranking">
          {payload && <HouseholdSummaryMetrics summary={payload.summary || emptySummary()} />}
          <SitioPriorityList rows={ranking} selectedSitioId={selectedSitioId} onSelect={setSelectedSitioId} />
        </aside>
      </div>}
    </main>
  )
}
