import { useMemo } from 'react'
import { Pencil, TriangleAlert } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { getDashboard } from '../api/dashboardApi'
import DashboardMainContent from '../components/dashboard/DashboardMainContent'
import DashboardOverview from '../components/dashboard/DashboardOverview'
import LoadingState from '../components/ui/LoadingState'
import { getStats } from '../utils/dashboardHelpers'
import { useModuleData } from '../utils/useModuleData'

export default function DashboardPage() {
  const navigate = useNavigate()
  const { data: dashboard, error, loading: isLoading } = useModuleData(getDashboard)
  const stats = useMemo(() => getStats(dashboard), [dashboard])
  const hasActiveEvent = Boolean(dashboard?.active_event)
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
