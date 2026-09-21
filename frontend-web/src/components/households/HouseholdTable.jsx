import { MapPin } from 'lucide-react'
import EmptyState from '../ui/EmptyState'

export default function HouseholdTable({ households, meta, selectedPurok, onOpen, onPageChange, onDispatchPurok }) {
  const hasPurokFilter = selectedPurok && selectedPurok !== 'all'

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
        {hasPurokFilter && (
          <button className="btn btn-primary btn-sm" type="button" onClick={() => onDispatchPurok(selectedPurok)}>
            Dispatch team to this purok
          </button>
        )}
      </div>
      <div className="table-scroll">
        <table className="household-table">
          <thead>
            <tr>
              <th>Household</th>
              <th>Purok</th>
              <th>People</th>
              <th>Status</th>
              <th>Last location</th>
              <th><span className="sr-only">Action</span></th>
            </tr>
          </thead>
          <tbody>
            {households.map((household) => (
              <tr className={household.priority?.key === 'urgent' ? 'urgent-row' : ''} key={household.household_id}>
                <td>
                  <strong>{household.household_name}</strong>
                  <small>{household.account_holder} {household.account_id ? `- ${household.account_id}` : ''}</small>
                </td>
                <td>{household.purok}</td>
                <td><span className="people-count"><strong>{household.people}</strong></span></td>
                <td><StatusBadge status={household.status} /></td>
                <td>
                  <strong className="location"><MapPin size={14} />{household.location?.label || 'No location'}</strong>
                  <small>{household.location?.note || 'Location not recorded'}</small>
                </td>
                <td>
                  <button className="button review" type="button" onClick={() => onOpen(household.household_id)}>
                    Review
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Pagination meta={meta} onPageChange={onPageChange} />
    </div>
  )
}

export function StatusBadge({ status }) {
  const key = status?.key || 'unchecked'
  const label = status?.label || 'Unchecked'
  return <span className={`status-badge ${key}`}>{label}</span>
}

export function PriorityPill({ priority }) {
  return <span className={`risk-pill ${priority?.key || 'stable'}`}>{priority?.label || 'Stable'}</span>
}

function Pagination({ meta, onPageChange }) {
  const total = Number(meta?.total || 0)
  const current = Number(meta?.current_page || 1)
  const last = Number(meta?.last_page || 1)
  const perPage = Number(meta?.per_page || 25)
  const hasRecords = total > 0

  const from = hasRecords ? Number(meta?.from || (current - 1) * perPage + 1) : 0
  const to = hasRecords ? Number(meta?.to || Math.min(current * perPage, total)) : 0
  const canGoPrevious = current > 1
  const canGoNext = current < last && hasRecords

  return (
    <div className="pagination">
      <span>{hasRecords ? `Showing ${from}-${to} of ${total}` : 'No records found'}</span>
      <div>
        <button className="icon-button" type="button" disabled={!canGoPrevious} onClick={() => onPageChange(current - 1)} aria-label="Previous page">{'<'}</button>
        <span className="page-number">{current}</span>
        <span className="page-divider">/</span>
        <span className="page-total">{last}</span>
        <button className="icon-button" type="button" disabled={!canGoNext} onClick={() => onPageChange(current + 1)} aria-label="Next page">{'>'}</button>
      </div>
    </div>
  )
}
