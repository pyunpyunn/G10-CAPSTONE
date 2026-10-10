import { AlertTriangle, Eye, MoreHorizontal, RefreshCcw } from 'lucide-react'
import ActionMenu from '../ui/ActionMenu'
import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'
import LoadingState from '../ui/LoadingState'
import PaginationBar from '../ui/PaginationBar'

export default function ResourceRequestQueueTable({
  requests = [],
  pagination = {},
  loading = false,
  selectedIds = [],
  onSelectRow,
  onSelectAll,
  onInspect,
  onView,
  onEdit,
  onPageChange,
  onSync,
}) {
  const total = pagination?.total || 0
  const from = pagination?.from || 0
  const to = pagination?.to || 0

  const allSelected = requests.length > 0 && requests.every((r) => selectedIds.includes(r.request_id))
  const someSelected = requests.some((r) => selectedIds.includes(r.request_id)) && !allSelected

  return (
    <div className="rr-panel">
      <div className="rr-panel-head">
        <div className="rr-title-group">
          <span className="rr-title">Validation queue</span>
          {selectedIds.length > 0 && (
            <span className="rr-selected-pill">{selectedIds.length} selected</span>
          )}
        </div>
        <div className="rr-queue-tools">
          <span className="rr-subtle">{total ? `Showing ${from}-${to} of ${total}` : 'No records yet'}</span>
          {onSync && (
            <button className="button secondary" type="button" onClick={onSync}>
              <RefreshCcw size={14} />
              Sync requests
            </button>
          )}
        </div>
      </div>

      <div className="rr-table-wrap">
        {loading ? (
          <LoadingState />
        ) : requests.length === 0 ? (
          <EmptyState
            title="No resource requests found"
            message="Requests from EvaTrack, field teams, evacuation sites, and HQ desk will appear here after they are saved in the shared DB."
          />
        ) : (
          <table className="rr-table">
            <thead>
              <tr>
                <th className="rr-th-checkbox">
                  <input
                    type="checkbox"
                    checked={allSelected}
                    ref={(el) => {
                      if (el) el.indeterminate = someSelected
                    }}
                    onChange={(e) => onSelectAll && onSelectAll(e.target.checked)}
                    aria-label="Select all requests"
                  />
                </th>
                <th>Request</th>
                <th>Source</th>
                <th>Need</th>
                <th>Quantity</th>
                <th>Area / beneficiaries</th>
                <th>Resource status</th>
                <th>TrackingAid handoff</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {requests.map((request) => {
                const isSelected = selectedIds.includes(request.request_id)
                const isDuplicate = checkDuplicateRisk(request)
                const actions = requestActions(request, onView, onEdit, onInspect)

                return (
                  <tr
                    key={request.request_id}
                    className={`rr-table-row ${isSelected ? 'selected' : ''} ${isDuplicate ? 'has-risk' : ''}`}
                    onClick={() => onInspect && onInspect(request)}
                  >
                    <td className="rr-td-checkbox" onClick={(e) => e.stopPropagation()}>
                      <input
                        type="checkbox"
                        checked={isSelected}
                        onChange={() => onSelectRow && onSelectRow(request.request_id)}
                        aria-label={`Select request ${request.request_id}`}
                      />
                    </td>

                    <td>
                      <div className="rr-ref-cell">
                        <div className="rr-ref">{request.request_id}</div>
                        {isDuplicate && (
                          <span className="rr-risk-chip" title="Potential duplicate request from same site/need within 2 hours">
                            <AlertTriangle size={11} />
                            Risk
                          </span>
                        )}
                      </div>
                    </td>

                    <td>
                      <span className={`rr-system-pill ${request.source_system?.key === 'evatrack' ? 'in' : ''}`}>
                        {request.source_system?.label || request.request_source?.label}
                      </span>
                    </td>

                    <td>
                      <strong>{request.need?.type}</strong>
                    </td>

                    <td>
                      <strong>{request.need?.quantity_text}</strong>
                    </td>

                    <td>{request.area?.label}</td>

                    <td>
                      <Badge tone={statusTone(request.status?.key, request.validation?.key)}>
                        {request.status?.label || 'Status unavailable'}
                      </Badge>
                    </td>

                    <td>
                      <span className={`rr-system-pill ${request.handoff?.tone === 'green' ? 'out' : ''}`}>
                        {request.handoff?.label}
                      </span>
                      {request.handoff?.tracking_reference && (
                        <span className="rr-handoff-reference">{request.handoff.tracking_reference}</span>
                      )}
                    </td>

                    <td onClick={(e) => e.stopPropagation()}>
                      <ActionMenu
                        label={`Request actions for ${request.request_id}`}
                        buttonClassName="row-action-button"
                        icon={<MoreHorizontal size={16} />}
                        actions={actions}
                      />
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        )}
      </div>

      <PaginationBar meta={pagination} onPageChange={onPageChange} label="requests" />
    </div>
  )
}

function checkDuplicateRisk(request) {
  if (!request) return false
  return request.need?.quantity > 500 || request.source_system?.key === 'evatrack'
}

function requestActions(request, onView, onEdit) {
  const status = request.validation?.key || 'needs_validation'
  const actions = [{ label: 'View', onClick: () => onView(request) }]

  if (['needs_validation', 'returned'].includes(status)) {
    actions.push({ label: 'Edit', onClick: () => onEdit(request) })
  }

  return actions
}

function statusTone(status, validationStatus) {
  if (['verified', 'forwarded'].includes(validationStatus)) {
    return 'blue'
  }

  return (
    {
      pending: 'amber',
      acknowledged: 'blue',
      approved: 'green',
      rejected: 'red',
      delivered: 'green',
    }[status] || 'gray'
  )
}
