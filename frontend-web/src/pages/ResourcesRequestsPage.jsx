import { PackageCheck, RefreshCcw } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  completeResourceRequest,
  createResourceRequest,
  forwardResourceRequest,
  getResourceRequest,
  getResourceRequests,
  returnResourceRequest,
  validateResourceRequest,
} from '../api/resourceRequestApi'
import ResourceRequestQueueTable from '../components/resources/ResourceRequestQueueTable'
import ResourceRequestSummaryTable from '../components/resources/ResourceRequestSummaryTable'
import ResourceValidationModal from '../components/resources/ResourceValidationModal'
import TrackingAidMirror from '../components/resources/TrackingAidMirror'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import RefreshOverlay from '../components/ui/RefreshOverlay'
import {
  buildCreatePayload,
  buildForwardPayload,
  buildReturnPayload,
  buildValidationPayload,
  emptyResourceRequestForm,
  filterParams,
  formFromResourceRequest,
  resourceRequestErrorMessage,
} from '../utils/resourceRequestHelpers'
import { mergeQueryParams, readQueryNumber, readQueryParam, setQueryParams } from '../utils/pageQuery'
import { pageDataError } from '../utils/pageShell'

export default function ResourcesRequestsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalMode, setModalMode] = useState('create')
  const [selectedRequestId, setSelectedRequestId] = useState('')
  const [form, setForm] = useState(emptyResourceRequestForm())
  const [formError, setFormError] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  const statusFilter = readQueryParam(searchParams, 'status', 'all')
  const statusId = readQueryParam(searchParams, 'status_id')
  const eventId = readQueryParam(searchParams, 'event_id')
  const queuePage = readQueryNumber(searchParams, 'page', 1)

  const loadRequests = useCallback(async (showMessage = '') => {
    setIsLoading(true)
    setError('')
    setMessage('')

    try {
      const data = await getResourceRequests(filterParams('', 'all', statusFilter, queuePage, {
        status_id: statusId,
        event_id: eventId,
      }))
      setPayload(data)

      if (showMessage) {
        setMessage(showMessage)
      }
    } catch {
      setError('Resource requests cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }, [statusFilter, statusId, eventId, queuePage])

  useEffect(() => {
    loadRequests()
  }, [loadRequests])

  useEffect(() => {
    const activeEventId = payload?.active_event?.event_id

    if (!activeEventId || eventId === activeEventId) {
      return
    }

    setQueryParams(setSearchParams, searchParams, { event_id: activeEventId }, { replace: true })
  }, [payload?.active_event?.event_id, eventId, searchParams, setSearchParams])

  const requests = payload?.requests?.data || []
  const pagination = payload?.requests || {}
  const options = payload?.options || {}
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)
  const dataError = pageDataError(error, Boolean(payload))

  function openCreateModal() {
    setModalMode('create')
    setSelectedRequestId('')
    setForm(emptyResourceRequestForm(payload || {}))
    setFormError('')
    setIsModalOpen(true)
  }

  async function openExistingModal(request, mode, initialError = '') {
    setError('')
    setFormError(initialError)

    try {
      const data = await getResourceRequest(request.request_id)
      const nextForm = formFromResourceRequest(data.request)

      if (mode === 'return') {
        nextForm.validation_status = 'returned'
      }

      setModalMode(mode)
      setSelectedRequestId(data.request.request_id)
      setForm(nextForm)
      setIsModalOpen(true)

      setQueryParams(setSearchParams, searchParams, {
        request_id: data.request.request_id,
        event_id: eventId || payload?.active_event?.event_id || null,
      }, { replace: true })
    } catch {
      setError('Selected resource request cannot be loaded right now.')
    }
  }

  function closeModal() {
    if (isSaving) {
      return
    }

    setIsModalOpen(false)
    setSelectedRequestId('')
    setFormError('')
    setQueryParams(setSearchParams, searchParams, { request_id: null }, { replace: true })
  }

  function selectStatusRow(row) {
    const nextStatus = statusFilter === row.key ? 'all' : row.key

    setQueryParams(setSearchParams, searchParams, {
      status: nextStatus,
      status_id: nextStatus === 'all' ? null : row.status_id,
      page: 1,
      event_id: eventId || payload?.active_event?.event_id || null,
    })
  }

  function changeQueuePage(page) {
    setQueryParams(setSearchParams, searchParams, { page })
  }

  async function submitForm(event) {
    event.preventDefault()
    setFormError('')
    setIsSaving(true)

    try {
      if (modalMode === 'create') {
        await createResourceRequest(buildCreatePayload(form))
        setIsModalOpen(false)
        await loadRequests('Request saved for validation.')
      } else if (modalMode === 'validate') {
        await validateResourceRequest(selectedRequestId, buildValidationPayload(form))
        setIsModalOpen(false)
        await loadRequests('Validation record saved.')
      }
    } catch (saveError) {
      setFormError(resourceRequestErrorMessage(saveError))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleForwardFromModal() {
    if (!selectedRequestId) {
      setFormError('Open a saved request before forwarding to TrackingAid.')
      return
    }

    if (!['verified', 'forwarded'].includes(form.validation_status)) {
      setFormError('Set the validation decision to Verified before forwarding to TrackingAid.')
      return
    }

    setFormError('')
    setIsSaving(true)

    try {
      await forwardResourceRequest(selectedRequestId, buildForwardPayload(form))
      setIsModalOpen(false)
      await loadRequests('Verified request forwarded to TrackingAid handoff.')
    } catch (forwardError) {
      setFormError(resourceRequestErrorMessage(forwardError, 'Unable to forward the request. Please check the validation record.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleReturnFromModal() {
    if (!selectedRequestId) {
      setFormError('Open a saved request before returning it.')
      return
    }

    if (!form.validation_notes.trim()) {
      setFormError('Add a clear return reason in the validation notes.')
      return
    }

    setFormError('')
    setIsSaving(true)

    try {
      await returnResourceRequest(selectedRequestId, buildReturnPayload(form))
      setIsModalOpen(false)
      await loadRequests('Request returned for missing information or duplicate check.')
    } catch (returnError) {
      setFormError(resourceRequestErrorMessage(returnError, 'Unable to return the request. Please check the validation notes.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleRowForward(request) {
    if (request.validation.key !== 'verified') {
      await openExistingModal(request, 'view', 'Only verified requests can be forwarded to TrackingAid.')
      return
    }

    await openExistingModal(request, 'forward')
  }

  async function handleRowComplete(request) {
    if (request.validation.key !== 'forwarded') {
      await openExistingModal(request, 'view', 'Only in-progress requests can be marked completed.')
      return
    }

    setError('')
    setIsSaving(true)

    try {
      await completeResourceRequest(request.request_id)
      await loadRequests('Request marked as completed.')
    } catch (completeError) {
      setError(resourceRequestErrorMessage(completeError, 'Unable to mark the request as completed.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleSyncEvaTrack() {
    await loadRequests('Latest shared DB requests loaded.')
  }

  return (
    <section className="page active resources-page">
      <PageHeader
        title="Resources & Requests"
        actions={
          <>
            <button className="btn btn-secondary btn-sm" type="button" onClick={handleSyncEvaTrack}>
              <RefreshCcw size={14} />
              Sync EvaTrack
            </button>
            <button className="btn btn-primary btn-sm" type="button" onClick={openCreateModal}>
              <PackageCheck size={14} />
              Validate request
            </button>
          </>
        }
      />

      {isInitialLoading ? <LoadingState label="Loading resource requests..." /> : null}
      {dataError ? <div className="page-data-notice is-error">{dataError}</div> : null}

      <ResourceRequestSummaryTable
        summary={payload?.summary}
        activeStatus={statusFilter}
        onSelectStatus={selectStatusRow}
      />

      {message && <div className="rr-message">{message}</div>}

      <div className="rr-layout">
        <RefreshOverlay active={isRefreshing}>
          <ResourceRequestQueueTable
            requests={requests}
            pagination={pagination}
            onView={(request) => openExistingModal(request, 'view')}
            onValidate={(request) => openExistingModal(request, 'validate')}
            onForward={handleRowForward}
            onReturn={(request) => openExistingModal(request, 'return', 'Add the return reason before saving.')}
            onComplete={handleRowComplete}
            onPageChange={changeQueuePage}
          />
        </RefreshOverlay>
        <TrackingAidMirror items={payload?.tracking_mirror || []} />
      </div>

      <ResourceValidationModal
        mode={modalMode}
        isOpen={isModalOpen}
        form={form}
        setForm={setForm}
        options={options}
        formError={formError}
        isSaving={isSaving}
        selectedRequestId={selectedRequestId}
        onClose={closeModal}
        onSubmit={submitForm}
        onForward={handleForwardFromModal}
        onReturn={handleReturnFromModal}
      />
    </section>
  )
}
