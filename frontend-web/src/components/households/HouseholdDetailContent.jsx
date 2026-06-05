import { Info, ShieldCheck } from 'lucide-react'
import {
  BatteryDisplay,
  StatusBadge,
} from './HouseholdTable'

export default function HouseholdDetailContent({ detail, history }) {
  const household = detail.household

  return (
    <div>
      <div className="one-account-rule">
        <Info size={16} />
        <span><strong>Read-only status.</strong> HQ can review, request field checks, and create dispatch requests. Status changes come from household mobile reports or responder field reports.</span>
      </div>

      <div className={`hh-status-callout ${household.status?.key || 'unchecked'}`}>
        <div className="hh-status-main">
          <span className="hh-status-icon"><ShieldCheck size={18} /></span>
          <div>
            <div className="hh-status-title">Household status: {household.status?.label}</div>
            <div className="hh-status-meta">{household.source?.label} - {household.source?.datetime || 'No report time'}</div>
          </div>
        </div>
        <StatusBadge status={household.status} />
      </div>

      <div className="hh-detail-grid">
        {household.detail_tiles.map((tile) => (
          <div className="hh-detail-tile" key={tile.label}>
            <div className="k">{tile.label}</div>
            <div className="v">{tile.value}</div>
          </div>
        ))}
      </div>

      <FamilyMembersTable members={detail.members} />
      <MobileDevicesTable devices={detail.devices} />
      <StatusHistoryTable history={history} />
    </div>
  )
}

function FamilyMembersTable({ members }) {
  return (
    <DetailSection title="Family members" note={`${members.length} people`}>
      <table className="hh-detail-table">
        <thead>
          <tr>
            <th>Member</th>
            <th>Role</th>
            <th>Risk</th>
            <th>Device</th>
            <th>Battery</th>
            <th>Last allowed location</th>
            <th>Last seen</th>
          </tr>
        </thead>
        <tbody>
          {members.length === 0 ? (
            <EmptyTableRow colSpan={7} text="No household members are synced yet." />
          ) : (
            members.map((member) => (
              <tr key={member.member_id || member.name}>
                <td>
                  <div className="hh-household-name">{member.name}</div>
                  <div className="hh-household-meta">{member.age ? `${member.age} yrs` : 'Age not recorded'} {member.gender ? `- ${member.gender}` : ''}</div>
                </td>
                <td>{member.relation}</td>
                <td>{member.risk_flags}</td>
                <td>{member.device_name}</td>
                <td>{member.battery_level !== null && member.battery_level !== undefined ? `${member.battery_level}%` : 'No battery'}</td>
                <td>{member.last_allowed_location}</td>
                <td>{member.last_seen_at || 'No device seen'}</td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </DetailSection>
  )
}

function MobileDevicesTable({ devices }) {
  return (
    <DetailSection title="Mobile devices" note={`${devices.length} records`}>
      <table className="hh-detail-table">
        <thead>
          <tr>
            <th>Device</th>
            <th>User</th>
            <th>Battery</th>
            <th>Signal</th>
            <th>Last known location</th>
            <th>Allowed location</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          {devices.length === 0 ? (
            <EmptyTableRow colSpan={7} text="No mobile devices are synced for this household yet." />
          ) : (
            devices.map((device) => (
              <tr key={device.id}>
                <td>
                  <div className="hh-household-name">{device.device_name}</div>
                  <div className="hh-household-meta">{device.platform} - {device.app_role}</div>
                </td>
                <td>{device.member_name}</td>
                <td><BatteryDisplay value={device.battery_level} tone={device.battery_tone} /></td>
                <td>{device.signal_strength !== null ? `${device.signal_strength}%` : 'No signal'}</td>
                <td>{device.last_location_label}</td>
                <td>{device.allowed_location}</td>
                <td>{device.last_seen_at || 'No update'}</td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </DetailSection>
  )
}

function StatusHistoryTable({ history }) {
  return (
    <DetailSection title="Status history" note="Active event">
      <table className="hh-detail-table">
        <thead>
          <tr>
            <th>Time</th>
            <th>Status</th>
            <th>Submitted by</th>
            <th>Source</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
          {history.length === 0 ? (
            <EmptyTableRow colSpan={5} text="No status history for this active event yet." />
          ) : (
            history.map((log) => (
              <tr key={log.status_log_id}>
                <td>{log.submitted_at || 'No time'}</td>
                <td><StatusBadge status={log.status} /></td>
                <td>{log.submitted_by}</td>
                <td>{log.source}</td>
                <td>{log.notes || log.location_label || 'No note'}</td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </DetailSection>
  )
}

function DetailSection({ title, note, children }) {
  return (
    <>
      <div className="hh-section-label">
        <span>{title}</span>
        <span>{note}</span>
      </div>
      <div className="hh-detail-table-wrap">{children}</div>
    </>
  )
}

function EmptyTableRow({ colSpan, text }) {
  return (
    <tr>
      <td colSpan={colSpan} className="hh-empty-cell">{text}</td>
    </tr>
  )
}
