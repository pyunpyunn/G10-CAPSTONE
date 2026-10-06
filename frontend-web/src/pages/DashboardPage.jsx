import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { AlertOctagon, Edit3, MoreVertical, Pencil, PlusCircle, RefreshCcw, TriangleAlert } from 'lucide-react'
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
import { getStats } from '../utils/dashboardHelpers'

export default function DashboardPage() {
  const navigate = useNavigate()
  const [summaryState, setSummaryState] = useState({ data: null, isLoading: true, error: '' })
  const [dispatchState, setDispatchState] = useState({ data: null, isLoading: true, error: '' })
  const [weatherState, setWeatherState] = useState({ data: null, isLoading: true, error: '' })
  const [requestsState, setRequestsState] = useState({ data: null, isLoading: true, error: '' })
  const [activityState, setActivityState] = useState({ data: null, isLoading: true, error: '' })
  const [refreshVersion, setRefreshVersion] = useState(0)

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
      setRefreshVersion((version) => version + 1)
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
      setCloseError(closeEventError?.response?.data?.message || 'Unable to close active disaster event.')
    } finally {
      setIsClosingEvent(false)
    }
  }

  const disasterAction = hasActiveEvent ? (
    <button className="btn btn-warning btn-sm" type="button" onClick={() => openModule(`/broadcast?event_id=${encodeURIComponent(summaryState.data?.active_event?.event_id || '')}`)}>
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
          <>
            <button className="btn btn-secondary btn-sm" type="button" onClick={loadAllWidgets}>
              <RefreshCcw size={14} />
              Refresh
            </button>
            <DashboardHeaderActionMenu
              hasActiveEvent={hasActiveEvent}
              onCloseActiveEvent={() => setIsCloseModalOpen(true)}
              onOpenBroadcast={() => openModule(hasActiveEvent ? `/broadcast?event_id=${encodeURIComponent(summaryState.data?.active_event?.event_id || '')}` : '/broadcast')}
            />
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
        <div className="dashboard-layout-mobile-actions">{disasterAction}</div>
        <DashboardMainContent
          summaryState={summaryState}
          dispatchState={dispatchState}
          activityState={activityState}
          stats={stats}
          hasActiveEvent={hasActiveEvent}
          onOpenModule={openModule}
          refreshVersion={refreshVersion}
        />
        <DashboardOverview
          weatherState={weatherState}
          requestsState={requestsState}
          hasActiveEvent={hasActiveEvent}
          onOpenModule={openModule}
          disasterAction={disasterAction}
        />
      </div>
    </section>
  )
}

function DashboardHeaderActionMenu({ hasActiveEvent, onCloseActiveEvent, onOpenBroadcast }) {
  const [isOpen, setIsOpen] = useState(false)
  const menuRef = useRef(null)

  useEffect(() => {
    function handleClickOutside(event) {
      if (menuRef.current && !menuRef.current.contains(event.target)) {
        setIsOpen(false)
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  return (
    <div className="bc-card-head-actions" ref={menuRef} style={{ position: 'relative' }}>
      <button
        className="btn btn-secondary btn-sm"
        type="button"
        aria-label="Disaster options"
        aria-expanded={isOpen}
        onClick={() => setIsOpen((prev) => !prev)}
      >
        <MoreVertical size={16} />
      </button>

      {isOpen && (
        <div className="bc-card-dropdown" style={{ right: 0, top: 'calc(100% + 6px)' }}>
          {hasActiveEvent ? (
            <>
              <button
                className="bc-dropdown-item danger"
                type="button"
                onClick={() => {
                  setIsOpen(false)
                  onCloseActiveEvent?.()
                }}
              >
                <AlertOctagon size={14} />
                <span>CLOSE ACTIVE EVENT</span>
              </button>
              <button
                className="bc-dropdown-item"
                type="button"
                onClick={() => {
                  setIsOpen(false)
                  onOpenBroadcast?.()
                }}
              >
                <Edit3 size={14} />
                <span>UPDATE ACTIVE EVENT</span>
              </button>
            </>
          ) : (
            <button
              className="bc-dropdown-item primary"
              type="button"
              onClick={() => {
                setIsOpen(false)
                onOpenBroadcast?.()
              }}
            >
              <PlusCircle size={14} />
              <span>DECLARE ACTIVE EVENT</span>
            </button>
          )}
        </div>
      )}
    </div>
  )
}

