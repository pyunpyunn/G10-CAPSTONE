import { AlertTriangle } from 'lucide-react'
import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'
import {
  historyTone,
  valueWithUnit,
} from '../../utils/weatherHelpers'

export default function WeatherSidebar({ workspace, logs, monitorRows }) {
  return (
    <aside className="weather-side-column">
      <MonitoringStatus activeEvent={workspace.active_event} monitorRows={monitorRows} />
      <SourceRule autoRefresh={workspace.auto_refresh} />
      <AlertHistory logs={logs} />
      <ActiveEventLink activeEvent={workspace.active_event} />
    </aside>
  )
}

function MonitoringStatus({ activeEvent, monitorRows }) {
  return (
    <section className="panel">
      <div className="panel-head">
        <span className="panel-title">Monitoring status</span>
        <Badge tone={activeEvent ? 'red' : 'gray'}>{activeEvent ? 'Event-linked' : 'Monitoring'}</Badge>
      </div>
      <div className="wx-monitor-list">
        {monitorRows.map((row) => {
          const Icon = row.icon

          return (
            <div className={`wx-monitor-row ${row.tone}`} key={row.title}>
              <span className="wx-monitor-icon"><Icon size={16} /></span>
              <div>
                <span className="wx-monitor-title">{row.title}</span>
                <span className="wx-monitor-sub">{row.sub}</span>
              </div>
              <span className="wx-monitor-value">{row.value}</span>
            </div>
          )
        })}
      </div>
    </section>
  )
}

function SourceRule({ autoRefresh }) {
  return (
    <section className="panel">
      <div className="panel-head">
        <span className="panel-title">Source rule</span>
        <Badge tone="blue">Auto</Badge>
      </div>
      <div className="wx-source-note">
        {autoRefresh?.source || 'Open-Meteo Forecast API'} refreshes every 3 hours.
      </div>
      <div className="wx-source-note">
        Confirm PAGASA warnings before broadcasting.
      </div>
    </section>
  )
}

function AlertHistory({ logs }) {
  return (
    <section className="panel">
      <div className="panel-head"><span className="panel-title">Alert history</span></div>
      {logs.length === 0 ? (
        <EmptyState title="No weather logs yet" message="Saved snapshots will appear here after refresh." />
      ) : (
        <div className="log-list wx-history-list">
          {logs.map((log) => (
            <div className="log-item" key={log.weather_log_id}>
              <span className="log-time">{log.observed_time || '-'}</span>
              <span className={`log-dot log-${historyTone(log.risk_level)}`} />
              <div className="log-msg">
                {log.condition_name} - {log.source_name}
                <span className="log-extra">Rain {valueWithUnit(log.rainfall_mm, 'mm')}</span>
              </div>
            </div>
          ))}
        </div>
      )}
      <div className="wx-source-note">
        PAGASA Ten-Day API can be added after token approval.
      </div>
    </section>
  )
}

function ActiveEventLink({ activeEvent }) {
  return (
    <section className="panel">
      <div className="panel-head"><span className="panel-title">Active event link</span></div>
      {activeEvent ? (
        <div className="wx-event-link">
          <AlertTriangle size={16} />
          <div>
            <strong>{activeEvent.name}</strong>
            <span>{activeEvent.type_name} - {activeEvent.severity_label}</span>
          </div>
        </div>
      ) : (
        <EmptyState title="No active disaster event" message="Weather can still be monitored, but archive/SitRep linkage starts after an event is declared." />
      )}
    </section>
  )
}
