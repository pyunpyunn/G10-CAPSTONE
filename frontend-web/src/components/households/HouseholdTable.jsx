import { Battery, Smartphone } from 'lucide-react'
import EmptyState from '../ui/EmptyState'
import { tableRange } from '../../utils/householdStatusHelpers'

export default function HouseholdTable({ households, meta, onOpen, onPageChange }) {
  if (households.length === 0) {
    return (
      <div className="hh-tbl-wrap">
        <EmptyState title="No households found" message="Try another search, purok, status, or device filter." />
      </div>
    )
  }

  return (
    <div className="hh-tbl-wrap">
      <div className="hh-tbl-topbar">
        <span className="hh-tbl-label">Households</span>
        <span className="hh-tbl-count">{tableRange(meta)} - latest status row only</span>
      </div>
      <div className="hh-table-scroll">
        <table className="hh-monitor-table">
          <thead>
            <tr>
              <th>Household</th>
              <th>Purok</th>
              <th>People</th>
              <th>Status</th>
              <th>Source / time</th>
              <th>Device</th>
              <th>Last known location</th>
              <th>Priority</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {households.map((household) => (
              <tr className={household.priority?.key === 'urgent' ? 'hh-row-urgent' : ''} key={household.household_id}>
                <td>
                  <div className="hh-household-name">{household.household_name}</div>
                  <div className="hh-household-meta">
                    Acct: {household.account_holder} {household.account_id ? `- ${household.account_id}` : ''}
                  </div>
                </td>
                <td>{household.purok}</td>
                <td>{household.people}</td>
                <td><StatusBadge status={household.status} /></td>
                <td>
                  <div className="hh-status-source">{household.source?.label}</div>
                  <div className="hh-status-source">{household.source?.time || 'No report time'}</div>
                </td>
                <td><DeviceCell device={household.device} /></td>
                <td className="hh-location-cell">
                  <div className="hh-location-title">{household.location?.label}</div>
                  <div className="hh-location-note">{household.location?.note}</div>
                </td>
                <td><PriorityPill priority={household.priority} /></td>
                <td>
                  <div className="hh-action-set">
                    <button className={`btn btn-${household.priority?.key === 'urgent' ? 'danger' : 'secondary'} btn-sm`} type="button" onClick={() => onOpen(household.household_id)}>
                      {household.priority?.key === 'urgent' ? 'Dispatch' : 'Review'}
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="hh-table-note">
        <span>HQ action:</span> review, request responder check, or create dispatch. No manual household status edit from HQ.
      </div>
      <Pagination meta={meta} onPageChange={onPageChange} />
    </div>
  )
}

export function DeviceCell({ device }) {
  const battery = device?.lowest_battery

  return (
    <div className="hh-device-stack">
      <div className="hh-device-line">
        <Smartphone size={14} />
        {device?.total > 0 ? `${device.active} active / ${device.total} synced` : 'No synced device'}
      </div>
      <div className="hh-device-line">
        <BatteryDisplay value={battery} tone={device?.battery_tone} />
      </div>
      <div className={`hh-device-risk ${device?.risk?.key || 'none'}`}>{device?.risk?.label || 'No device data'}</div>
    </div>
  )
}

export function BatteryDisplay({ value, tone }) {
  const hasBattery = value !== null && value !== undefined && value !== ''
  const width = hasBattery ? Math.max(0, Math.min(100, Number(value))) : 0

  return (
    <span className="hh-battery">
      <Battery size={14} />
      <span className="hh-battery-track">
        <span className={`hh-battery-fill ${tone || 'unknown'}`} style={{ width: `${width}%` }} />
      </span>
      {hasBattery ? `${value}%` : 'No battery'}
    </span>
  )
}

export function StatusBadge({ status }) {
  return <span className={`badge bs-${status?.key || 'unchecked'}`}>{status?.label || 'Unchecked'}</span>
}

export function PriorityPill({ priority }) {
  return <span className={`hh-risk-pill ${priority?.key || 'stable'}`}>{priority?.label || 'Stable'}</span>
}

function Pagination({ meta, onPageChange }) {
  const current = meta.current_page || 1
  const last = meta.last_page || 1
  const canGoPrevious = current > 1
  const canGoNext = current < last

  return (
    <div className="hh-pagination">
      <span className="hh-pagination-info">Page <strong>{current}</strong> of {last} - {meta.per_page || 20} per page</span>
      <div className="hh-pagination-btns">
        <button className="pg-btn" type="button" disabled={!canGoPrevious} onClick={() => onPageChange(current - 1)}>{'<'}</button>
        <button className="pg-btn active" type="button">{current}</button>
        <button className="pg-btn" type="button" disabled={!canGoNext} onClick={() => onPageChange(current + 1)}>{'>'}</button>
      </div>
    </div>
  )
}
