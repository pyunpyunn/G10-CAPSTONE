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
  emptyDashboard,
} from '../utils/dashboardHelpers'
import RefreshOverlay from '../components/ui/RefreshOverlay'

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
      } catch (dashboardError) {
        if (!ignore) {
          setError(getDashboardErrorMessage(dashboardError))
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadInitialDashboard()

    const intervalId = window.setInterval(async () => {
      if (ignore) {
        return
      }

      try {
        const data = await getDashboard()

        if (!ignore) {
          setDashboard(data)
        }
      } catch {
        // Keep the last loaded dashboard visible during background refresh failures.
      }
    }, 30000)

    return () => {
      ignore = true
      window.clearInterval(intervalId)
    }
  }, [])

  const displayDashboard = dashboard || emptyDashboard()
  const stats = useMemo(() => getStats(displayDashboard), [displayDashboard])
  const hasActiveEvent = Boolean(displayDashboard?.active_event)
  const isInitialLoading = isLoading && !dashboard
  const isRefreshing = isLoading && Boolean(dashboard)
  async function loadDashboard() {
    setIsLoading(true)
    setError('')

    try {
      const data = await getDashboard()
      setDashboard(data)
    } catch (dashboardError) {
      setError(getDashboardErrorMessage(dashboardError))
    } finally {
      setIsLoading(false)
    }
  }

  function openModule(path) {
    navigate(path)
  }

  function getDashboardErrorMessage(error) {
    if (error?.response?.status === 401) {
      return 'Your session has expired or you are not authenticated. Please log in again.'
    }

    if (error?.response?.status === 403) {
      return 'You do not have permission to view the dashboard. Please use an admin account.'
    }

    if (error?.response?.status === 404) {
      return 'The dashboard API endpoint was not found on the backend.'
    }

    return error?.friendlyMessage || error?.response?.data?.message || 'Dashboard data cannot be loaded right now. Please check the backend or database connection.'
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
        activeEvent={displayDashboard?.active_event}
        isOpen={isCloseModalOpen}
        isClosingEvent={isClosingEvent}
        closeError={closeError}
        onClose={closeModal}
        onConfirm={handleCloseActiveEvent}
      />

      {error && !dashboard ? <div className="page-data-notice is-error">{error}</div> : null}
      {isInitialLoading ? <LoadingState /> : null}

      <RefreshOverlay active={isRefreshing}>
        <div className="dashboard-layout">
          <DashboardMainContent
            dashboard={displayDashboard}
            stats={stats}
            hasActiveEvent={hasActiveEvent}
            onOpenModule={openModule}
          />
          <DashboardOverview
            dashboard={displayDashboard}
            hasActiveEvent={hasActiveEvent}
            onOpenModule={openModule}
          />
        </div>
      </RefreshOverlay>
    </section>
  )
}
