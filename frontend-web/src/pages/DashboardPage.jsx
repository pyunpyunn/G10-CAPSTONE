import { useEffect, useMemo, useState } from 'react'
import { Archive, RefreshCcw, TriangleAlert } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { closeActiveEvent, getDashboard } from '../api/dashboardApi'
import DashboardCloseEventModal from '../components/dashboard/DashboardCloseEventModal'
import DashboardMainContent from '../components/dashboard/DashboardMainContent'
import DashboardOverview from '../components/dashboard/DashboardOverview'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import {
  getCloseEventMessage,
  getStats,
} from '../utils/dashboardHelpers'

export default function DashboardPage() {
  const navigate = useNavigate()
  const [dashboard, setDashboard] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [isCloseModalOpen, setIsCloseModalOpen] = useState(false)
  const [isClosingEvent, setIsClosingEvent] = useState(false)
  const [closeError, setCloseError] = useState('')

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
    } catch {
      setError('Dashboard data cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  function openModule(path) {
    navigate(path)
  }

  function closeModal() {
    if (isClosingEvent) {
      return
    }

    setIsCloseModalOpen(false)
    setCloseError('')
  }

  async function handleCloseActiveEvent() {
    setIsClosingEvent(true)
    setCloseError('')

    try {
      const result = await closeActiveEvent()
      setDashboard(result.dashboard)
      setIsCloseModalOpen(false)
    } catch (closeEventError) {
      setCloseError(getCloseEventMessage(closeEventError))
    } finally {
      setIsClosingEvent(false)
    }
  }

  return (
    <section className="page active">
      <PageHeader
        title="Dashboard"
        actions={
          <>
            <button className="btn btn-secondary btn-sm" type="button" onClick={loadDashboard}>
              <RefreshCcw size={14} />
              Refresh
            </button>
            {hasActiveEvent ? (
              <button className="btn btn-warning btn-sm" type="button" onClick={() => setIsCloseModalOpen(true)}>
                <Archive size={14} />
                Close Active Event
              </button>
            ) : (
              <button className="btn btn-danger btn-sm dashboard-declare-button" type="button" onClick={() => openModule('/broadcast')}>
                <TriangleAlert size={14} />
                Declare New Disaster
              </button>
            )}
          </>
        }
      />

      <DashboardCloseEventModal
        activeEvent={dashboard?.active_event}
        isOpen={isCloseModalOpen}
        isClosingEvent={isClosingEvent}
        closeError={closeError}
        onClose={closeModal}
        onConfirm={handleCloseActiveEvent}
      />

      {isLoading && <LoadingState message="Loading dashboard..." />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && !error && dashboard && (
        <div className="dashboard-layout">
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
          />
        </div>
      )}
    </section>
  )
}
