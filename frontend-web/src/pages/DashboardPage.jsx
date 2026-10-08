import { useMemo, useState } from 'react'
import { Pencil, RefreshCcw, TriangleAlert } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { getDashboard } from '../api/dashboardApi'
import DashboardMainContent from '../components/dashboard/DashboardMainContent'
import DashboardOverview from '../components/dashboard/DashboardOverview'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import { getStats } from '../utils/dashboardHelpers'
import { useModuleData } from '../utils/useModuleData'

export default function DashboardPage() {
  const navigate = useNavigate()
  const { data: dashboard, error, loading: isLoading, refresh } = useModuleData(getDashboard)
  const [refreshVersion, setRefreshVersion] = useState(0)
  const stats = useMemo(() => getStats(dashboard), [dashboard])
  const hasActiveEvent = Boolean(dashboard?.active_event)
  function loadDashboard() {
    refresh()
    setRefreshVersion((version) => version + 1)
  }
  function openModule(path) {
    navigate(path)
  }

  const disasterAction = hasActiveEvent ? (
    <button className="btn btn-warning btn-sm" type="button" onClick={() => openModule(`/broadcast?event_id=${encodeURIComponent(dashboard.active_event.event_id)}`)}>
      <Pencil size={14} />
      UPDATE DISASTER
    </button>
  ) : (
    <button className="btn btn-danger btn-sm dashboard-declare-button" type="button" onClick={() => openModule('/broadcast')}>
      <TriangleAlert size={14} />
      Declare New Disaster
    </button>
  )

  return (
    <section className="page active">
      <PageHeader
        actions={
          <button className="btn btn-secondary btn-sm" type="button" onClick={loadDashboard}>
            <RefreshCcw size={14} />
            Refresh
          </button>
        }
      />

      {isLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && !error && dashboard && (
        <div className="dashboard-layout">
          <div className="dashboard-layout-mobile-actions">{disasterAction}</div>
          <DashboardMainContent
            dashboard={dashboard}
            stats={stats}
            hasActiveEvent={hasActiveEvent}
            onOpenModule={openModule}
            refreshVersion={refreshVersion}
          />
          <DashboardOverview
            dashboard={dashboard}
            hasActiveEvent={hasActiveEvent}
            onOpenModule={openModule}
            disasterAction={disasterAction}
          />
        </div>
      )}
    </section>
  )
}
