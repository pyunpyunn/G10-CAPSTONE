import {
  CloudLightning,
  CloudRain,
  CloudRainWind,
  Cloudy,
  ExternalLink,
  Sun,
} from 'lucide-react'
import EmptyState from '../ui/EmptyState'
import WeatherLivePanel from './WeatherLivePanel'

const conditionOptions = [
  { key: 'sunny', label: 'Sunny', sub: 'Clear', icon: Sun },
  { key: 'rainy', label: 'Rainy', sub: 'Rainfall', icon: CloudRain },
  { key: 'stormy', label: 'Stormy', sub: 'Thunderstorm', icon: CloudLightning },
  { key: 'cloudy', label: 'Cloudy', sub: 'Overcast', icon: Cloudy },
  { key: 'storm', label: 'Storm', sub: 'Severe', icon: CloudRainWind },
]

export default function WeatherMainColumn({
  latest,
  sourceLinks,
  hasSnapshot,
  activeConditionKey,
  riskTone,
  liveTitle,
}) {
  return (
    <div className="weather-main-column">
      <WeatherLivePanel
        latest={latest}
        hasSnapshot={hasSnapshot}
        activeConditionKey={activeConditionKey}
        riskTone={riskTone}
        liveTitle={liveTitle}
      />

      <WeatherConditionStates activeConditionKey={activeConditionKey} />
      <ForecastOutlook latest={latest} />
      <AdvisoryUpdates latest={latest} sourceLinks={sourceLinks} hasSnapshot={hasSnapshot} riskTone={riskTone} />
    </div>
  )
}

function WeatherConditionStates({ activeConditionKey }) {
  return (
    <section className="panel">
      <div className="panel-head"><span className="panel-title">Weather condition display</span></div>
      <div className="wx-condition-options" aria-label="Weather condition states">
        {conditionOptions.map((option) => {
          const Icon = option.icon
          const isActive = activeConditionKey === option.key || (activeConditionKey === 'stormy' && option.key === 'storm')

          return (
            <div className={`wx-condition-option ${isActive ? 'active' : ''}`} key={option.key}>
              <Icon size={20} />
              <span><strong>{option.label}</strong><span>{isActive ? 'Active now' : option.sub}</span></span>
            </div>
          )
        })}
      </div>
    </section>
  )
}

function ForecastOutlook({ latest }) {
  return (
    <section className="panel">
      <div className="panel-head"><span className="panel-title">Forecast outlook</span></div>
      {latest?.daily_forecast?.length ? (
        <div className="wx-forecast-grid">
          {latest.daily_forecast.map((day) => (
            <article className="wx-forecast-card" key={day.date}>
              <div className="wx-forecast-date">{day.date}</div>
              <strong>{day.condition_name}</strong>
              <span>{day.temp_min}°C - {day.temp_max}°C</span>
              <span>{day.rain_probability}% rain chance</span>
              <span>{day.rainfall_sum} mm expected rain</span>
              <span>Gusts up to {day.gust_max} km/h</span>
            </article>
          ))}
        </div>
      ) : (
        <EmptyState title="No forecast snapshot yet" message="The 3-day Open-Meteo outlook will appear after the scheduler or manual refresh saves a snapshot." />
      )}
    </section>
  )
}

function AdvisoryUpdates({ latest, sourceLinks, hasSnapshot, riskTone }) {
  return (
    <section className="panel">
      <div className="panel-head"><span className="panel-title">Latest advisory updates</span></div>
      <div className="wx-advisory-list">
        {hasSnapshot ? (
          <article className={`wx-advisory-card ${riskTone}`}>
            <div className="wx-advisory-head">
              <span className="wx-advisory-source">{latest.source_name} - Saved Forecast Snapshot</span>
              <span className="wx-advisory-time">{latest.observed_at || '-'}</span>
            </div>
            <div className="wx-msg">{latest.advisory_text}</div>
          </article>
        ) : (
          <EmptyState title="No saved advisory snapshot" message="The latest forecast snapshot will appear after the scheduled backend refresh runs." />
        )}

        {sourceLinks.map((source) => (
          <article className="wx-advisory-card info" key={source.url}>
            <div className="wx-advisory-head">
              <span className="wx-advisory-source">{source.name}</span>
              <a href={source.url} target="_blank" rel="noreferrer">
                Open <ExternalLink size={12} />
              </a>
            </div>
            <div className="wx-msg">{source.note}</div>
          </article>
        ))}
      </div>
    </section>
  )
}
