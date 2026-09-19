import { ShieldCheck } from 'lucide-react'
import {
  StatusBadge,
} from './HouseholdTable'

export default function HouseholdDetailContent({ detail, history }) {
  const household = detail.household

  return (
    <div>
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
      <StatusHistoryTable history={history} />
    </div>
  )
}

function FamilyMembersTable({ members }) {
  return (
    <DetailSection title="Family members" note={`${members.length} people - household status applies to all`}>
      <table className="hh-detail-table">
        <thead>
          <tr>
            <th>Member</th>
            <th>Role</th>
            <th>Gender</th>
            <th>Head</th>
            <th>PWD</th>
            <th>Senior</th>
            <th>Pregnant</th>
          </tr>
        </thead>
        <tbody>
          {members.length === 0 ? (
            <EmptyTableRow colSpan={8} text="No household members are synced yet." />
          ) : (
            members.map((member) => (
              <tr key={member.member_id || member.name}>
                <td>
                  <div className="hh-household-name">{member.name}</div>
                  <div className="hh-household-meta">{member.age ? `${member.age} yrs` : 'Age not recorded'} {member.gender ? `- ${member.gender}` : ''}</div>
                </td>
                <td>{member.relation}</td>
                <td>{member.gender}</td>
                <td>{member.is_household_head ? 'Yes' : 'No'}</td>
                <td>{member.is_pwd ? 'Yes' : 'No'}</td>
                <td>{member.is_senior ? 'Yes' : 'No'}</td>
                <td>{member.is_pregnant ? 'Yes' : 'No'}</td>
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
          </tr>
        </thead>
        <tbody>
          {history.length === 0 ? (
            <EmptyTableRow colSpan={4} text="No status history for this active event yet." />
          ) : (
            history.map((log) => (
              <tr key={log.status_log_id}>
                <td>{log.submitted_at || 'No time'}</td>
                <td><StatusBadge status={log.status} /></td>
                <td>{log.submitted_by}</td>
                <td>{log.source}</td>
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
