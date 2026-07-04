import { MoreHorizontal } from 'lucide-react'
import { displayText } from '../../utils/resourceRequestHelpers'
import ActionMenu from '../ui/ActionMenu'
import Badge from '../ui/Badge'
import EmptyState from '../ui/EmptyState'
import PaginationBar from '../ui/PaginationBar'

export default function ResourceRequestQueueTable({
  requests = [],
  pagination = {},
  onView,
  onValidate,
  onForward,
  onReturn,
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
        {requests.length === 0 ? (
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
                <th>Validation</th>
                <th>TrackingAid handoff</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {requests.map((request) => {
                const actions = requestActions(request, onView, onValidate, onForward, onReturn)

                return (
                  <tr key={request.request_id}>
                    <td>
                      <div className="rr-ref">{request.request_id}</div>
                      <div className="rr-meta">{displayText(request.source_reference, 'No event/reference')} - {displayText(request.created_time)}</div>
                    </td>
                    <td>
                      <span className="rr-system-pill in">{request.source_system?.label || request.request_source.label}</span>
                      <div className="rr-meta">{request.request_category.label}</div>
                    </td>
                    <td>
                      <strong>{request.need.type}</strong>
                      <div className="rr-meta">{request.request_source.label}</div>
                    </td>
                    <td>
                      <strong>{request.need.quantity_text}</strong>
                      <div className="rr-meta">{request.urgency.label}</div>
                    </td>
                    <td>
                      {request.area.label}
                      <div className="rr-meta">{request.area.meta}</div>
                    </td>
                    <td>
                      <Badge tone={request.validation.tone}>{request.validation.label}</Badge>
                      <div className="rr-meta">{request.validation_notes || 'HQ check pending'}</div>
                    </td>
                    <td>
                      <span className={`rr-system-pill ${request.handoff.tone === 'green' ? 'out' : ''}`}>{request.handoff.label}</span>
                      {request.handoff.meta && <div className="rr-meta">{request.handoff.meta}</div>}
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

function requestActions(request, onView, onValidate, onForward, onReturn) {
  const status = request.validation?.key || 'needs_validation'
  const actions = [{ label: 'View', onClick: () => onView(request) }]

  if (status === 'needs_validation') {
    actions.push({ label: 'Validate', onClick: () => onValidate(request) })
    actions.push({ label: 'Return', onClick: () => onReturn(request) })
  }

  if (status === 'verified') {
    actions.push({ label: 'Forward to TrackingAid', onClick: () => onForward(request) })
  }

  return actions
}
