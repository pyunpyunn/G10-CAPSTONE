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
import ResourceRequestFilters from '../components/resources/ResourceRequestFilters'
import ResourceRequestNotice from '../components/resources/ResourceRequestNotice'
import ResourceRequestQueueTable from '../components/resources/ResourceRequestQueueTable'
import ResourceRequestStats from '../components/resources/ResourceRequestStats'
import ResourceValidationModal from '../components/resources/ResourceValidationModal'
import TrackingAidMirror from '../components/resources/TrackingAidMirror'
import LoadingState from '../components/ui/LoadingState'
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
  const [search, setSearch] = useState('')
  const [purok, setPurok] = useState('all')
  const [activeChip, setActiveChip] = useState('all')
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
        const data = await getResourceRequests(filterParams(search, purok, activeChip))

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
  }, [search, purok, activeChip])

  async function loadRequests(showMessage = '') {
    setIsLoading(true)
    setError('')
    setMessage('')

    try {
      const data = await getResourceRequests(filterParams(search, purok, activeChip))
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

  function openCreateModal() {
    setModalMode('create')
    setSelectedRequestId('')
    setForm(emptyResourceRequestForm(payload || {}))
    setFormError('')
    setIsModalOpen(true)
  }

  async function openExistingModal(request, mode, overrideStatus = '', initialError = '') {
    setError('')
    setFormError(initialError)

    try {
      const data = await getResourceRequest(request.request_id)
      const nextForm = formFromResourceRequest(data.request)

      if (overrideStatus) {
        nextForm.validation_status = overrideStatus
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
      } else {
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
      await validateResourceRequest(selectedRequestId, buildValidationPayload(form))
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
    if (!['verified', 'forwarded'].includes(request.validation.key)) {
      await openExistingModal(
        request,
        'edit',
        'verified',
        'Review the record and save a Verified decision before forwarding to TrackingAid.',
      )
      return
    }

    const confirmed = window.confirm(`Forward ${request.request_id} to TrackingAid handoff?`)

    if (!confirmed) {
      return
    }

    setError('')

    try {
      await forwardResourceRequest(request.request_id, { validation_notes: request.validation_notes })
      await loadRequests('Verified request forwarded to TrackingAid handoff.')
    } catch (forwardError) {
      setError(resourceRequestErrorMessage(forwardError, 'Unable to forward the request right now.'))
    }
  }

  async function handleSyncEvaTrack() {
    await loadRequests('Latest shared DB requests loaded. EvaTrack requests will appear here after they are saved in the shared database.')
  }

  return (
    <section className="page active resources-page">
      <div className="page-ops-row">
        <div className="left" />
        <div className="right">
          <button className="btn btn-secondary btn-sm" type="button" onClick={handleSyncEvaTrack}>
            <RefreshCcw size={14} />
            Sync EvaTrack
          </button>
          <button className="btn btn-primary btn-sm" type="button" onClick={openCreateModal} disabled={!payload}>
            <PackageCheck size={14} />
            Validate request
          </button>
        </div>
      </div>

      {isLoading && <LoadingState message="Loading resource requests..." />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && !error && (
        <>
          <ResourceRequestNotice note={payload?.scope_note} />
          <ResourceRequestStats summary={payload?.summary} />

          <ResourceRequestFilters
            search={search}
            onSearchChange={setSearch}
            purok={purok}
            onPurokChange={setPurok}
            puroks={options.puroks || []}
            activeChip={activeChip}
            onChipChange={setActiveChip}
          />

          {message && <div className="rr-message">{message}</div>}

          <div className="rr-layout">
            <ResourceRequestQueueTable
              requests={requests}
              pagination={pagination}
              onView={(request) => openExistingModal(request, 'view')}
              onValidate={(request) => openExistingModal(request, 'edit')}
              onForward={handleRowForward}
              onReturn={(request) => openExistingModal(request, 'edit', 'returned', 'Add the return reason before saving.')}
            />
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
