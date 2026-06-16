import { PackageCheck, RefreshCcw } from 'lucide-react'
import { useEffect, useState } from 'react'
import {
  createResourceRequest,
  forwardResourceRequest,
  getResourceRequest,
  getResourceRequests,
  returnResourceRequest,
  validateResourceRequest,
} from '../api/resourceRequestApi'
import ResourceRequestNotice from '../components/resources/ResourceRequestNotice'
import ResourceRequestQueueTable from '../components/resources/ResourceRequestQueueTable'
import ResourceRequestStats from '../components/resources/ResourceRequestStats'
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

export default function ResourcesRequestsPage() {
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [queuePage, setQueuePage] = useState(1)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [modalMode, setModalMode] = useState('create')
  const [selectedRequestId, setSelectedRequestId] = useState('')
  const [form, setForm] = useState(emptyResourceRequestForm())
  const [formError, setFormError] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    let ignore = false

    async function loadInitialRequests() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getResourceRequests(filterParams('', 'all', 'all', queuePage))

        if (!ignore) {
          setPayload(data)
        }
      } catch {
        if (!ignore) {
          setError('Resource requests cannot be loaded right now. Please check the backend or database connection.')
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
  }, [queuePage])

  async function loadRequests(showMessage = '') {
    setIsLoading(true)
    setError('')
    setMessage('')

    try {
      const data = await getResourceRequests(filterParams('', 'all', 'all', queuePage))
      setPayload(data)

      if (showMessage) {
        setMessage(showMessage)
      }
    } catch {
      setError('Resource requests cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  const requests = payload?.requests?.data || []
  const pagination = payload?.requests || {}
  const options = payload?.options || {}
  const isInitialLoading = isLoading && !payload
  const isRefreshing = isLoading && Boolean(payload)
  const hasBlockingError = error && !payload

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

  async function handleSyncEvaTrack() {
    await loadRequests('Latest shared DB requests loaded. EvaTrack requests will appear here after they are saved in the shared database.')
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

<<<<<<< HEAD
      {isLoading && <LoadingState />}
=======
      {isInitialLoading && <LoadingState />}
>>>>>>> 4748515fd9da7c3d41af7e11c0951e50f424cd0c
      {error && <div className="form-error">{error}</div>}

      {!isInitialLoading && !hasBlockingError && payload && (
        <>
          <ResourceRequestNotice note={payload?.scope_note} />
          <ResourceRequestStats summary={payload?.summary} />

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
                onPageChange={setQueuePage}
              />
            </RefreshOverlay>
            <TrackingAidMirror items={payload?.tracking_mirror || []} />
          </div>
        </>
      )}

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
