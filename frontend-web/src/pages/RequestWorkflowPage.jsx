import { useCallback, useMemo, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import {
  Activity,
  AlertCircle,
  Building2,
  CheckCircle2,
  Clock,
  Clock3,
  Edit3,
  Eye,
  Flame,
  Inbox,
  Layers,
  MapPin,
  PackageSearch,
  Radio,
  RotateCw,
  Search,
  ShieldCheck,
  Smartphone,
  Truck,
  User,
  X,
} from 'lucide-react'
import {
  getResourceRequests,
  validateResourceRequest,
  forwardResourceRequest,
} from '../api/resourceRequestApi'
import {
  buildValidationPayload,
  buildForwardPayload,
} from '../utils/resourceRequestHelpers'
import { useModuleData } from '../utils/useModuleData'
import PaginationBar from '../components/ui/PaginationBar'
import TrackingAidMirror from '../components/resources/TrackingAidMirror'
import ResourceRequestDetailDrawer from '../components/resources/ResourceRequestDetailDrawer'

export default function RequestWorkflowPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const external = location.pathname === '/external-requests'

  // Filter States
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [source, setSource] = useState('all')
  const [status, setStatus] = useState('all')
  const [urgency, setUrgency] = useState('all')

  // Live Tracking Drawer State (Option 1)
  const [inspectingRequest, setInspectingRequest] = useState(null)
  const [isProcessing, setIsProcessing] = useState(false)

  const loader = useCallback(
    () =>
      getResourceRequests({
        page,
        per_page: 20,
        search,
        status,
        source: source === 'all' ? undefined : source,
        workflow: external ? 'approval' : 'monitoring',
      }),
    [page, search, status, source, external],
  )

  const { data, error, loading, refresh } = useModuleData(loader)

  // Navigate to editor/viewer
  const open = (row, mode) =>
    navigate(`/resources-requests/${row.request_id}/${mode}`, {
      state: { returnTo: location.pathname },
    })

  // Drawer Action Handlers (Validate & Forward to TrackingAid Shared DB)
  const handleValidateAndForwardFromDrawer = async (req) => {
    setIsProcessing(true)
    try {
      await validateResourceRequest(
        req.request_id,
        buildValidationPayload({ validation_status: 'verified', validation_notes: 'Verified via Tracking Drawer' })
      )
      await forwardResourceRequest(
        req.request_id,
        buildForwardPayload({ forward_notes: 'Forwarded to TrackingAid via Tracking Drawer' })
      )
      setInspectingRequest(null)
      refresh()
    } catch (err) {
      console.error(err)
    } finally {
      setIsProcessing(false)
    }
  }

  const handleReturnFromDrawer = (req) => {
    setInspectingRequest(null)
    open(req, 'return')
  }

  // Summary counts
  const summary = data?.summary || {}
  const rawRequests = data?.requests?.data || []
  const totalCount = data?.requests?.total || rawRequests.length

  // Client-side urgency filtering
  const filteredRequests = useMemo(() => {
    if (urgency === 'all') return rawRequests
    return rawRequests.filter((r) => r.urgency?.key === urgency)
  }, [rawRequests, urgency])

  const hasActiveFilters = search || source !== 'all' || status !== 'all' || urgency !== 'all'

  const handleResetFilters = () => {
    setSearch('')
    setSource('all')
    setStatus('all')
    setUrgency('all')
    setPage(1)
  }

  return (
    <main className="ops-page resources-page">
      <div className="workflow-page-wrap">
        {/* Operations Hero Header */}
        <header className="workflow-hero-header">
          <div className="workflow-hero-left">
            <div className="workflow-hero-icon-box">
              {external ? <Inbox size={22} /> : <Activity size={22} />}
            </div>
            <div>
              <div className="workflow-hero-kicker">
                {external ? 'Relief Operations & Dispatch Intake' : 'Logistics Monitoring & Forwarding'}
              </div>
              <h1 className="workflow-hero-title">
                {external ? 'External Requests' : 'Request Monitoring'}
              </h1>
              <p className="workflow-hero-desc">
                {external
                  ? 'Verify and review inbound resource requests from EvaTrack, field teams, and evacuation sites before approval.'
                  : 'Track live fulfillment, TrackingAid warehouse handoffs, and distribution status for approved relief supplies.'}
              </p>
            </div>
          </div>

          <div className="workflow-hero-actions">
            {data?.active_event && (
              <div className="workflow-event-pill">
                <Flame size={13} />
                <span>Event: {data.active_event.name}</span>
              </div>
            )}
            <button
              type="button"
              className="btn btn-secondary"
              onClick={refresh}
              disabled={loading}
              style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
            >
              <RotateCw size={14} className={loading ? 'spin-icon' : ''} />
              <span>{loading ? 'Refreshing...' : 'Refresh'}</span>
            </button>
          </div>
        </header>

        {/* Executive KPI Stat Cards */}
        <section className="workflow-kpi-grid" aria-label="Workflow Metrics">
          {/* Card 1: Pending Verification */}
          <div className="workflow-kpi-card kpi-amber">
            <div className="workflow-kpi-icon">
              <Clock3 size={19} />
            </div>
            <div className="workflow-kpi-info">
              <span className="workflow-kpi-num">
                {Number(summary.needs_validation ?? 0).toLocaleString()}
              </span>
              <span className="workflow-kpi-label">Pending Approval</span>
              <span className="workflow-kpi-sub">Awaiting verification</span>
            </div>
          </div>

          {/* Card 2: Approved / Validated */}
          <div className="workflow-kpi-card kpi-emerald">
            <div className="workflow-kpi-icon">
              <CheckCircle2 size={19} />
            </div>
            <div className="workflow-kpi-info">
              <span className="workflow-kpi-num">
                {Number(summary.verified ?? 0).toLocaleString()}
              </span>
              <span className="workflow-kpi-label">Approved Requests</span>
              <span className="workflow-kpi-sub">Ready for allocation</span>
            </div>
          </div>

          {/* Card 3: Forwarded / Dispatched */}
          <div className="workflow-kpi-card kpi-sky">
            <div className="workflow-kpi-icon">
              <Truck size={19} />
            </div>
            <div className="workflow-kpi-info">
              <span className="workflow-kpi-num">
                {Number(summary.forwarded_today ?? 0).toLocaleString()}
              </span>
              <span className="workflow-kpi-label">Forwarded Today</span>
              <span className="workflow-kpi-sub">Active field handoffs</span>
            </div>
          </div>

          {/* Card 4: Total Incident Intake */}
          <div className="workflow-kpi-card kpi-indigo">
            <div className="workflow-kpi-icon">
              <Layers size={19} />
            </div>
            <div className="workflow-kpi-info">
              <span className="workflow-kpi-num">
                {Number(summary.total_requests ?? totalCount).toLocaleString()}
              </span>
              <span className="workflow-kpi-label">Total Volume</span>
              <span className="workflow-kpi-sub">All logged requests</span>
            </div>
          </div>
        </section>

        {/* Unified Filter & Search Toolbar */}
        <section className="workflow-toolbar-box" aria-label="Search and Filter Controls">
          <div className="workflow-toolbar-top">
            {/* Search Input */}
            <div className="workflow-search-wrap">
              <Search size={15} className="workflow-search-icon" />
              <input
                type="text"
                className="workflow-search-input"
                placeholder="Search by request ID, item, requester, or evacuation site..."
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value)
                  setPage(1)
                }}
              />
              {search && (
                <button
                  type="button"
                  className="workflow-search-clear"
                  onClick={() => {
                    setSearch('')
                    setPage(1)
                  }}
                  aria-label="Clear search"
                >
                  <X size={12} />
                </button>
              )}
            </div>

            {/* Source Channel Pills */}
            <div className="workflow-channel-pills">
              <button
                type="button"
                className={`wf-channel-btn ${source === 'all' ? 'active' : ''}`}
                onClick={() => {
                  setSource('all')
                  setPage(1)
                }}
              >
                All Sources
              </button>
              <button
                type="button"
                className={`wf-channel-btn ${source === 'evatrack' || source === 'shared_db' ? 'active' : ''}`}
                onClick={() => {
                  setSource('evatrack')
                  setPage(1)
                }}
              >
                <Smartphone size={12} /> EvaTrack
              </button>
              <button
                type="button"
                className={`wf-channel-btn ${source === 'hq_desk' ? 'active' : ''}`}
                onClick={() => {
                  setSource('hq_desk')
                  setPage(1)
                }}
              >
                <Building2 size={12} /> HQ Desk
              </button>
              <button
                type="button"
                className={`wf-channel-btn ${source === 'field_team' ? 'active' : ''}`}
                onClick={() => {
                  setSource('field_team')
                  setPage(1)
                }}
              >
                <Radio size={12} /> Field Teams
              </button>
            </div>
          </div>

          {/* Secondary Controls Row */}
          <div className="workflow-toolbar-bottom">
            <div className="workflow-filter-selects">
              {/* Urgency Filter */}
              <label className="workflow-filter-label">
                <span>Priority:</span>
                <select
                  className="workflow-filter-select"
                  value={urgency}
                  onChange={(e) => setUrgency(e.target.value)}
                >
                  <option value="all">All Priorities</option>
                  <option value="critical">Critical</option>
                  <option value="high">High Priority</option>
                  <option value="medium">Medium Priority</option>
                  <option value="low">Low Priority</option>
                </select>
              </label>

              {/* Status Filter (especially on monitoring) */}
              {!external && (
                <label className="workflow-filter-label">
                  <span>Status:</span>
                  <select
                    className="workflow-filter-select"
                    value={status}
                    onChange={(e) => {
                      setStatus(e.target.value)
                      setPage(1)
                    }}
                  >
                    <option value="all">All Approved Records</option>
                    <option value="verified">Validated</option>
                    <option value="forwarded">In Progress</option>
                    <option value="fulfilled">Completed</option>
                  </select>
                </label>
              )}
            </div>

            {hasActiveFilters && (
              <button
                type="button"
                className="workflow-reset-btn"
                onClick={handleResetFilters}
              >
                <X size={12} /> Reset filters
              </button>
            )}
          </div>
        </section>

        {/* Modern Workflow Data Table Card */}
        <section className="workflow-table-card" aria-label="Requests Table">
          <div className="workflow-table-card-head">
            <div className="workflow-table-title-wrap">
              <h2 className="workflow-table-card-title">
                {external ? 'Requests Awaiting Approval' : 'Active Request Fulfillment Stream'}
              </h2>
              <span className="workflow-count-badge">
                {filteredRequests.length} of {totalCount} requests
              </span>
            </div>
          </div>

          {error && (
            <div className="form-error-banner" role="alert" style={{ margin: '14px 18px' }}>
              <AlertCircle size={15} />
              <span>{error}</span>
            </div>
          )}

          <div className="workflow-table-scroll">
            <table className="workflow-table">
              <thead>
                <tr>
                  <th>Request ID</th>
                  <th>Origin & Requester</th>
                  <th>Resource Need</th>
                  <th>Quantity</th>
                  <th>Evacuation Area</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Tracking</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading && filteredRequests.length === 0 ? (
                  <tr>
                    <td colSpan={9} style={{ textAlign: 'center', padding: '40px 16px' }}>
                      <RotateCw size={24} className="spin-icon" style={{ margin: '0 auto 8px', color: '#3b82f6' }} />
                      <div style={{ fontSize: '13px', color: '#64748b', fontWeight: 600 }}>Loading requests...</div>
                    </td>
                  </tr>
                ) : filteredRequests.length === 0 ? (
                  <tr>
                    <td colSpan={9} style={{ textAlign: 'center', padding: '48px 16px' }}>
                      <PackageSearch size={36} style={{ margin: '0 auto 10px', color: '#94a3b8' }} />
                      <div style={{ fontSize: '14.5px', fontWeight: 750, color: '#1e293b' }}>
                        No matching requests found
                      </div>
                      <p style={{ fontSize: '12px', color: '#64748b', margin: '4px 0 12px' }}>
                        {hasActiveFilters
                          ? 'Try adjusting your search keywords or resetting your active filters.'
                          : 'No inbound requests are currently pending review.'}
                      </p>
                      {hasActiveFilters && (
                        <button
                          type="button"
                          className="btn btn-secondary btn-sm"
                          onClick={handleResetFilters}
                        >
                          Clear active filters
                        </button>
                      )}
                    </td>
                  </tr>
                ) : (
                  filteredRequests.map((row) => {
                    const srcKey = row.request_source?.key || 'shared_db'
                    const urgencyKey = row.urgency?.key || 'medium'
                    const statusKey = row.validation?.key || 'needs_validation'
                    const isPending = statusKey === 'needs_validation'
                    const isVerified = ['verified', 'validated'].includes(statusKey)
                    const isForwarded = statusKey === 'forwarded'
                    const isCompleted = statusKey === 'fulfilled'

                    return (
                      <tr key={row.request_id}>
                        {/* Request ID & Timestamp */}
                        <td>
                          <span className="workflow-id-badge">{row.request_id}</span>
                          <div className="workflow-sub-meta">
                            <Clock size={10} />
                            <span>{row.created_at || 'Recently submitted'}</span>
                          </div>
                        </td>

                        {/* Origin Source & Requester */}
                        <td>
                          <div style={{ display: 'flex', flexDirection: 'column', gap: '3px' }}>
                            <span
                              className={`channel-chip ${
                                srcKey === 'evatrack' || srcKey === 'shared_db'
                                  ? 'evatrack'
                                  : srcKey === 'hq_desk'
                                  ? 'hq'
                                  : 'field'
                              }`}
                            >
                              {srcKey === 'evatrack' || srcKey === 'shared_db' ? (
                                <Smartphone size={10} />
                              ) : srcKey === 'hq_desk' ? (
                                <Building2 size={10} />
                              ) : (
                                <Radio size={10} />
                              )}
                              <span>{row.request_source?.label || 'EvaTrack'}</span>
                            </span>
                            <div className="workflow-sub-meta">
                              <User size={10} />
                              <span>{row.requested_by || 'Disaster Staff'}</span>
                            </div>
                          </div>
                        </td>

                        {/* Resource Item Name */}
                        <td>
                          <div className="workflow-item-title">
                            {row.need?.item_name || row.need?.type || 'Resource Request'}
                          </div>
                          {row.need?.item_name && row.need?.type && (
                            <span style={{ fontSize: '11px', color: '#64748b' }}>
                              {row.need.type}
                            </span>
                          )}
                        </td>

                        {/* Quantity Pill */}
                        <td>
                          <span className="workflow-qty-pill">
                            {row.need?.quantity_text || `${row.need?.quantity || 1} units`}
                          </span>
                        </td>

                        {/* Destination Evacuation Center */}
                        <td>
                          <div
                            className="workflow-center-name"
                            style={{ cursor: 'pointer' }}
                            onClick={() => setInspectingRequest(row)}
                            title="Click to track destination and handoff"
                          >
                            {row.area?.label || 'General Relief Hub'}
                          </div>
                          {row.area?.meta && (
                            <div className="workflow-center-sub">
                              <MapPin size={10} style={{ flexShrink: 0 }} />
                              <span>
                                {row.area.meta.length > 40
                                  ? `${row.area.meta.slice(0, 38)}...`
                                  : row.area.meta}
                              </span>
                            </div>
                          )}
                        </td>

                        {/* Urgency Badge */}
                        <td>
                          <span className={`urgency-tag urgency-${urgencyKey}`}>
                            {urgencyKey === 'critical' ? (
                              <Flame size={11} />
                            ) : urgencyKey === 'high' ? (
                              <AlertCircle size={11} />
                            ) : urgencyKey === 'low' ? (
                              <ShieldCheck size={11} />
                            ) : (
                              <Clock size={11} />
                            )}
                            <span>{row.urgency?.label || 'Medium'}</span>
                          </span>
                        </td>

                        {/* Status Badge */}
                        <td>
                          <span
                            className={`wf-status-badge ${
                              isPending
                                ? 'wf-status-pending'
                                : isVerified
                                ? 'wf-status-approved'
                                : isForwarded
                                ? 'wf-status-forwarded'
                                : 'wf-status-fulfilled'
                            }`}
                          >
                            <span className={`wf-status-dot ${isPending ? 'pulse' : ''}`} />
                            <span>
                              {isPending
                                ? 'Pending Approval'
                                : isVerified
                                ? 'Approved'
                                : isForwarded
                                ? 'In Progress'
                                : isCompleted
                                ? 'Fulfilled'
                                : row.status?.label || 'Pending'}
                            </span>
                          </span>
                        </td>

                        {/* Tracking / Handoff */}
                        <td>
                          <div
                            style={{ fontSize: '11.5px', fontWeight: 650, color: '#334155', cursor: 'pointer' }}
                            onClick={() => setInspectingRequest(row)}
                            title="Click to inspect live tracking and handoff status"
                          >
                            {row.handoff?.label || 'Awaiting handoff'}
                          </div>
                          {row.handoff?.tracking_reference && (
                            <div className="wf-tracking-sub">
                              Ref: {row.handoff.tracking_reference}
                            </div>
                          )}
                        </td>

                        {/* Action Buttons */}
                        <td style={{ textAlign: 'right' }}>
                          <div className="workflow-action-cell" style={{ justifyContent: 'flex-end' }}>
                            <button
                              type="button"
                              className="btn-wf-action btn-wf-track"
                              onClick={() => setInspectingRequest(row)}
                              title="Track destination and TrackingAid handoff status"
                            >
                              <Truck size={12} />
                              <span>Track Handoff</span>
                            </button>
                            <button
                              type="button"
                              className="btn-wf-action btn-wf-view"
                              onClick={() => open(row, 'view')}
                              title="View request details"
                            >
                              <Eye size={12} />
                              <span>View Request</span>
                            </button>
                            {external && (
                              <button
                                type="button"
                                className="btn-wf-action btn-wf-edit"
                                onClick={() => open(row, 'validate')}
                                title="Review and approve request"
                              >
                                <CheckCircle2 size={12} />
                                <span>Approve request</span>
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    )
                  })
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          <div style={{ padding: '8px 18px', borderTop: '1px solid #f1f5f9' }}>
            <PaginationBar meta={data?.requests || {}} onPageChange={setPage} label="requests" />
          </div>
        </section>

        {/* TrackingAid Mirror for Monitoring */}
        {!external && <TrackingAidMirror items={data?.tracking_mirror || []} />}

        {/* Live Tracking & Handoff Inspection Drawer (Option 1) */}
        <ResourceRequestDetailDrawer
          request={inspectingRequest}
          isOpen={Boolean(inspectingRequest)}
          onClose={() => setInspectingRequest(null)}
          onValidateAndForward={handleValidateAndForwardFromDrawer}
          onReturn={handleReturnFromDrawer}
          onEdit={(req) => open(req, 'edit')}
          isProcessing={isProcessing}
        />
      </div>
    </main>
  )
}
