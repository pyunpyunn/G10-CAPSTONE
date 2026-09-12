import { useCallback, useEffect, useMemo, useState } from 'react'
import { Archive, RefreshCcw, TriangleAlert } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import {
  closeActiveEvent,
  getDashboardActivity,
  getDashboardDispatch,
  getDashboardRequests,
  getDashboardSummary,
  getDashboardWeather,
} from '../api/dashboardApi'
import DashboardCloseEventModal from '../components/dashboard/DashboardCloseEventModal'
import DashboardMainContent from '../components/dashboard/DashboardMainContent'
import DashboardOverview from '../components/dashboard/DashboardOverview'
import PageHeader from '../components/ui/PageHeader'
import {
  getCloseEventMessage,
  getStats,
} from '../utils/dashboardHelpers'

export default function DashboardPage() {
  const navigate = useNavigate()

  const [summaryState, setSummaryState] = useState({ data: null, isLoading: true, error: '' })
  const [dispatchState, setDispatchState] = useState({ data: null, isLoading: true, error: '' })
  const [weatherState, setWeatherState] = useState({ data: null, isLoading: true, error: '' })
  const [requestsState, setRequestsState] = useState({ data: null, isLoading: true, error: '' })
  const [activityState, setActivityState] = useState({ data: null, isLoading: true, error: '' })

  const [isCloseModalOpen, setIsCloseModalOpen] = useState(false)
  const [isClosingEvent, setIsClosingEvent] = useState(false)
  const [closeError, setCloseError] = useState('')

  const fetchSummary = useCallback(async () => {
    setSummaryState((current) => ({ ...current, isLoading: true, error: '' }))
    try {
      const data = await getDashboardSummary()
      setSummaryState({ data, isLoading: false, error: '' })
    } catch {
      setSummaryState({ data: null, isLoading: false, error: 'Summary data cannot be loaded right now.' })
    }
  }, [])

  const fetchDispatch = useCallback(async () => {
    setDispatchState((current) => ({ ...current, isLoading: true, error: '' }))
    try {
      const data = await getDashboardDispatch()
      setDispatchState({ data, isLoading: false, error: '' })
    } catch {
      setDispatchState({ data: null, isLoading: false, error: 'Dispatch data cannot be loaded right now.' })
    }
  }, [])

  const fetchWeather = useCallback(async () => {
    setWeatherState((current) => ({ ...current, isLoading: true, error: '' }))
    try {
      const data = await getDashboardWeather()
      setWeatherState({ data, isLoading: false, error: '' })
    } catch {
      setWeatherState({ data: null, isLoading: false, error: 'Weather snapshot cannot be loaded right now.' })
    }
  }, [])

  const fetchRequests = useCallback(async () => {
    setRequestsState((current) => ({ ...current, isLoading: true, error: '' }))
    try {
      const data = await getDashboardRequests()
      setRequestsState({ data, isLoading: false, error: '' })
    } catch {
      setRequestsState({ data: null, isLoading: false, error: 'Request summary cannot be loaded right now.' })
    }
  }, [])

  const fetchActivity = useCallback(async () => {
    setActivityState((current) => ({ ...current, isLoading: true, error: '' }))
    try {
      const data = await getDashboardActivity()
      setActivityState({ data, isLoading: false, error: '' })
    } catch {
      setActivityState({ data: null, isLoading: false, error: 'Recent activity logs cannot be loaded right now.' })
    }
  }, [])

  const loadAllWidgets = useCallback(() => {
    fetchSummary()
    fetchDispatch()
    fetchWeather()
    fetchRequests()
    fetchActivity()
  }, [fetchSummary, fetchDispatch, fetchWeather, fetchRequests, fetchActivity])

  useEffect(() => {
    loadAllWidgets()
  }, [loadAllWidgets])

  const stats = useMemo(() => getStats(summaryState.data), [summaryState.data])
  const hasActiveEvent = Boolean(summaryState.data?.active_event)

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
      if (result?.dashboard) {
        setSummaryState({
          data: {
            barangay_profile: result.dashboard.barangay_profile,
            active_event: result.dashboard.active_event,
            households: result.dashboard.households,
            map: result.dashboard.map,
          },
          isLoading: false,
          error: '',
        })
        setDispatchState({ data: result.dashboard.dispatch, isLoading: false, error: '' })
        setWeatherState({ data: result.dashboard.weather, isLoading: false, error: '' })
        setRequestsState({ data: result.dashboard.requests, isLoading: false, error: '' })
        setActivityState({ data: result.dashboard.recent_activity, isLoading: false, error: '' })
      } else {
        loadAllWidgets()
      }
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
            <button className="btn btn-secondary btn-sm" type="button" onClick={loadAllWidgets}>
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
        activeEvent={summaryState.data?.active_event}
        isOpen={isCloseModalOpen}
        isClosingEvent={isClosingEvent}
        closeError={closeError}
        onClose={closeModal}
        onConfirm={handleCloseActiveEvent}
      />

      <div className="dashboard-layout">
        <DashboardMainContent
          summaryState={summaryState}
          dispatchState={dispatchState}
          activityState={activityState}
          stats={stats}
          hasActiveEvent={hasActiveEvent}
          onOpenModule={openModule}
        />
        <DashboardOverview
          weatherState={weatherState}
          requestsState={requestsState}
          hasActiveEvent={hasActiveEvent}
          onOpenModule={openModule}
        />
      </div>
    </section>
  )
}
