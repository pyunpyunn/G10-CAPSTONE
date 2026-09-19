import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'
import {
  historyTone,
  valueWithUnit,
} from '../../utils/weatherHelpers'

export default function WeatherSidebar({ workspace, logs }) {
  return (
    <aside className="weather-side-column">
      <SourceRule autoRefresh={workspace.auto_refresh} />
      <AlertHistory logs={logs} />
    </aside>
  )
}

function SourceRule({ autoRefresh }) {
  return (
    <section className="wx-panel">
      <div className="wx-panel-head">
        <span className="wx-panel-title">Source freshness</span>
        <Badge tone="blue">Live</Badge>
      </div>
      <div className="wx-freshness-row">
        <span className="wx-freshness-dot" aria-hidden="true" />
        <strong>{autoRefresh?.source || 'Open-Meteo Forecast API'}</strong>
      </div>
      <div className="wx-freshness-meta">
        Latest snapshot refreshes every 3 hours.
      </div>
    </section>
  )
}

function AlertHistory({ logs }) {
  return (
    <section className="wx-panel">
      <div className="wx-panel-head"><span className="wx-panel-title">Alert history</span></div>
      {logs.length === 0 ? (
        <EmptyState title="No weather logs yet" message="Saved snapshots will appear here after refresh." />
      ) : (
        <div className="wx-history-list">
          {logs.map((log) => (
            <div className="wx-history-item" key={log.weather_log_id}>
              <span className={`wx-history-dot ${historyTone(log.risk_level)}`} aria-hidden="true" />
              <div className="wx-history-body">
                <span className="wx-history-title">{log.condition_name} · {log.source_name}</span>
                <span className="wx-history-meta">{log.observed_at || log.observed_time || '-'} · {log.advisory_title || 'Weather snapshot'}</span>
                <span className="wx-history-meta">
                  Temp {valueWithUnit(log.temperature, 'C')} · Feels {valueWithUnit(log.apparent_temperature, 'C')} · Humidity {valueWithUnit(log.humidity, '%')}
                </span>
                <span className="wx-history-meta">
                  Rain {valueWithUnit(log.rainfall_mm, 'mm')} · Wind {valueWithUnit(log.wind_speed, 'km/h')} {log.wind_direction || ''} · Gusts {valueWithUnit(log.wind_gusts, 'km/h')}
                </span>
                {log.advisory_text && <span className="wx-history-text">{log.advisory_text}</span>}
                {log.risk_tags?.length > 0 && <span className="wx-history-meta">{log.risk_tags.join(' · ')}</span>}
              </div>
            </div>
          ))}
        </div>
      )}
      <div className="wx-footnote">
        PAGASA Ten-Day API can be added after token approval.
      </div>
    </section>
  )
}