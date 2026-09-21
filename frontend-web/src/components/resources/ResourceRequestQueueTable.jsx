import { MoreHorizontal } from 'lucide-react'
import ActionMenu from '../ui/ActionMenu'
import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'
import LoadingState from '../ui/LoadingState'
import PaginationBar from '../ui/PaginationBar'

export default function ResourceRequestQueueTable({
  requests = [],
  pagination = {},
  loading = false,
  onView,
  onEdit,
  onPageChange,
}) {
  const total = pagination?.total || 0
  const from = pagination?.from || 0
  const to = pagination?.to || 0

  return (
    <div className="rr-panel">
      <div className="rr-panel-head">
        <span className="rr-title">Validation queue</span>
        <span className="rr-subtle">{total ? `Showing ${from}-${to} of ${total}` : 'No records yet'}</span>
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
                const actions = requestActions(request, onView, onEdit)

                return (
                  <tr key={request.request_id}>
                    <td>
                      <div className="rr-ref">{request.request_id}</div>
                    </td>
                    <td>
                      <span className="rr-system-pill in">{request.source_system?.label || request.request_source.label}</span>
                    </td>
                    <td>
                      <strong>{request.need.type}</strong>
                    </td>
                    <td>
                      <strong>{request.need.quantity_text}</strong>
                    </td>
                    <td>
                      {request.area.label}
                    </td>
                    <td>
                      <Badge tone={statusTone(request.status?.key, request.validation?.key)}>{request.status?.label || 'Pending'}</Badge>
                    </td>
                    <td>
                      <span className={`rr-system-pill ${request.handoff.tone === 'green' ? 'out' : ''}`}>{request.handoff.label}</span>
                      {request.handoff.tracking_reference && <span className="rr-handoff-reference">{request.handoff.tracking_reference}</span>}
                    </td>
                    <td>
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

  return {
    pending: 'amber',
    acknowledged: 'blue',
    approved: 'green',
    rejected: 'red',
    delivered: 'green',
  }[status] || 'gray'
}
