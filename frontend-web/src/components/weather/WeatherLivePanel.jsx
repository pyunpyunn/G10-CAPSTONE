import {
  CloudLightning,
  CloudRain,
  CloudSun,
  Cloudy,
  Sun,
} from 'lucide-react'
import {
  rainDetail,
  valueWithUnit,
  windDetail,
} from '../../utils/weatherHelpers'

export default function WeatherLivePanel({
  latest,
  activeConditionKey,
  riskTone,
}) {
  return (
    <aside className="weather-left-column">
      <section className="wx-panel wx-current-panel">
        <div className="wx-panel-head"><span className="wx-panel-title">Current condition</span></div>
        <div className="wx-current-row">
          <span className={`wx-condition-chip ${riskTone}`} aria-hidden="true">
            <WeatherConditionIcon conditionKey={activeConditionKey} size={18} />
          </span>
          <div className="wx-condition-name">{latest?.condition_name || 'No data yet'}</div>
        </div>
      </section>

      <MetricCard
        label="Temperature"
        value={valueWithUnit(latest?.temperature, '°C')}
        detail={latest?.apparent_temperature != null && latest?.humidity != null
          ? `Feels like ${latest.apparent_temperature}°C with ${latest.humidity}% humidity.`
          : 'Temperature and humidity appear after refresh.'}
        large
      />
      <MetricCard
        label="Rainfall"
        value={valueWithUnit(latest?.rainfall_mm, 'mm')}
        detail={rainDetail(latest)}
      />
      <MetricCard
        label="Wind"
        value={valueWithUnit(latest?.wind_speed, 'km/h')}
        detail={windDetail(latest)}
      />
    </aside>
  )
}

function MetricCard({ label, value, detail, large = false }) {
  return (
    <article className="wx-panel">
      <span className="wx-metric-label">{label}</span>
      <span className={`wx-metric-value ${large ? 'wx-metric-value--lg' : ''}`}>{value}</span>
      <div className="wx-metric-detail">{detail}</div>
    </article>
  )
}

function WeatherConditionIcon({ conditionKey, size }) {
  if (conditionKey === 'sunny') {
    return <Sun size={size} />
  }

  if (conditionKey === 'rainy') {
    return <CloudRain size={size} />
  }

  if (conditionKey === 'stormy' || conditionKey === 'storm') {
    return <CloudLightning size={size} />
  }

  if (conditionKey === 'cloudy') {
    return <Cloudy size={size} />
  }

  return <CloudSun size={size} />
}