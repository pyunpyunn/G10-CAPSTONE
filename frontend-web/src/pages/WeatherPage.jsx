import { useCallback, useEffect, useRef, useState } from 'react'
import { getWeatherWorkspace, refreshWeather } from '../api/weatherApi'
import WeatherIcon from '../components/weather/WeatherIcon'
import { useModuleData } from '../utils/useModuleData'
import { statusColor } from '../utils/statusPalette'
import { weatherDay, weatherGradient, weatherNumber as value, weatherTime } from '../utils/weatherDisplay'

const loadWeather = () => getWeatherWorkspace()
export default function WeatherPage() {
  const { data: workspace, loading, error, refresh } = useModuleData(loadWeather, ['weather', 'disasters', 'broadcast'], { keepPreviousData:true })
  const [selectedId, setSelectedId] = useState(null)
  const [forecastOpen, setForecastOpen] = useState(null)
  const [refreshing, setRefreshing] = useState(false)
  const [queued, setQueued] = useState(false)
  const [notice, setNotice] = useState('')
  const [refreshError, setRefreshError] = useState('')
  const queuedSnapshot = useRef(null)
  const displayedWorkspace = workspace
  const latest = displayedWorkspace?.latest_snapshot
  const logs = displayedWorkspace?.logs || []
  const snapshot = logs.find(log => String(log.weather_log_id) === String(selectedId)) || latest
  const isPast = Boolean(snapshot && latest && String(snapshot.weather_log_id) !== String(latest.weather_log_id))
  const event = displayedWorkspace?.active_event
  const advisory = event?.latest_advisory
  const age = snapshot?.observed_age_minutes
  const freshness = age == null ? 'Time not recorded' : age < 60 ? `${Math.max(0, Math.floor(age))}m ago` : `${Math.floor(age / 60)}h ago`
  const refreshSnapshot = useCallback(async () => {
    setRefreshing(true); setRefreshError(''); setNotice('')
    try {
      const result = await refreshWeather()
      queuedSnapshot.current = String(result.latest_snapshot?.weather_log_id || '')+'|'+String(result.latest_snapshot?.observed_at_iso || '')
      setSelectedId(null)
      setQueued(result.refresh_queued)
      setNotice(result.refresh_queued ? 'Refresh queued. The latest saved snapshot is shown until processing finishes.' : 'Weather snapshot refreshed.')
      refresh()
    } catch (failure) { setRefreshError(failure.response?.data?.message || 'Weather refresh failed. Try again.') }
    finally { setRefreshing(false) }
  }, [refresh])
  useEffect(() => {
    if (queued && workspace?.latest_snapshot) {
      const key = String(workspace.latest_snapshot.weather_log_id || '')+'|'+String(workspace.latest_snapshot.observed_at_iso || '')
      if (key !== queuedSnapshot.current) { setQueued(false); setNotice('Weather snapshot refreshed.') }
    }
  }, [queued, workspace])
  useEffect(() => {
    const timer = window.setInterval(() => { if (!document.hidden) refresh() }, 60000)
    return () => window.clearInterval(timer)
  }, [refresh])
  useEffect(() => {
    if (!queued || loading) return
    const interval = window.setInterval(refresh, 5000)
    return () => window.clearInterval(interval)
  }, [queued, loading, refresh])
  useEffect(() => {
    if (!queued) return
    const timeout = window.setTimeout(() => { setQueued(false); setNotice('Weather refresh is still processing. The latest saved snapshot is shown.') }, 60000)
    return () => window.clearTimeout(timeout)
  }, [queued])
  useEffect(() => {
    if (!notice) return
    const timeout = window.setTimeout(() => setNotice(''), 6000)
    return () => window.clearTimeout(timeout)
  }, [notice])
  return <section className="weather-reference" aria-label="Weather updates">
    {(error || refreshError) && <div className="form-error" role="alert">{refreshError || error}<button type="button" onClick={refreshError ? refreshSnapshot : refresh}>Retry</button></div>}
    {loading && !displayedWorkspace ? <div className="empty" role="status">Loading weather…</div> : displayedWorkspace && <div className="wg">
      <div className="mc">
        <section className="hero" style={{ '--g': weatherGradient(snapshot?.condition_key) }} aria-label="Weather conditions">
          <div className="hr1"><span className="loc">{displayedWorkspace.location?.name || 'Location not recorded'}</span><span className="mu">{snapshot ? `${weatherDay(snapshot.observed_at_iso)} · ${weatherTime(snapshot.observed_at_iso)}` : 'No saved snapshot'}</span><span className="sp" />{isPast ? <span className="fr"><i />Past snapshot<button type="button" onClick={() => setSelectedId(null)}>Latest</button></span> : <span className={`fr ${snapshot?.is_stale ? 'st' : ''}`} style={{ '--c': statusColor(snapshot ? snapshot.is_stale ? 'warning' : 'normal' : 'unchecked') }}><i />{snapshot ? `${snapshot.is_stale ? 'Stale · ' : 'Updated '}${freshness}` : 'No data'}</span>}</div>
          <div className="hm"><div><div className="tp">{value(snapshot?.temperature)}<sup>°C</sup></div><div className="cn">{snapshot?.condition_name || 'No weather data yet'}</div>{snapshot && <div className="rk"><span className="p" style={{ '--c': statusColor(snapshot.risk_level) }}><i />{snapshot.risk_label || snapshot.risk_level || 'Unknown'} risk</span>{snapshot.risk_tags?.map(tag => <span key={tag}>{tag}</span>)}</div>}</div><div className="big"><WeatherIcon condition={snapshot?.condition_key} /></div></div>
          <div className="mx"><div><em>Feels like</em><b>{value(snapshot?.apparent_temperature, '°')}</b></div><div><em>Humidity</em><b>{value(snapshot?.humidity, '%')}</b></div><div><em>Rain</em><b>{value(snapshot?.rainfall_mm, ' mm')}</b></div><div><em>Wind</em><b>{value(snapshot?.wind_speed, ' km/h')}</b> <small>{snapshot?.wind_direction}</small></div><div><em>Gust</em><b>{value(snapshot?.wind_gusts, ' km/h')}</b></div></div>
        </section>
        <section className="fc" aria-label="Three-day forecast">{snapshot?.daily_forecast?.length ? snapshot.daily_forecast.map(day => <button type="button" className={`fd ${forecastOpen === day.date_iso ? 'on' : ''}`} key={day.date_iso || day.date} aria-expanded={forecastOpen === day.date_iso} onClick={() => setForecastOpen(forecastOpen === day.date_iso ? null : day.date_iso)}><div className="fn"><b>{weatherDay(day.date_iso)}</b><em>{day.date}</em></div><WeatherIcon condition={day.condition_name} /><div className="ft"><b>{value(day.temp_max, '°')}</b><span>{value(day.temp_min, '°')}</span></div><div className="fp"><s><i style={{ width: `${day.rain_probability == null ? 0 : Math.max(0, Math.min(100, day.rain_probability))}%` }} /></s>{value(day.rain_probability, '%')}</div><div className="fx"><div><span>Rain <b>{value(day.rainfall_sum, ' mm')}</b></span><span>Gust <b>{value(day.gust_max, ' km/h')}</b></span></div></div></button>) : <div className="cd empty">No forecast snapshot available.</div>}</section>
        <div className="r2"><section className="cd adv" style={{ '--a': statusColor(event?.severity_key || (event ? 'warning' : 'unchecked')) }} aria-label="Disaster advisory"><div className="t">{!event ? 'NO ACTIVE DISASTER' : advisory?.title || event.name}</div>{event && <><p>{advisory?.message || 'No advisory broadcast for this event.'}</p><em>{event.name}{advisory?.sent_at ? ` · ${advisory.sent_at}` : ''}</em></>}</section>
          <section className="cd src" aria-label="Weather sources"><div className="lk">{displayedWorkspace.source_links?.map(source => <a href={/^https?:\/\//.test(source.url) ? source.url : undefined} key={source.url} target="_blank" rel="noopener noreferrer" title={source.note}>{source.name} ↗</a>)}</div><div className="mu">{displayedWorkspace.auto_refresh?.source} · {displayedWorkspace.auto_refresh?.frequency}. Forecast context; confirm warnings with {displayedWorkspace.auto_refresh?.official_warning_source || 'the official weather authority'}.</div></section></div>
      </div>
      <aside className="cd hist"><div className="ch">History</div><div className="cb">{logs.length ? logs.map(log => <button type="button" key={log.weather_log_id} className={`hs ${String(snapshot?.weather_log_id) === String(log.weather_log_id) ? 'on' : ''}`} onClick={() => { setSelectedId(log.weather_log_id); setForecastOpen(null) }} aria-label={`Weather snapshot ${log.observed_at || log.weather_log_id}`} aria-pressed={String(snapshot?.weather_log_id) === String(log.weather_log_id)}><span className="a">{weatherTime(log.observed_at_iso)}<em>{weatherDay(log.observed_at_iso)}</em></span><WeatherIcon condition={log.condition_key} /><span><b>{log.temperature == null ? '—' : value(log.temperature, '°')}</b> <span className="m">{value(log.rainfall_mm, ' mm')} · {value(log.wind_speed, ' km/h')}</span></span><i className="rd" style={{ '--c': statusColor(log.risk_level) }} title={log.risk_label || log.risk_level} />{log.advisory_title && <span className="t">{log.advisory_title}</span>}</button>) : <div className="empty">No weather logs yet.</div>}</div></aside>
    </div>}
    <button type="button" className={`rf ${refreshing ? 'spin' : ''}`} disabled={refreshing || queued} onClick={refreshSnapshot} aria-label="Refresh weather data" title={queued ? 'Weather refresh queued' : 'Refresh weather data'}><i>↻</i></button>
    {notice && <div className="toast s" role="status">{notice}</div>}
  </section>
}
