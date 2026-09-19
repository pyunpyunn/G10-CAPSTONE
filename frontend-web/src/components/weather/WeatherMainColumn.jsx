import { ExternalLink } from 'lucide-react'
import EmptyState from '../ui/EmptyState'
import WeatherLivePanel from './WeatherLivePanel'

export default function WeatherMainColumn({
  latest,
  sourceLinks,
  hasSnapshot,
  activeConditionKey,
  riskTone,
  activeEvent,
  locationName,
}) {
  return (
    <>
      <WeatherLivePanel
        latest={latest}
        activeConditionKey={activeConditionKey}
        riskTone={riskTone}
      />

      <div className="weather-main-column">
        <ForecastOutlook latest={latest} locationName={locationName} />
        <AdvisoryUpdates latest={latest} hasSnapshot={hasSnapshot} riskTone={riskTone} activeEvent={activeEvent} />
        <SourceLinks sourceLinks={sourceLinks} />
      </div>
    </>
  )
}

function ForecastOutlook({ latest, locationName }) {
  return (
    <section className="wx-panel">
      <div className="wx-panel-head">
        <span className="wx-panel-title">Three-day forecast</span>
        {locationName && <span className="wx-panel-location">{locationName}</span>}
      </div>
      {latest?.daily_forecast?.length ? (
        <div className="wx-forecast-grid">
          {latest.daily_forecast.map((day) => (
            <article className="wx-forecast-card" key={day.date}>
              <div className="wx-forecast-date">{day.date}</div>
              <div className="wx-forecast-temp">{day.temp_max}° / {day.temp_min}°</div>
              <div className="wx-forecast-meta">{day.condition_name} · {day.rain_probability}% rain</div>
              <div className="wx-forecast-extra">{day.rainfall_sum} mm · gusts {day.gust_max} km/h</div>
            </article>
          ))}
        </div>
      ) : (
        <EmptyState title="No forecast snapshot yet" message="The 3-day Open-Meteo outlook will appear after the scheduler or manual refresh saves a snapshot." />
      )}
    </section>
  )
}

function AdvisoryUpdates({ latest, hasSnapshot, riskTone, activeEvent }) {
  return (
    <section className="wx-panel">
      <div className="wx-panel-head"><span className="wx-panel-title">Latest advisory updates</span></div>
      <div className="wx-advisory-list">
        {!activeEvent ? (
          <div className="wx-advisory-title">NO ACTIVE DISASTER</div>
        ) : activeEvent.latest_advisory ? (
          <article>
            <div className="wx-advisory-head">
              <span className="wx-advisory-source">Disaster advisory</span>
              <span className="wx-status-pill">Broadcast</span>
            </div>
            <div className="wx-advisory-title">{activeEvent.latest_advisory.title || activeEvent.name}</div>
            <div className="wx-advisory-text">{activeEvent.latest_advisory.message}</div>
            <div className="wx-advisory-context">
              <span className="wx-advisory-dot" aria-hidden="true" />
              {activeEvent.latest_advisory.sent_at || activeEvent.name}
            </div>
          </article>
        ) : hasSnapshot ? (
          <article>
            <div className="wx-advisory-title">{activeEvent.name}</div>
            <div className="wx-advisory-text">No disaster advisory has been broadcast for this active event.</div>
          </article>
        ) : (
          <EmptyState title="No saved advisory snapshot" message="The latest forecast snapshot will appear after the scheduled backend refresh runs." />
        )}
      </div>
    </section>
  )
}

function SourceLinks({ sourceLinks }) {
  if (!sourceLinks?.length) {
    return null
  }

  // Helper function to map full backend notes into short, concise titles/phrases
  const getShortPhrase = (source) => {
    const text = source.note || ''
    
    if (text.includes('Official public weather forecast')) {
      return 'Public Forecast & Warning Context'
    }
    if (text.includes('confirm named cyclones')) {
      return 'Named Cyclones, PAR Entry & Wind Signals'
    }
    if (text.includes('Reference for rainfall warning')) {
      return 'Rainfall, Thunderstorm & Flood Alerts'
    }
    if (text.includes('Structured numeric forecast')) {
      return 'Automated Open-Meteo Numeric Model'
    }

    return source.name || 'Official Weather Reference'
  }

  return (
    <section className="wx-panel">
      <div className="wx-panel-head">
        <span className="wx-panel-title">Official sources</span>
      </div>

      <div className="wx-source-links grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        {sourceLinks.map((source) => (
          <a
            key={source.url}
            href={source.url}
            target="_blank"
            rel="noreferrer"
            className="wx-source-card group flex flex-col justify-between p-3 rounded-lg border border-gray-200 hover:border-blue-500 hover:bg-blue-50/50 transition-all text-left no-underline h-full"
          >
            <div className="flex flex-col mb-3">
              <span className="wx-source-name font-semibold text-gray-900 group-hover:text-blue-600 text-sm leading-tight">
                {source.name}
              </span>
              <span className="wx-source-phrase text-xs text-gray-500 mt-1">
                {getShortPhrase(source)}
              </span>
            </div>

            <div className="wx-source-action">
              <span>Open</span>
              <ExternalLink size={12} />
            </div>
          </a>
        ))}
      </div>
    </section>
  )
}