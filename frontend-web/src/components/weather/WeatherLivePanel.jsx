import {
  Activity,
  CloudLightning,
  CloudRain,
  CloudSun,
  Cloudy,
  Clock,
  Sun,
  Thermometer,
  Wind,
} from 'lucide-react'
import {
  meter,
  rainDetail,
  valueWithUnit,
  windDetail,
} from '../../utils/weatherHelpers'

export default function WeatherLivePanel({
  latest,
  hasSnapshot,
  activeConditionKey,
  riskTone,
  liveTitle,
}) {
  return (
    <section className="wx-live-panel" aria-labelledby="liveWeatherTitle">
      <div className="wx-live-header">
        <div>
          <span className="wx-live-kicker"><Activity size={15} /> Auto forecast snapshots</span>
          <h2 className="wx-live-title" id="liveWeatherTitle">{liveTitle}</h2>
          <div className="wx-live-meta">
            {hasSnapshot
              ? `Updated ${latest.observed_at || latest.created_at || '-'} - ${latest.source_name}`
              : 'No saved weather snapshot yet. Laravel will save Open-Meteo snapshots automatically when the scheduler runs.'}
          </div>
        </div>
        <span className="wx-update-clock"><Clock size={14} /> Every 3 hours</span>
      </div>

      <div className="wx-condition-display">
        <div className="wx-current-condition">
          <div>
            <span className={`wx-condition-icon ${riskTone}`}>
              <WeatherConditionIcon conditionKey={activeConditionKey} size={28} />
            </span>
            <div className="wx-condition-name">{latest?.condition_name || 'No data yet'}</div>
            <div className="wx-condition-detail">
              {latest?.advisory_text || 'Wait for the scheduled refresh or use Refresh now to save the latest numeric snapshot.'}
            </div>
          </div>
          <div className="wx-severity-strip">
            {(latest?.risk_tags || ['No saved snapshot']).map((tag) => (
              <span className={`wx-status-pill ${riskTone}`} key={tag}>{tag}</span>
            ))}
          </div>
        </div>

        <div className="wx-readout-grid">
          <ReadoutCard
            className="temperature"
            label="Temperature monitoring"
            icon={Thermometer}
            value={valueWithUnit(latest?.temperature, '°C')}
            detail={latest?.apparent_temperature != null && latest?.humidity != null
              ? `Feels like ${latest.apparent_temperature}°C with ${latest.humidity}% humidity.`
              : 'Temperature and humidity appear after refresh.'}
            meter={meter(latest?.temperature, 45)}
            color="#8f5c18"
          />
          <ReadoutCard
            className="rainfall"
            label="Rainfall monitoring"
            icon={CloudRain}
            value={valueWithUnit(latest?.rainfall_mm, 'mm')}
            detail={rainDetail(latest)}
            meter={meter(latest?.rainfall_mm, 20)}
            color="#2a5a90"
          />
          <ReadoutCard
            className="wind"
            label="Wind speed and direction"
            icon={Wind}
            value={valueWithUnit(latest?.wind_speed, 'km/h')}
            detail={windDetail(latest)}
            meter={meter(latest?.wind_speed, 80)}
            color="#962020"
          />
        </div>
      </div>
    </section>
  )
}

function ReadoutCard({ className, label, icon: Icon, value, detail, meter: meterValue, color }) {
  return (
    <article className={`wx-readout-card ${className}`}>
      <div>
        <div className="wx-readout-top">
          <span className="wx-readout-label">{label}</span>
          <span className="wx-readout-icon"><Icon size={18} /></span>
        </div>
        <span className="wx-readout-value">{value}</span>
        <div className="wx-readout-detail">{detail}</div>
      </div>
      <div className="wx-meter" aria-hidden="true">
        <span style={{ '--meter-value': `${meterValue}%`, '--meter-color': color }} />
      </div>
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
