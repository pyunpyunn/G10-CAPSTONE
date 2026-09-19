import { useEffect, useState } from 'react'
import { RefreshCcw } from 'lucide-react'
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faXmark } from '@fortawesome/free-solid-svg-icons'
import { getWeatherWorkspace, refreshWeather } from '../api/weatherApi'
import WeatherMainColumn from '../components/weather/WeatherMainColumn'
import WeatherSidebar from '../components/weather/WeatherSidebar'
import LoadingState from '../components/ui/LoadingState'
import {
  apiErrorMessage,
  riskToneFor,
} from '../utils/weatherHelpers'

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
  const locationName = 'Barangay Mambaling, Cebu City'
  const updatedAt = latest?.observed_at || latest?.created_at || 'Not yet available'

  return (
    <section className="page weather-page active">
      <header className="weather-page-header">
        <div>
          <h1>Weather Updates</h1>
          <p>{locationName}</p>
        </div>
        <div className="weather-page-actions">
          <div className="weather-live-status">
            <strong>{isLoading ? 'Connecting' : 'Live'}</strong>
            <span>Updated {updatedAt}</span>
          </div>
          <button className="btn btn-primary btn-sm wx-refresh-button" type="button" disabled={isRefreshing} onClick={handleRefresh}>
            <RefreshCcw size={14} />
            {isRefreshing ? 'Refreshing' : 'Refresh'}
          </button>
        </div>
      </header>

      {isLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}
      
      {refreshMessage && (
        <div className="wx-save-message">
          <span>{refreshMessage}</span>
          <button
            type="button"
            className="wx-banner-close"
            onClick={() => setRefreshMessage('')}
            aria-label="Close message"
          >
            <FontAwesomeIcon icon={faXmark} />
          </button>
        </div>
      )}

      {!isLoading && !error && workspace && (
        <div className="weather-dashboard">
          <WeatherMainColumn
            latest={latest}
            sourceLinks={sourceLinks}
            hasSnapshot={hasSnapshot}
            activeConditionKey={activeConditionKey}
            riskTone={riskTone}
            activeEvent={workspace.active_event}
            locationName={locationName}
          />
          <WeatherSidebar
            workspace={workspace}
            logs={logs}
          />
        </div>
      )}
    </section>
  )
}