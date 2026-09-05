import { useEffect, useMemo, useState } from 'react'
import { RefreshCcw } from 'lucide-react'
import { getWeatherWorkspace, refreshWeather } from '../api/weatherApi'
import WeatherMainColumn from '../components/weather/WeatherMainColumn'
import WeatherSidebar from '../components/weather/WeatherSidebar'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import {
  apiErrorMessage,
  makeMonitorRows,
  riskToneFor,
} from '../utils/weatherHelpers'
import { pageDataError } from '../utils/pageShell'

export default function WeatherPage() {
  const [workspace, setWorkspace] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isRefreshing, setIsRefreshing] = useState(false)
  const [error, setError] = useState('')
  const [refreshMessage, setRefreshMessage] = useState('')

  useEffect(() => {
    let ignore = false

    async function loadInitialWeather() {
      try {
        const data = await getWeatherWorkspace()

        if (!ignore) {
          setWorkspace(data)
        }
      } catch {
        if (!ignore) {
          setError('Weather updates cannot be loaded right now. Please check the backend or database connection.')
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadInitialWeather()

    return () => {
      ignore = true
    }
  }, [])

  async function loadWeather() {
    setIsLoading(true)
    setError('')
    setRefreshMessage('')

    try {
      const data = await getWeatherWorkspace()
      setWorkspace(data)
    } catch {
      setError('Weather updates cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  async function handleRefresh() {
    setIsRefreshing(true)
    setError('')
    setRefreshMessage('')

    try {
      const data = await refreshWeather()
      setWorkspace(data)
      setRefreshMessage(data.active_event
        ? 'Weather snapshot saved for the active event.'
        : 'Weather monitoring snapshot saved without an active disaster event.')
    } catch (refreshError) {
      setError(apiErrorMessage(refreshError, 'Unable to refresh weather data right now. Latest saved logs are still shown.'))
    } finally {
      setIsRefreshing(false)
    }
  }

  const latest = workspace?.latest_snapshot || null
  const logs = workspace?.logs || []
  const sourceLinks = workspace?.source_links || []
  const hasSnapshot = Boolean(latest)
  const activeConditionKey = latest?.condition_key || 'monitoring'
  const riskTone = riskToneFor(latest?.risk_level)
  const liveTitle = hasSnapshot
    ? `${latest.condition_name} conditions over ${workspace?.location?.name || 'Barangay Sta. Cruz'}`
    : `Weather monitoring for ${workspace?.location?.name || 'Barangay Sta. Cruz'}`
  const isInitialLoading = isLoading && !workspace
  const dataError = pageDataError(error, Boolean(workspace))
  const monitorRows = useMemo(() => makeMonitorRows(latest), [latest])

  return (
    <section className="page weather-page active">
      <PageHeader
        title="Weather Updates"
        actions={
          <>
            <button className="btn btn-secondary btn-sm" type="button" onClick={loadWeather}>
              <RefreshCcw size={14} />
              Reload
            </button>
            <button className="btn btn-primary btn-sm wx-refresh-button" type="button" disabled={isRefreshing} onClick={handleRefresh}>
              <RefreshCcw size={14} />
              {isRefreshing ? 'Refreshing...' : 'Refresh now'}
            </button>
          </>
        }
      />

      {dataError ? <div className="page-data-notice is-error">{dataError}</div> : null}
      {isInitialLoading ? <LoadingState /> : null}
      {refreshMessage && <div className="wx-save-message">{refreshMessage}</div>}

      <div className="weather-dashboard">
        <WeatherMainColumn
          latest={latest}
          sourceLinks={sourceLinks}
          hasSnapshot={hasSnapshot}
          activeConditionKey={activeConditionKey}
          riskTone={riskTone}
          liveTitle={liveTitle}
        />
        <WeatherSidebar
          workspace={workspace || { active_event: null, auto_refresh: false, location: { name: 'Barangay Sta. Cruz' } }}
          logs={logs}
          monitorRows={monitorRows}
        />
      </div>
    </section>
  )
}
