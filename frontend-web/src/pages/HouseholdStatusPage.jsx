import { useCallback, useState } from 'react'
import { getSitioPriorities } from '../api/dispatchApi'
import { SitioPriorityChart, SitioPriorityList } from '../components/households/SitioPriorityRanking'
import LoadingState from '../components/ui/LoadingState'
import { useModuleData } from '../utils/useModuleData'

export default function HouseholdStatusPage() {
  const [selectedSitioId, setSelectedSitioId] = useState(null)
  const loader = useCallback(async () => {
    return getSitioPriorities()
  }, [])
  const { data: ranking = [], error, loading: isLoading, refresh: load } = useModuleData(loader, ['households', 'disasters'])
  return (
    <main className="ops-page household-page">
      {isLoading && <LoadingState />}
      {error && <div className="form-error" role="alert">{error}</div>}
      {!isLoading && <div className="workspace-grid">
        <section className="household-workspace" aria-label="Sitio rescue priority ranking">
          {!error && ranking.length === 0 && <div className="standby-strip hh-standby-strip"><strong>No active disaster event</strong><span>Household reporting starts after HQ/Admin broadcasts an active event.</span></div>}
          <SitioPriorityChart rows={ranking} loading={isLoading} onRefresh={load} />
        </section>
        <aside className="side-panel" aria-label="Sitio ranking">
          <SitioPriorityList rows={ranking} selectedSitioId={selectedSitioId} onSelect={setSelectedSitioId} />
        </aside>
      </div>}
    </main>
  )
}
