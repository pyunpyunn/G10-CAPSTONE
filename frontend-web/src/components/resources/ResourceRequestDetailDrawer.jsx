import {
  AlertTriangle,
  ArrowRight,
  CheckCircle2,
  Clock,
  ExternalLink,
  FileCheck2,
  MapPin,
  Package,
  RotateCw,
  Send,
  ShieldCheck,
  Truck,
  UserCheck,
  X,
} from 'lucide-react'
import Badge from '../ui/Badge'

export default function ResourceRequestDetailDrawer({
  request,
  isOpen,
  mode = 'view', // 'view' | 'tracking'
  onClose,
  onValidateAndForward,
  onReturn,
  onEdit,
  canApprove = false,
  isProcessing = false,
}) {
  if (!isOpen || !request) return null

  const isDuplicateRisk = request.is_duplicate_risk || checkDuplicateRisk(request)
  const handoffStep = getHandoffStep(request)
  const isAwaitingApproval = request.validation?.key === 'needs_validation'
  const isTrackingMode = mode === 'tracking'

  return (
    <div className="rr-drawer-backdrop" onClick={onClose}>
      <div className="rr-drawer-container" onClick={(e) => e.stopPropagation()}>
        {/* Drawer Header */}
        <div className="rr-drawer-header">
          <div className="rr-drawer-title-group">
            <span className="rr-drawer-kicker">
              {isTrackingMode
                ? 'Status Tracking of Requests'
                : isAwaitingApproval
                ? 'For Approval Request Inspection'
                : 'Resource Request Details'}
            </span>
            <h2 className="rr-drawer-title">
              Request #{request.request_id}
              {request.need?.item_name
                ? ` · ${request.need.item_name}`
                : request.need?.type
                ? ` · ${request.need.type}`
                : ''}
            </h2>
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
            {isTrackingMode ? (
              <>
                <div className="rr-drawer-card">
                  <span className="rr-card-label">Resource Status</span>
                  <div className="rr-card-val">
                    <Badge tone={statusTone(request.status?.key, request.validation?.key)}>
                      {request.status?.label || 'Status unavailable'}
                    </Badge>
                  </div>
                </div>
                <div className="rr-drawer-card">
                  <span className="rr-card-label">Handoff Status</span>
                  <div className="rr-card-val">
                    <Badge tone={request.handoff?.tone || 'gray'}>
                      {request.handoff?.label || 'Handoff unavailable'}
                    </Badge>
                  </div>
                </div>
              </>
            ) : (
              <>
                <div className="rr-drawer-card">
                  <span className="rr-card-label">Priority</span>
                  <div className="rr-card-val">
                    <span className={`urgency-tag urgency-${request.urgency?.key || 'medium'}`}>
                      {request.urgency?.label || 'Medium'}
                    </span>
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
              </>
            )}
          </div>

          {/* Depot Stock Pre-Check Banner for Approval */}
          {!isTrackingMode && isAwaitingApproval && (
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                background: '#f0fdf4',
                border: '1px solid #bbf7d0',
                padding: '10px 14px',
                borderRadius: '8px',
              }}
            >
              <CheckCircle2 size={18} style={{ color: '#16a34a', flexShrink: 0 }} />
              <div style={{ fontSize: '12px', color: '#166534', lineHeight: 1.4 }}>
                <strong>Depot Inventory Pre-Check: Stock Available</strong>
                <div>Requested supplies are verified in warehouse bays. Approving this request will immediately deduct stock and sync allocation to TrackingAid Shared DB.</div>
              </div>
            </div>
          )}

          {/* VIEW MODE: Request & Validation Details ONLY (No status tracking) */}
          {!isTrackingMode ? (
            <>
              {/* Request Needs & Specifications */}
              <div className="rr-drawer-section">
                <h3 className="rr-section-title"><Package size={16} /> Resource Need Details</h3>
                <div className="rr-detail-group">
                  <div className="rr-detail-row">
                    <span className="rr-detail-label">Requested Need:</span>
                    <span className="rr-detail-value highlight">{request.need?.type || 'Supplies'}</span>
                  </div>
                  {request.need?.item_name && request.need?.item_name !== request.need?.type && (
                    <div className="rr-detail-row">
                      <span className="rr-detail-label">Item Name:</span>
                      <span className="rr-detail-value">{request.need.item_name}</span>
                    </div>
                  )}
                  <div className="rr-detail-row">
                    <span className="rr-detail-label">Quantity / Amount:</span>
                    <span className="rr-detail-value">{request.need?.quantity_text || `${request.need?.quantity || 1} units`}</span>
                  </div>
                  {request.request_category?.label && (
                    <div className="rr-detail-row">
                      <span className="rr-detail-label">Category:</span>
                      <span className="rr-detail-value">{request.request_category.label}</span>
                    </div>
                  )}
                  <div className="rr-detail-row">
                    <span className="rr-detail-label">Priority Level:</span>
                    <span className="rr-detail-value">
                      <span className={`urgency-tag urgency-${request.urgency?.key || 'medium'}`}>
                        {request.urgency?.label || 'Medium'}
                      </span>
                    </span>
                  </div>
                  <div className="rr-detail-row">
                    <span className="rr-detail-label">Date Submitted:</span>
                    <span className="rr-detail-value">{request.created_at || 'Recently submitted'}</span>
                  </div>
                </div>

                {/* Justification / Request Description */}
                {request.description && (
                  <div style={{ marginTop: '8px' }}>
                    <span className="rr-detail-label" style={{ display: 'block', marginBottom: '4px', fontSize: '12px' }}>
                      Justification / Area Note:
                    </span>
                    <div className="rr-notes-box">
                      {request.description}
                    </div>
                  </div>
                )}
              </div>

              {/* Evacuation Area & Beneficiary Details */}
              <div className="rr-drawer-section">
                <h3 className="rr-section-title"><MapPin size={16} /> Destination & Beneficiary</h3>
                <div className="rr-detail-group">
                  <div className="rr-detail-row">
                    <span className="rr-detail-label">Target Evacuation Site:</span>
                    <span className="rr-detail-value highlight">{request.area?.label || 'General Relief Hub'}</span>
                  </div>
                  {request.area?.meta && (
                    <div className="rr-detail-row">
                      <span className="rr-detail-label">Destination Address:</span>
                      <span className="rr-detail-value">{request.area.meta}</span>
                    </div>
                  )}
                  {request.area?.event?.name && (
                    <div className="rr-detail-row">
                      <span className="rr-detail-label">Disaster Event:</span>
                      <span className="rr-detail-value">{request.area.event.name}</span>
                    </div>
                  )}
                  <div className="rr-detail-row">
                    <span className="rr-detail-label">Beneficiary Count:</span>
                    <span className="rr-detail-value">{request.beneficiaries?.label || request.beneficiaries?.count || 'Not specified'}</span>
                  </div>
                  <div className="rr-detail-row">
                    <span className="rr-detail-label">Requested By:</span>
                    <span className="rr-detail-value">{request.requested_by || request.requester?.name || 'Disaster Staff'}</span>
                  </div>
                  {request.requester?.contact && (
                    <div className="rr-detail-row">
                      <span className="rr-detail-label">Contact Number:</span>
                      <span className="rr-detail-value">{request.requester.contact}</span>
                    </div>
                  )}
                </div>
              </div>

              {/* Validation & Verification Record */}
              <div className="rr-drawer-section">
                <h3 className="rr-section-title"><FileCheck2 size={16} /> Validation Record</h3>
                <div className="rr-detail-group">
                  <div className="rr-detail-row">
                    <span className="rr-detail-label">Validation Decision:</span>
                    <span className="rr-detail-value">
                      <Badge tone={validationTone(request.validation?.key)}>
                        {request.validation?.label || 'Needs Validation'}
                      </Badge>
                    </span>
                  </div>
                  {request.validated_by_user_id && (
                    <div className="rr-detail-row">
                      <span className="rr-detail-label">Validated By:</span>
                      <span className="rr-detail-value">{request.validated_by_user_id}</span>
                    </div>
                  )}
                  {request.validated_at && (
                    <div className="rr-detail-row">
                      <span className="rr-detail-label">Validated At:</span>
                      <span className="rr-detail-value">{request.validated_at}</span>
                    </div>
                  )}
                </div>

                {request.validation_notes && (
                  <div style={{ marginTop: '8px' }}>
                    <span className="rr-detail-label" style={{ display: 'block', marginBottom: '4px', fontSize: '12px' }}>
                      Validation / Auditor Notes:
                    </span>
                    <div className="rr-notes-box">
                      {request.validation_notes}
                    </div>
                  </div>
                )}

                {request.missing_information && (
                  <div style={{ marginTop: '8px' }}>
                    <span className="rr-detail-label" style={{ display: 'block', marginBottom: '4px', fontSize: '12px', color: '#dc2626' }}>
                      Missing Details / Return Reason:
                    </span>
                    <div className="rr-notes-box" style={{ background: '#fef2f2', borderColor: '#fecaca', color: '#991b1b' }}>
                      {request.missing_information}
                    </div>
                  </div>
                )}
              </div>
            </>
          ) : (
            /* TRACKING MODE: Status Tracking & Logistics Stream ONLY */
            <>
              {/* Item & Logistics Overview */}
              <div className="rr-drawer-section">
                <h3 className="rr-section-title">
                  <Truck size={16} />
                  <span>Logistics & Fulfillment Stream</span>
                </h3>
                <div className="rr-stream-card">
                  <div className="rr-stream-row">
                    <span className="rr-stream-label">Tracking Reference:</span>
                    <span className="rr-stream-ref">
                      {request.handoff?.tracking_reference || request.tracking_reference || 'Ref: Auto-synced'}
                    </span>
                  </div>
                  <div className="rr-stream-row">
                    <span className="rr-stream-label">Relief Supplies:</span>
                    <span className="rr-stream-val">
                      {formatQuantityText(request.need?.quantity, request.need?.unit, request.need?.quantity_text)} of {request.need?.item_name || request.need?.type || 'Supplies'}
                    </span>
                  </div>
                  <div className="rr-stream-row">
                    <span className="rr-stream-label">Delivery Destination:</span>
                    <div className="rr-stream-dest">
                      <span className="rr-dest-title">{request.area?.label || 'General Relief Hub'}</span>
                      {request.area?.meta && (
                        <span className="rr-dest-meta" title={request.area.meta}>
                          {request.area.meta}
                        </span>
                      )}
                    </div>
                  </div>
                  <div className="rr-stream-row">
                    <span className="rr-stream-label">TrackingAid Shared DB:</span>
                    <span className={`rr-sync-pill ${request.handoff?.tracking_reference ? 'connected' : 'pending'}`}>
                      <span className={`rr-sync-dot ${request.handoff?.tracking_reference ? 'live' : 'pulse'}`} />
                      <span>
                        {request.handoff?.tracking_reference
                          ? `Connected (${request.handoff.tracking_reference})`
                          : 'Pending Sync to Shared DB'}
                      </span>
                    </span>
                  </div>
                </div>

                <div style={{ marginTop: '4px' }}>
                  <a
                    href="/mapping"
                    className="rr-map-btn"
                  >
                    <MapPin size={13} style={{ color: '#2563eb' }} />
                    <span>View Evacuation Center on Live Map</span>
                    <ExternalLink size={12} style={{ opacity: 0.6 }} />
                  </a>
                </div>
              </div>

              {/* Handoff Audit Timeline */}
              <div className="rr-drawer-section">
                <h3 className="rr-section-title">
                  <Clock size={16} />
                  <span>TrackingAid Handoff Timeline</span>
                </h3>
                <div className="rr-timeline">
                  {/* Step 1: Logged */}
                  <div className="rr-timeline-item completed">
                    <div className="rr-timeline-icon">
                      <CheckCircle2 size={15} />
                    </div>
                    <div className="rr-timeline-content">
                      <div className="rr-timeline-title">Request Registered</div>
                      <div className="rr-timeline-sub">Recorded in resQperation local database</div>
                    </div>
                  </div>

                  {/* Step 2: Validated */}
                  <div className={`rr-timeline-item ${handoffStep >= 2 ? 'completed' : 'active'}`}>
                    <div className="rr-timeline-icon">
                      {handoffStep >= 2 ? <UserCheck size={15} /> : <Clock size={15} />}
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
                      {handoffStep >= 3 ? <Send size={15} /> : <ExternalLink size={15} />}
                    </div>
                    <div className="rr-timeline-content">
                      <div className="rr-timeline-title">TrackingAid Shared DB Forwarding</div>
                      <div className="rr-timeline-sub">
                        {request.handoff?.tracking_reference
                          ? `Ref: ${request.handoff.tracking_reference} (resqperation_forwarded_requests)`
                          : 'Synced to TrackingAid shared database upon approval'}
                      </div>
                    </div>
                  </div>

                  {/* Step 4: Dispatch/Fulfill */}
                  <div className={`rr-timeline-item ${handoffStep >= 4 ? 'completed' : ''}`}>
                    <div className="rr-timeline-icon">
                      <ShieldCheck size={15} />
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
            </>
          )}

        </div>

        {/* Drawer Action Footer */}
        <div className="rr-drawer-footer" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          {isAwaitingApproval ? (
            <>
              {canApprove && (
                <button
                  className="button review"
                  type="button"
                  disabled={isProcessing}
                  onClick={() => onValidateAndForward(request)}
                  style={{ padding: '7px 16px', fontSize: '12px' }}
                >
                  <CheckCircle2 size={14} />
                  <span>Approve Request</span>
                </button>
              )}
              {canApprove && (
                <button
                  className="button secondary danger-text"
                  type="button"
                  disabled={isProcessing}
                  onClick={() => onReturn(request)}
                  style={{ padding: '7px 14px', fontSize: '12px' }}
                >
                  <span>Return Request</span>
                </button>
              )}
              {canApprove && (
                <button
                  className="button secondary"
                  type="button"
                  onClick={() => onEdit(request)}
                  style={{ padding: '7px 14px', fontSize: '12px', marginLeft: 'auto' }}
                >
                  <span>Full Editor</span>
                  <ArrowRight size={13} />
                </button>
              )}
            </>
          ) : (
            <>
              {canApprove && handoffStep >= 4 && (
                <button
                  className="button secondary danger-text"
                  type="button"
                  disabled={isProcessing}
                  onClick={() => onReturn(request)}
                  style={{ padding: '7px 14px', fontSize: '12px' }}
                >
                  <RotateCw size={13} />
                  <span>Return Assets to Depot</span>
                </button>
              )}
              {canApprove && (
                <button
                  className="button secondary"
                  type="button"
                  onClick={() => onEdit(request)}
                  style={{ padding: '7px 16px', fontSize: '12px', marginLeft: 'auto' }}
                >
                  <span>View Full Record</span>
                  <ArrowRight size={13} />
                </button>
              )}
            </>
          )}
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
  if (!request) return 1
  const vKey = request.validation?.key || 'needs_validation'
  const hTone = request.handoff?.tone
  const hLabel = request.handoff?.label

  if (request.status?.key === 'delivered' || request.status?.key === 'fulfilled') return 4
  if (hTone === 'green' || vKey === 'forwarded' || hLabel === 'Acknowledged' || request.handoff?.tracking_reference) return 3
  if (vKey === 'verified' || vKey === 'approved') return 2
  return 1
}

