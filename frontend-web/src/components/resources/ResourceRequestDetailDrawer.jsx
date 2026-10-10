import {
  AlertTriangle,
  ArrowRight,
  CheckCircle2,
  Clock,
  ExternalLink,
  Package,
  Send,
  ShieldCheck,
  UserCheck,
  X,
} from 'lucide-react'
import Badge from '../ui/Badge'

export default function ResourceRequestDetailDrawer({
  request,
  isOpen,
  onClose,
  onValidateAndForward,
  onReturn,
  onEdit,
  isProcessing = false,
}) {
  if (!isOpen || !request) return null

  const isDuplicateRisk = request.is_duplicate_risk || checkDuplicateRisk(request)
  const handoffStep = getHandoffStep(request)

  return (
    <div className="rr-drawer-backdrop" onClick={onClose}>
      <div className="rr-drawer-container" onClick={(e) => e.stopPropagation()}>
        {/* Drawer Header */}
        <div className="rr-drawer-header">
          <div className="rr-drawer-title-group">
            <span className="rr-drawer-kicker">Resource Request Inspection</span>
            <h2 className="rr-drawer-title">Request #{request.request_id}</h2>
          </div>
          <button className="rr-drawer-close" type="button" onClick={onClose} aria-label="Close drawer">
            <X size={20} />
          </button>
        </div>

        {/* Drawer Scrollable Body */}
        <div className="rr-drawer-body">

          {/* Duplicate Risk Radar Warning Banner */}
          {isDuplicateRisk && (
            <div className="rr-risk-banner">
              <div className="rr-risk-icon">
                <AlertTriangle size={20} />
              </div>
              <div className="rr-risk-content">
                <div className="rr-risk-title">Potential Duplicate Request Detected</div>
                <div className="rr-risk-desc">
                  Another request for <strong>{request.need?.type}</strong> in <strong>{request.area?.label}</strong> was filed within 2 hours. Please review before validation.
                </div>
              </div>
            </div>
          )}

          {/* Status & Source Summary Cards */}
          <div className="rr-drawer-cards-grid">
            <div className="rr-drawer-card">
              <span className="rr-card-label">Source System</span>
              <div className="rr-card-val">
                <span className={`rr-system-pill ${request.source_system?.key === 'evatrack' ? 'in' : ''}`}>
                  {request.source_system?.label || request.request_source?.label || 'Manual Entry'}
                </span>
              </div>
            </div>
            <div className="rr-drawer-card">
              <span className="rr-card-label">Resource Status</span>
              <div className="rr-card-val">
                <Badge tone={statusTone(request.status?.key, request.validation?.key)}>
                  {request.status?.label || 'Pending'}
                </Badge>
              </div>
            </div>
            <div className="rr-drawer-card">
              <span className="rr-card-label">Validation Status</span>
              <div className="rr-card-val">
                <Badge tone={validationTone(request.validation?.key)}>
                  {request.validation?.label || 'Needs Validation'}
                </Badge>
              </div>
            </div>
          </div>

          {/* Request Needs & Beneficiary Section */}
          <div className="rr-drawer-section">
            <h3 className="rr-section-title"><Package size={16} /> Item & Location Details</h3>
            <div className="rr-detail-group">
              <div className="rr-detail-row">
                <span className="rr-detail-label">Requested Need:</span>
                <span className="rr-detail-value highlight">{request.need?.type}</span>
              </div>
              <div className="rr-detail-row">
                <span className="rr-detail-label">Quantity / Amount:</span>
                <span className="rr-detail-value">{request.need?.quantity_text}</span>
              </div>
              <div className="rr-detail-row">
                <span className="rr-detail-label">Target Area / Site:</span>
                <span className="rr-detail-value">{request.area?.label}</span>
              </div>
              <div className="rr-detail-row">
                <span className="rr-detail-label">Beneficiary Count:</span>
                <span className="rr-detail-value">{request.beneficiaries?.label || request.beneficiaries?.count || 'Not specified'}</span>
              </div>
              {request.requester && (
                <div className="rr-detail-row">
                  <span className="rr-detail-label">Requested By:</span>
                  <span className="rr-detail-value">{request.requester.name} ({request.requester.contact || 'No contact info'})</span>
                </div>
              )}
            </div>
          </div>

          {/* Handoff Audit Timeline */}
          <div className="rr-drawer-section">
            <h3 className="rr-section-title"><Clock size={16} /> TrackingAid Handoff Timeline</h3>
            <div className="rr-timeline">
              
              {/* Step 1: Logged */}
              <div className={`rr-timeline-item completed`}>
                <div className="rr-timeline-icon">
                  <CheckCircle2 size={16} />
                </div>
                <div className="rr-timeline-content">
                  <div className="rr-timeline-title">Request Registered</div>
                  <div className="rr-timeline-sub">Recorded in resQperation local database</div>
                </div>
              </div>

              {/* Step 2: Validated */}
              <div className={`rr-timeline-item ${handoffStep >= 2 ? 'completed' : 'active'}`}>
                <div className="rr-timeline-icon">
                  {handoffStep >= 2 ? <UserCheck size={16} /> : <Clock size={16} />}
                </div>
                <div className="rr-timeline-content">
                  <div className="rr-timeline-title">HQ Command Validation</div>
                  <div className="rr-timeline-sub">
                    {handoffStep >= 2 ? 'Verified by disaster responder' : 'Awaiting HQ review'}
                  </div>
                </div>
              </div>

              {/* Step 3: Forwarded */}
              <div className={`rr-timeline-item ${handoffStep >= 3 ? 'completed' : handoffStep === 2 ? 'active' : ''}`}>
                <div className="rr-timeline-icon">
                  {handoffStep >= 3 ? <Send size={16} /> : <ExternalLink size={16} />}
                </div>
                <div className="rr-timeline-content">
                  <div className="rr-timeline-title">TrackingAid Forwarding</div>
                  <div className="rr-timeline-sub">
                    {request.handoff?.tracking_reference
                      ? `Shared DB Reference: ${request.handoff.tracking_reference}`
                      : 'Pending sync to shared tracking aid'}
                  </div>
                </div>
              </div>

              {/* Step 4: Dispatch/Fulfill */}
              <div className={`rr-timeline-item ${handoffStep >= 4 ? 'completed' : ''}`}>
                <div className="rr-timeline-icon">
                  <ShieldCheck size={16} />
                </div>
                <div className="rr-timeline-content">
                  <div className="rr-timeline-title">Dispatch & Fulfilled</div>
                  <div className="rr-timeline-sub">
                    {handoffStep >= 4 ? 'Items dispatched to site' : 'Awaiting dispatch confirmation'}
                  </div>
                </div>
              </div>

            </div>
          </div>

          {/* Validation Notes */}
          {request.validation_notes && (
            <div className="rr-drawer-section">
              <h3 className="rr-section-title">Validation / Auditor Notes</h3>
              <div className="rr-notes-box">
                {request.validation_notes}
              </div>
            </div>
          )}

        </div>

        {/* Drawer Action Footer */}
        <div className="rr-drawer-footer">
          {request.validation?.key === 'needs_validation' && (
            <>
              <button
                className="button primary"
                type="button"
                disabled={isProcessing}
                onClick={() => onValidateAndForward(request)}
              >
                <ShieldCheck size={16} />
                Validate & Forward
              </button>
              <button
                className="button secondary danger-text"
                type="button"
                disabled={isProcessing}
                onClick={() => onReturn(request)}
              >
                Return Request
              </button>
            </>
          )}

          <button
            className="button secondary"
            type="button"
            onClick={() => onEdit(request)}
          >
            Edit Full Record <ArrowRight size={14} />
          </button>
        </div>
      </div>
    </div>
  )
}

function checkDuplicateRisk(request) {
  if (!request) return false
  return request.need?.quantity > 500 || request.source_system?.key === 'evatrack'
}

function getHandoffStep(request) {
  const vKey = request.validation?.key || 'needs_validation'
  const hTone = request.handoff?.tone

  if (request.status?.key === 'delivered') return 4
  if (hTone === 'green' || vKey === 'forwarded') return 3
  if (vKey === 'verified') return 2
  return 1
}

function statusTone(status, validationStatus) {
  if (['verified', 'forwarded'].includes(validationStatus)) return 'blue'
  return {
    pending: 'amber',
    acknowledged: 'blue',
    approved: 'green',
    rejected: 'red',
    delivered: 'green',
  }[status] || 'gray'
}

function validationTone(validationStatus) {
  return {
    needs_validation: 'amber',
    verified: 'blue',
    forwarded: 'green',
    returned: 'red',
  }[validationStatus] || 'gray'
}
