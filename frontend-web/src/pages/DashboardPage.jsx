import { useEffect, useMemo, useState } from 'react'
import { Pencil, RefreshCcw, TriangleAlert } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { getDashboard } from '../api/dashboardApi'
import DashboardMainContent from '../components/dashboard/DashboardMainContent'
import DashboardOverview from '../components/dashboard/DashboardOverview'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import { getStats } from '../utils/dashboardHelpers'

export default function DashboardPage() {
  const navigate = useNavigate()
  const [dashboard, setDashboard] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [refreshVersion, setRefreshVersion] = useState(0)

  useEffect(() => {
    let ignore = false

    async function loadInitialDashboard() {
      try {
        const data = await getDashboard()

        if (!ignore) {
          setDashboard(data)
        }
      } catch {
        if (!ignore) {
          setError('Dashboard data cannot be loaded right now. Please check the backend or database connection.')
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadInitialDashboard()

    return () => {
      ignore = true
    }
  }, [])

  const stats = useMemo(() => getStats(dashboard), [dashboard])
  const hasActiveEvent = Boolean(dashboard?.active_event)
  async function loadDashboard() {
    setIsLoading(true)
    setError('')

    try {
      const data = await getDashboard()
      setDashboard(data)
      setRefreshVersion((version) => version + 1)
    } catch {
      setError('Dashboard data cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
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
