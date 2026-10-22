import { useCallback, useMemo, useState } from 'react'
import { useLocation, useNavigate, useOutletContext } from 'react-router-dom'
import {
  Box,
  CheckCircle2,
  Clock,
  Eye,
  MapPin,
  Plus,
  RotateCw,
  Truck,
  User,
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
import PageHeader from '../components/ui/PageHeader'
import DataFilterBar from '../components/ui/DataFilterBar'
import PaginationBar from '../components/ui/PaginationBar'
import Badge from '../components/ui/Badge'
import EmptyState from '../components/ui/EmptyState'
import LoadingState from '../components/ui/LoadingState'
import TrackingAidMirror from '../components/resources/TrackingAidMirror'
import { canApproveRequests } from '../utils/personnelAccess'
import ResourceRequestDetailDrawer from '../components/resources/ResourceRequestDetailDrawer'

// Format quantity and unit matching Request Types inventory UI
function getQuantityParts(quantity, unit, fallbackText) {
  const qty = Number(quantity) || (fallbackText ? parseInt(fallbackText, 10) : 1) || 1
  let rawUnit = unit
  if (!rawUnit && fallbackText) {
    rawUnit = fallbackText.replace(/^[0-9.]+\s*/, '')
  }
  rawUnit = (rawUnit || 'units').trim().toLowerCase()

  if (qty === 1) {
    if (rawUnit === 'kits' || rawUnit === 'kit') return { qty: 1, unit: 'kit' }
    if (rawUnit === 'boxes' || rawUnit === 'box') return { qty: 1, unit: 'box' }
    if (rawUnit === 'packs' || rawUnit === 'pack') return { qty: 1, unit: 'pack' }
    if (rawUnit === 'pieces' || rawUnit === 'pcs' || rawUnit === 'piece') return { qty: 1, unit: 'piece' }
    if (rawUnit === 'units' || rawUnit === 'unit') return { qty: 1, unit: 'unit' }
    if (rawUnit.endsWith('s') && !rawUnit.endsWith('ss')) return { qty: 1, unit: rawUnit.slice(0, -1) }
    return { qty: 1, unit: rawUnit }
  }

  // Plural
  if (rawUnit === 'kit') return { qty, unit: 'kits' }
  if (rawUnit === 'box') return { qty, unit: 'boxes' }
  if (rawUnit === 'pack') return { qty, unit: 'packs' }
  if (rawUnit === 'piece') return { qty, unit: 'pieces' }
  if (rawUnit === 'unit') return { qty, unit: 'units' }
  if (!rawUnit.endsWith('s')) return { qty, unit: `${rawUnit}s` }
  return { qty, unit: rawUnit }
}

// Origin source badge helper
function getSourceBadge(source) {
  const key = (source?.key || '').toLowerCase()
  const rawLabel = (source?.label || '').toLowerCase()

  if (key.includes('mobile') || rawLabel.includes('mobile') || rawLabel.includes('rescuer')) {
    return { className: 'channel-chip mobile', label: 'Rescuer Mobile' }
  }
  if (key.includes('evatrack') || rawLabel.includes('evatrack') || rawLabel.includes('center')) {
    return { className: 'channel-chip evatrack', label: 'EvaTrack Center' }
  }
  if (key.includes('field') || rawLabel.includes('field')) {
    return { className: 'channel-chip field', label: 'Field Unit' }
  }
  return { className: 'channel-chip hq', label: 'HQ Desk' }
}

// Evacuation area formatter helper
function formatEvacuationArea(area) {
  const rawLabel = area?.label || ''
  const meta = area?.meta || ''
  const isUnrecorded = !rawLabel || rawLabel.toLowerCase() === 'area not recorded'

  if (isUnrecorded) {
    if (meta.toLowerCase().startsWith('location:')) {
      return {
        title: meta.replace(/^location:\s*/i, '').trim(),
        subtitle: 'Field Location Dispatch',
      }
    }
    return {
      title: 'Unassigned Site',
      subtitle: meta || 'Central Depot Intake',
    }
  }

  return {
    title: rawLabel,
    subtitle: meta || 'Registered Evacuation Center',
  }
}

export default function RequestWorkflowPage() {
  const { user } = useOutletContext()
  const canApprove = canApproveRequests(user)
  const location = useLocation()
  const navigate = useNavigate()
  const external = location.pathname === '/external-requests'

  // Filter States
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [source, setSource] = useState('all')
  const [status, setStatus] = useState('all')
  const [urgency, setUrgency] = useState('all')

  // Live Tracking Drawer & Action States
  const [inspectingRequest, setInspectingRequest] = useState(null)
  const [drawerMode, setDrawerMode] = useState('view') // 'view' | 'tracking'
  const [isProcessing, setIsProcessing] = useState(false)
  const [successToast, setSuccessToast] = useState(null)

  const handleViewRequest = (row) => {
    setDrawerMode('view')
    setInspectingRequest(row)
  }

  const handleTrackLive = (row) => {
    setDrawerMode('tracking')
    setInspectingRequest(row)
  }

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

  // Direct Approve Handler (Approve Request action from row)
  const handleApproveRequest = async (req) => {
    const itemDesc = req.need?.type || req.need?.item_name || 'Relief Supplies'
    const qtyDesc = req.need?.quantity_text || `${req.need?.quantity || 1} units`
    const confirmed = window.confirm(
      `Approve and allocate Request #${req.request_id} (${qtyDesc} of ${itemDesc}) for ${req.area?.label || 'Evacuation Center'}? Supplies will be allocated from warehouse bays and synchronized to TrackingAid.`
    )
    if (!confirmed) return

    setIsProcessing(true)
    try {
      await validateResourceRequest(
        req.request_id,
        buildValidationPayload({
          validation_status: 'verified',
          validation_notes: 'Approved from For Approval Request List',
        })
      )
      await forwardResourceRequest(
        req.request_id,
        buildForwardPayload({
          forward_notes: 'Forwarded to TrackingAid Shared DB via For Approval Request List',
        })
      )
      setSuccessToast(`Request #${req.request_id} has been approved and forwarded to TrackingAid Shared DB!`)
      setTimeout(() => setSuccessToast(null), 5000)
      refresh()
    } catch (err) {
      console.error(err)
      alert(err.message || 'Failed to approve request. Please verify warehouse stock.')
    } finally {
      setIsProcessing(false)
    }
  }

  // Drawer Action Handlers (Validate & Forward to TrackingAid Shared DB)
  const handleValidateAndForwardFromDrawer = async (req) => {
    setIsProcessing(true)
    try {
      await validateResourceRequest(
        req.request_id,
        buildValidationPayload({ validation_status: 'verified', validation_notes: 'Verified via Inspection Drawer' })
      )
      await forwardResourceRequest(
        req.request_id,
        buildForwardPayload({ forward_notes: 'Forwarded to TrackingAid via Inspection Drawer' })
      )
      setInspectingRequest(null)
      setSuccessToast(`Request #${req.request_id} successfully approved and forwarded to TrackingAid Shared DB!`)
      setTimeout(() => setSuccessToast(null), 5000)
      refresh()
    } catch (err) {
      console.error(err)
      alert(err.message || 'Failed to approve request.')
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
  const rawRequests = data?.requests?.data
  const totalCount = data?.requests?.total ?? rawRequests?.length ?? 0

  // Client-side urgency filtering
  const filteredRequests = useMemo(() => {
    const requests = rawRequests || []
    if (urgency === 'all') return requests
    return requests.filter((r) => r.urgency?.key === urgency)
  }, [rawRequests, urgency])

  const hasActiveFilters = search || source !== 'all' || status !== 'all' || urgency !== 'all'

  const handleResetFilters = () => {
    setSearch('')
    setSource('all')
    setStatus('all')
    setUrgency('all')
    setPage(1)
  }

  const filtersConfig = [
    ...(external ? [{
      id: 'source',
      label: 'Source',
      value: source,
      onChange: (value) => { setSource(value); setPage(1) },
      options: [
        { value: 'all', label: 'All sources' },
        { value: 'evatrack', label: 'EvaTrack' },
        { value: 'hq_desk', label: 'HQ Desk' },
        { value: 'field_team', label: 'Field Teams' },
      ],
    }] : []),
    {
      id: 'priority',
      label: 'Priority',
      value: urgency,
      onChange: (value) => { setUrgency(value); setPage(1) },
      options: [
        { value: 'all', label: 'All priorities' },
        { value: 'critical', label: 'Critical' },
        { value: 'high', label: 'High' },
        { value: 'medium', label: 'Medium' },
        { value: 'low', label: 'Low' },
      ],
    },
    ...(!external ? [{
      id: 'status',
      label: 'Status',
      value: status,
      onChange: (value) => { setStatus(value); setPage(1) },
      options: [
        { value: 'all', label: 'All approved' },
        { value: 'verified', label: 'Validated / Staged' },
        { value: 'forwarded', label: 'In Transit' },
        { value: 'fulfilled', label: 'Completed' },
      ],
    }] : []),
  ]

  return (
    <main className="ops-page resources-page">
      {/* ResQperation Native Page Header */}
      <PageHeader
        title={external ? 'External Requests' : 'Request Monitoring'}
        subtitle={data?.active_event?.name || (external ? 'Inbound relief requests for command review' : 'Active relief fulfillment and handoff tracking')}
        actions={
          <div className="pg-actions">
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
            {!external && (
              <button
                type="button"
                className="button review"
                onClick={() => navigate('/resources-requests/new')}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
              >
                <Plus size={15} />
                <span>New Request</span>
              </button>
            )}
          </div>
        }
      />

      {/* Standby strip if no active disaster event */}
      {!loading && !error && !data?.active_event && (
        <div className="standby-strip">
          <strong>No active disaster event</strong>
          <span>Requests will be tracked against general relief inventory and depot reserves.</span>
        </div>
      )}

      {/* Success Notification Banner */}
      {successToast && (
        <div className="ra-success" role="status">
          <CheckCircle2 size={16} style={{ color: '#16a34a', flexShrink: 0 }} />
          <span>{successToast}</span>
        </div>
      )}

      {error && <div className="form-error" role="alert">{error}</div>}

      {/* ResQperation Submodule Segmented Tabs (Native account-management-tabs) */}
      {!external && (
        <div className="account-management-tabs" role="tablist" aria-label="Request monitoring categories">
          <button
            type="button"
            role="tab"
            aria-selected={status === 'all'}
            className={status === 'all' ? 'active' : ''}
            onClick={() => { setStatus('all'); setPage(1) }}
          >
            Approved Request List
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={status === 'forwarded'}
            className={status === 'forwarded' ? 'active' : ''}
            onClick={() => { setStatus('forwarded'); setPage(1) }}
          >
            Status Tracking of Requests
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={status === 'fulfilled'}
            className={status === 'fulfilled' ? 'active' : ''}
            onClick={() => { setStatus('fulfilled'); setPage(1) }}
          >
            Completed Requests
          </button>
        </div>
      )}

      {/* Native ResQperation KPI Stat Row (.rr-stat-row) */}
      <div className="rr-stat-row">
        <div className="rr-stat">
          <div className="k">{external ? 'Pending Approval' : 'Approved Requests'}</div>
          <div className="v">{Number(external ? summary.needs_validation ?? 0 : summary.verified ?? 0).toLocaleString()}</div>
          <div className="n">{external ? 'awaiting verification' : 'ready for allocation'}</div>
        </div>
        <div className="rr-stat">
          <div className="k">{external ? 'Approved Today' : 'In Transit'}</div>
          <div className="v">{Number(external ? summary.verified ?? 0 : summary.forwarded_today ?? 0).toLocaleString()}</div>
          <div className="n">{external ? 'validated by command' : 'active dispatch tracking'}</div>
        </div>
        <div className="rr-stat">
          <div className="k">{external ? 'Forwarded to Depot' : 'Completed Requests'}</div>
          <div className="v">
            {Number(
              external
                ? summary.forwarded_today ?? 0
                : summary.fulfilled ?? summary.delivered ?? 0
            ).toLocaleString()}
          </div>
          <div className="n">{external ? 'warehouse handoffs' : 'delivered to centers'}</div>
        </div>
        <div className="rr-stat">
          <div className="k">{external ? 'Total Inbound' : 'Total Monitored'}</div>
          <div className="v">{Number(summary.total_requests ?? totalCount).toLocaleString()}</div>
          <div className="n">{external ? 'all logged intake requests' : 'active relief stream'}</div>
        </div>
      </div>

      {/* Shared TanStack DataFilterBar */}
      <DataFilterBar
        search={search}
        onSearchChange={(value) => { setSearch(value); setPage(1) }}
        searchPlaceholder={external ? "Search by request ID, item, requester or evacuation site..." : "Search by request ID, item, or center..."}
        filters={filtersConfig}
        onReset={handleResetFilters}
      />

      {/* Main Content: Native ResQperation Panel matching Request Types UI */}
      <section className="panel" aria-label={external ? 'For Approval Request List' : 'Requests Stream'}>
        <div className="panel-head">
          <span className="panel-title">
            {external
              ? 'For Approval Request List'
              : status === 'forwarded'
              ? 'Status Tracking of Requests'
              : status === 'fulfilled'
              ? 'Completed Requests'
              : 'Approved Request List'}
          </span>
          <span className="rr-subtle">
            {filteredRequests.length} of {totalCount} records
          </span>
        </div>

        <div className="tbl-wrap compact-table">
          <table>
            <thead>
              <tr>
                <th>Request</th>
                <th>Origin</th>
                <th>Resource Need</th>
                <th>Quantity</th>
                <th>Evacuation Area</th>
                <th>Priority</th>
                <th>Status</th>
                {!external && <th>Tracking Reference</th>}
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading && filteredRequests.length === 0 ? (
                <tr>
                  <td colSpan={external ? 8 : 9} style={{ textAlign: 'center', padding: '36px 16px' }}>
                    <LoadingState label="Loading requests..." />
                  </td>
                </tr>
              ) : filteredRequests.length === 0 ? (
                <tr>
                  <td colSpan={external ? 8 : 9} style={{ textAlign: 'center', padding: '36px 16px' }}>
                    <EmptyState
                      title="No resource requests found"
                      message={
                        hasActiveFilters
                          ? 'No requests match your current search and filter criteria.'
                          : external
                          ? 'No external requests currently require approval.'
                          : 'No requests found in this monitoring status.'
                      }
                    />
                  </td>
                </tr>
              ) : (
                filteredRequests.map((row) => {
                  const urgencyKey = row.urgency?.key || 'medium'
                  const statusKey = row.validation?.key || 'needs_validation'
                  const isCompleted = statusKey === 'fulfilled'
                  const sourceBadge = getSourceBadge(row.request_source)
                  const areaInfo = formatEvacuationArea(row.area)
                  const qtyParts = getQuantityParts(row.need?.quantity, row.need?.unit, row.need?.quantity_text)

                  return (
                    <tr key={row.request_id}>
                      {/* Request ID & Timestamp */}
                      <td>
                        <strong style={{ fontFamily: 'monospace', color: 'var(--color-text-1)', fontSize: '13px', whiteSpace: 'nowrap' }}>
                          #{row.request_id}
                        </strong>
                        <div style={{ fontSize: '11px', color: 'var(--color-text-3)', display: 'flex', alignItems: 'center', gap: '4px', marginTop: '2px', whiteSpace: 'nowrap' }}>
                          <Clock size={10} />
                          <span>{row.created_at || 'Recently submitted'}</span>
                        </div>
                      </td>

                      {/* Origin & Requester */}
                      <td>
                        <div style={{ fontWeight: 600, color: 'var(--color-text-2)', fontSize: '12.5px', whiteSpace: 'nowrap' }}>
                          {sourceBadge.label}
                        </div>
                        <div style={{ fontSize: '11px', color: 'var(--color-text-3)', display: 'flex', alignItems: 'center', gap: '4px', marginTop: '2px', whiteSpace: 'nowrap' }}>
                          <User size={10} />
                          <span>{row.requested_by || 'Disaster Staff'}</span>
                        </div>
                      </td>

                      {/* Resource Need */}
                      <td>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                          <div
                            className="card-category-icon-box"
                            style={{ width: '28px', height: '28px', borderRadius: '6px', flexShrink: 0 }}
                          >
                            <Box size={15} strokeWidth={2.2} />
                          </div>
                          <div>
                            <div style={{ fontWeight: 650, color: 'var(--color-text-1)', fontSize: '13px', whiteSpace: 'nowrap' }}>
                              {row.need?.item_name || row.need?.type || 'Supplies'}
                            </div>
                            {row.need?.type && row.need?.item_name && (
                              <div style={{ fontSize: '11px', color: 'var(--color-text-3)', whiteSpace: 'nowrap' }}>
                                {row.need.type}
                              </div>
                            )}
                          </div>
                        </div>
                      </td>

                      {/* Quantity */}
                      <td>
                        <div style={{ display: 'flex', alignItems: 'baseline', gap: '4px', whiteSpace: 'nowrap' }}>
                          <strong style={{ fontSize: '14px', color: 'var(--color-text-1)' }}>
                            {qtyParts.qty}
                          </strong>
                          <span style={{ fontSize: '11px', color: 'var(--color-text-3)' }}>
                            {qtyParts.unit}
                          </span>
                        </div>
                      </td>

                      {/* Evacuation Area */}
                      <td>
                        <div style={{ fontWeight: 600, color: 'var(--color-text-2)', fontSize: '12.5px' }}>
                          {areaInfo.title}
                        </div>
                        {areaInfo.subtitle && (
                          <div style={{ fontSize: '11px', color: 'var(--color-text-3)', display: 'flex', alignItems: 'center', gap: '3px', marginTop: '2px' }}>
                            <MapPin size={10} style={{ flexShrink: 0 }} />
                            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '180px' }} title={areaInfo.subtitle}>
                              {areaInfo.subtitle}
                            </span>
                          </div>
                        )}
                      </td>

                      {/* Priority */}
                      <td>
                        <span className={`urgency-tag urgency-${urgencyKey}`}>
                          {row.urgency?.label || 'Medium'}
                        </span>
                      </td>

                      {/* Status */}
                      <td>
                        <Badge tone={row.validation?.tone || 'amber'}>
                          {row.validation?.label || row.status?.label || 'Pending'}
                        </Badge>
                      </td>

                      {/* Tracking Reference (Monitoring only) */}
                      {!external && (
                        <td>
                          <div
                            style={{ fontSize: '12px', fontWeight: 600, color: 'var(--color-text-2)', cursor: 'pointer', whiteSpace: 'nowrap' }}
                            onClick={() => handleTrackLive(row)}
                            title="Inspect live tracking"
                          >
                            {row.handoff?.label || 'Awaiting handoff'}
                          </div>
                          {row.handoff?.tracking_reference && (
                            <div style={{ fontSize: '10.5px', color: '#0284c7', fontFamily: 'monospace' }}>
                              Ref: {row.handoff.tracking_reference}
                            </div>
                          )}
                        </td>
                      )}

                      {/* Actions */}
                      <td style={{ textAlign: 'right' }}>
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', justifyContent: 'flex-end', whiteSpace: 'nowrap' }}>
                          {external ? (
                            <>
                              <button
                                type="button"
                                className="button secondary"
                                style={{ padding: '5px 10px', fontSize: '11.5px' }}
                                onClick={() => handleViewRequest(row)}
                                title="View Request"
                              >
                                <Eye size={12} />
                                <span>View Request</span>
                              </button>
                              {canApprove && (
                                <button
                                  type="button"
                                  className="button review"
                                  style={{ padding: '5px 12px', fontSize: '11.5px' }}
                                  onClick={() => handleApproveRequest(row)}
                                  disabled={isProcessing}
                                  title="Approve Request"
                                >
                                  <CheckCircle2 size={12} />
                                  <span>Approve Request</span>
                                </button>
                              )}
                              {canApprove && (
                                <button
                                  type="button"
                                  className="button secondary danger-text"
                                  style={{ padding: '5px 8px', fontSize: '11px' }}
                                  onClick={() => open(row, 'return')}
                                  title="Return Request"
                                >
                                  Return
                                </button>
                              )}
                            </>
                          ) : (
                            <>
                              <button
                                type="button"
                                className="button secondary"
                                style={{ padding: '5px 10px', fontSize: '11.5px' }}
                                onClick={() => handleTrackLive(row)}
                                title="Status Tracking of Requests"
                              >
                                <Truck size={12} />
                                <span>Track Live</span>
                              </button>
                              <button
                                type="button"
                                className="button secondary"
                                style={{ padding: '5px 10px', fontSize: '11.5px' }}
                                onClick={() => handleViewRequest(row)}
                                title="View Request"
                              >
                                <Eye size={12} />
                                <span>View Request</span>
                              </button>
                              {canApprove && isCompleted && (
                                <button
                                  type="button"
                                  className="button secondary"
                                  style={{ padding: '5px 9px', fontSize: '11px' }}
                                  onClick={() => open(row, 'return')}
                                  title="Return Assets"
                                >
                                  <RotateCw size={11} />
                                  <span>Return Assets</span>
                                </button>
                              )}
                            </>
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

        <div style={{ padding: '8px 14px', borderTop: '1px solid var(--color-border)' }}>
          <PaginationBar meta={data?.requests || {}} onPageChange={setPage} label="requests" />
        </div>
      </section>

      {/* TrackingAid Mirror for Monitoring */}
      {!external && <TrackingAidMirror items={data?.tracking_mirror || []} />}

      {/* Live Tracking & Handoff Inspection Drawer */}
      <ResourceRequestDetailDrawer
        request={inspectingRequest}
        isOpen={Boolean(inspectingRequest)}
        mode={drawerMode}
        onClose={() => setInspectingRequest(null)}
        onValidateAndForward={handleValidateAndForwardFromDrawer}
        onReturn={handleReturnFromDrawer}
        onEdit={(req) => open(req, 'view')}
        canApprove={canApprove}
        isProcessing={isProcessing}
      />
    </main>
  )
}
