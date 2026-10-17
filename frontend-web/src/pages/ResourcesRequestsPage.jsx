import { PackageCheck } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  forwardResourceRequest,
  getResourceRequests,
  returnResourceRequest,
  validateResourceRequest,
} from '../api/resourceRequestApi'
import ResourceRequestBatchBar from '../components/resources/ResourceRequestBatchBar'
import ResourceRequestDetailDrawer from '../components/resources/ResourceRequestDetailDrawer'
import ResourceRequestQueueTable from '../components/resources/ResourceRequestQueueTable'
import ResourceRequestStats from '../components/resources/ResourceRequestStats'
import TrackingAidMirror from '../components/resources/TrackingAidMirror'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import RefreshOverlay from '../components/ui/RefreshOverlay'
import {
  buildForwardPayload,
  buildReturnPayload,
  buildValidationPayload,
  filterParams,
  resourceRequestErrorMessage,
} from '../utils/resourceRequestHelpers'

export default function ResourcesRequestsPage() {
  const navigate = useNavigate()
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [queuePage, setQueuePage] = useState(1)
  const [summaryPeriod, setSummaryPeriod] = useState('week')

  // Interactive Enhancements State
  const [selectedIds, setSelectedIds] = useState([])
  const [inspectingRequest, setInspectingRequest] = useState(null)
  const [isProcessing, setIsProcessing] = useState(false)

  useEffect(() => {
    let ignore = false

    async function loadInitialRequests() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getResourceRequests(filterParams('', 'all', 'all', queuePage, summaryPeriod))

        if (!ignore) {
          setPayload(data)
        }
      } catch (loadError) {
        if (!ignore) {
          setError(
            resourceRequestErrorMessage(
              loadError,
              'Resource requests could not be loaded. Please sign in again or check the Laravel API.'
            )
          )
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadInitialRequests()

    return () => {
      ignore = true
    }
  }, [queuePage, summaryPeriod])

  async function loadRequests(showMessage = '') {
    setIsLoading(true)
    setError('')
    setMessage('')

    try {
      const data = await getResourceRequests(filterParams('', 'all', 'all', queuePage, summaryPeriod))
      setPayload(data)

      if (showMessage) {
        setMessage(showMessage)
      }
    } catch (loadError) {
      setError(
        resourceRequestErrorMessage(
          loadError,
          'Resource requests could not be loaded. Please sign in again or check the Laravel API.'
        )
      )
    } finally {
      setIsLoading(false)
    }
  }

  const requests = payload?.requests?.data || []
  const pagination = payload?.requests || {}
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)
  const hasBlockingError = error && !payload

  function openCreateModal() {
    navigate('/resources-requests/new')
  }

  function openEditPage(request) {
    navigate(`/resources-requests/${request.request_id}/edit`)
  }

  async function openExistingModal(request, mode, initialError = '') {
    navigate(`/resources-requests/${request.request_id}/${mode}`, { state: { initialError } })
  }

  async function handleSyncEvaTrack() {
    await loadRequests(
      'Latest shared DB requests loaded. EvaTrack requests will appear here after they are saved in the shared database.'
    )
  }

  // Row Selection Handlers
  function handleSelectRow(id) {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
    )
  }

  function handleSelectAll(shouldSelectAll) {
    if (shouldSelectAll) {
      const pageIds = requests.map((r) => r.request_id)
      setSelectedIds(Array.from(new Set([...selectedIds, ...pageIds])))
    } else {
      const pageIds = new Set(requests.map((r) => r.request_id))
      setSelectedIds(selectedIds.filter((id) => !pageIds.has(id)))
    }
  }

  function handleClearSelection() {
    setSelectedIds([])
  }

  // Inspection Drawer Handlers
  function handleInspectRequest(request) {
    setInspectingRequest(request)
  }

  function handleCloseDrawer() {
    setInspectingRequest(null)
  }

  async function handleValidateAndForwardFromDrawer(request) {
    setIsProcessing(true)
    try {
      await validateResourceRequest(
        request.request_id,
        buildValidationPayload({ validation_status: 'verified', validation_notes: 'Verified via Inspection Drawer' })
      )
      await forwardResourceRequest(
        request.request_id,
        buildForwardPayload({ forward_notes: 'Forwarded via Inspection Drawer' })
      )
      handleCloseDrawer()
      await loadRequests(`Request #${request.request_id} successfully validated and forwarded to TrackingAid.`)
    } catch (err) {
      setError(resourceRequestErrorMessage(err, 'Unable to validate and forward request.'))
    } finally {
      setIsProcessing(false)
    }
  }

  function handleReturnFromDrawer(request) {
    handleCloseDrawer()
    openExistingModal(request, 'return')
  }

  // Batch Operations Handlers
  async function handleBatchForward() {
    if (selectedIds.length === 0) return
    setIsProcessing(true)

    try {
      let count = 0
      for (const id of selectedIds) {
        await validateResourceRequest(
          id,
          buildValidationPayload({ validation_status: 'verified', validation_notes: 'Batch validated and forwarded via HQ command queue' })
        )
        await forwardResourceRequest(
          id,
          buildForwardPayload({ forward_notes: 'Batch forwarded to TrackingAid via HQ command queue' })
        )
        count++
      }
      setSelectedIds([])
      await loadRequests(`Successfully batch validated and forwarded ${count} resource request(s) to TrackingAid.`)
    } catch (err) {
      setError(resourceRequestErrorMessage(err, 'Failed to complete batch forwarding.'))
    } finally {
      setIsProcessing(false)
    }
  }

  async function handleBatchReject() {
    if (selectedIds.length === 0) return
    const confirmed = window.confirm(
      `Are you sure you want to return/reject ${selectedIds.length} selected request(s)?`
    )
    if (!confirmed) return

    setIsProcessing(true)
    try {
      let count = 0
      for (const id of selectedIds) {
        await returnResourceRequest(
          id,
          buildReturnPayload({ validation_notes: 'Returned during batch rejection' })
        )
        count++
      }
      setSelectedIds([])
      await loadRequests(`Successfully returned ${count} resource request(s).`)
    } catch (err) {
      setError(resourceRequestErrorMessage(err, 'Failed to complete batch rejection.'))
    } finally {
      setIsProcessing(false)
    }
  }

  return (
    <section className="page active resources-page">
      <PageHeader
        title="Resources & Requests"
        subtitle={payload?.area_label || 'Shared database records'}
        actions={
          <button className="button review" type="button" onClick={openCreateModal}>
            <PackageCheck size={16} />
            New request
          </button>
        }
      />

      {isInitialLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!hasBlockingError && (payload || isLoading) && (
        <>
          {message && <div className="rr-message">{message}</div>}

          <div className="rr-layout">
            <div className="rr-main-column">
              <RefreshOverlay active={isRefreshing}>
                <ResourceRequestQueueTable
                  requests={requests}
                  pagination={pagination}
                  loading={isLoading}
                  selectedIds={selectedIds}
                  onSelectRow={handleSelectRow}
                  onSelectAll={handleSelectAll}
                  onInspect={handleInspectRequest}
                  onView={handleInspectRequest}
                  onEdit={openEditPage}
                  onPageChange={setQueuePage}
                  onSync={handleSyncEvaTrack}
                />
              </RefreshOverlay>
              <TrackingAidMirror items={payload?.tracking_mirror || []} />
            </div>
            <aside className="rr-side-column">
              <ResourceRequestStats
                summary={payload?.summary}
                period={summaryPeriod}
                onPeriodChange={(value) => {
                  setSummaryPeriod(value)
                  setQueuePage(1)
                }}
              />
            </aside>
          </div>
        </>
      )}

      {/* Slide-over Inspection Detail Drawer */}
      <ResourceRequestDetailDrawer
        request={inspectingRequest}
        isOpen={Boolean(inspectingRequest)}
        onClose={handleCloseDrawer}
        onValidateAndForward={handleValidateAndForwardFromDrawer}
        onReturn={handleReturnFromDrawer}
        onEdit={openEditPage}
        isProcessing={isProcessing}
      />

      <ResourceRequestBatchBar
        selectedCount={selectedIds.length}
        onClearSelection={handleClearSelection}
        onBatchForward={handleBatchForward}
        onBatchReject={handleBatchReject}
        isProcessing={isProcessing}
      />
    </section>
  )
}