function statusTone(status) {
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

function formatQuantityText(quantity, unit, fallbackText) {
  const qty = Number(quantity) || (fallbackText ? parseInt(fallbackText, 10) : 1) || 1
  let rawUnit = unit
  if (!rawUnit && fallbackText) {
    rawUnit = fallbackText.replace(/^[0-9.]+\s*/, '')
  }
  rawUnit = (rawUnit || 'units').trim().toLowerCase()

  if (qty === 1) {
    if (rawUnit === 'kits' || rawUnit === 'kit') return '1 kit'
    if (rawUnit === 'boxes' || rawUnit === 'box') return '1 box'
    if (rawUnit === 'packs' || rawUnit === 'pack') return '1 pack'
    if (rawUnit === 'pieces' || rawUnit === 'pcs' || rawUnit === 'piece') return '1 piece'
    if (rawUnit === 'units' || rawUnit === 'unit') return '1 unit'
    if (rawUnit.endsWith('s') && !rawUnit.endsWith('ss')) return `1 ${rawUnit.slice(0, -1)}`
    return `1 ${rawUnit}`
  }

  // Plural
  if (rawUnit === 'kit') return `${qty} kits`
  if (rawUnit === 'box') return `${qty} boxes`
  if (rawUnit === 'pack') return `${qty} packs`
  if (rawUnit === 'piece') return `${qty} pieces`
  if (rawUnit === 'unit') return `${qty} units`
  if (!rawUnit.endsWith('s')) return `${qty} ${rawUnit}s`
  return `${qty} ${rawUnit}`
}
