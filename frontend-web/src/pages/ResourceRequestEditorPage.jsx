import { ArrowLeft, RefreshCcw } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
import {
  createResourceRequest,
  forwardResourceRequest,
  getResourceRequest,
  getResourceRequests,
  returnResourceRequest,
  updateResourceRequest,
  validateResourceRequest,
} from '../api/resourceRequestApi'
import ResourceValidationModal from '../components/resources/ResourceValidationModal'
import LoadingState from '../components/ui/LoadingState'
import {
  buildCreatePayload,
  buildForwardPayload,
  buildReturnPayload,
  buildValidationPayload,
  buildUpdatePayload,
  emptyResourceRequestForm,
  formFromResourceRequest,
  resourceRequestErrorMessage,
} from '../utils/resourceRequestHelpers'

export default function ResourceRequestEditorPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const { requestId, mode = 'create' } = useParams()
  const [payload, setPayload] = useState(null)
  const [form, setForm] = useState(emptyResourceRequestForm())
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState(location.state?.initialError || '')
  const [formError, setFormError] = useState('')

  useEffect(() => {
    let ignore = false

    async function loadEditor() {
      setIsLoading(true)
      setError('')

      try {
        const workspacePromise = getResourceRequests({ per_page: 1 })

        if (!requestId) {
          const workspace = await workspacePromise
          if (!ignore) {
            setPayload({ options: workspace.options || {} })
            setForm(emptyResourceRequestForm(workspace || {}))
          }
          return
        }

        const [data, workspace] = await Promise.all([
          getResourceRequest(requestId),
          workspacePromise,
        ])
        const nextForm = formFromResourceRequest(data.request)
        if (mode === 'validate') {
          nextForm.validation_status = 'verified'
        }
        if (mode === 'return') {
          nextForm.validation_status = 'returned'
        }

        if (!ignore) {
          setPayload({ ...data, options: workspace.options || {} })
          setForm(nextForm)
        }
      } catch (loadError) {
        if (!ignore) {
          setError(resourceRequestErrorMessage(loadError, 'This resource request could not be loaded.'))
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadEditor()
    return () => { ignore = true }
  }, [requestId, mode])

  const options = payload?.options || {}

  function closePage() {
    navigate('/resources-requests')
  }

  function openReturnPage() {
    navigate(`/resources-requests/${requestId}/return`)
  }

  async function submitForm(event) {
    event.preventDefault()
    setFormError('')
    setIsSaving(true)

    try {
      if (mode === 'create') {
        await createResourceRequest(buildCreatePayload(form))
      } else if (mode === 'edit') {
        await updateResourceRequest(requestId, buildUpdatePayload(form))
      } else if (mode === 'validate') {
        await validateResourceRequest(requestId, buildValidationPayload({ ...form, validation_status: 'verified' }))
      }
      closePage()
    } catch (saveError) {
      setFormError(resourceRequestErrorMessage(saveError))
    } finally {
      setIsSaving(false)
    }
  }

  async function forwardRequest() {
    setFormError('')
    setIsSaving(true)
    try {
      await forwardResourceRequest(requestId, buildForwardPayload(form))
      closePage()
    } catch (forwardError) {
      setFormError(resourceRequestErrorMessage(forwardError, 'Unable to forward this request.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function validateAndForwardRequest() {
    const confirmed = window.confirm('Validate this request and forward it to TrackingAid now? Select Cancel to leave it unchanged.')

    if (!confirmed) {
      return
    }

    setFormError('')
    setIsSaving(true)

    try {
      await validateResourceRequest(requestId, buildValidationPayload({ ...form, validation_status: 'verified' }))
      await forwardResourceRequest(requestId, buildForwardPayload(form))
      closePage()
    } catch (validationError) {
      setFormError(resourceRequestErrorMessage(validationError, 'The request could not be validated and forwarded.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function returnRequest() {
    if (!form.validation_notes.trim()) {
      setFormError('Add a clear return reason in the validation notes.')
      return
    }

    setFormError('')
    setIsSaving(true)
    try {
      await returnResourceRequest(requestId, buildReturnPayload(form))
      closePage()
    } catch (returnError) {
      setFormError(resourceRequestErrorMessage(returnError, 'Unable to return this request.'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <section className="page active resources-page resource-request-editor">
      <header className="household-status-page-header">
        <div className="household-status-header-copy">
          <h1>{editorTitle(mode)}</h1>
          <p>Barangay Mambaling, Cebu City</p>
        </div>
        <div className="weather-page-actions household-status-page-actions">
          <div className="weather-live-status"><strong>Requests</strong><span>Validation and handoff records</span></div>
          <button className="button secondary" type="button" onClick={closePage}><ArrowLeft size={16} />Back to requests</button>
          {requestId && <button className="button secondary" type="button" onClick={() => window.location.reload()}><RefreshCcw size={16} />Reload</button>}
        </div>
      </header>

      {isLoading ? <LoadingState /> : error ? <div className="form-error">{error}</div> : (
        <ResourceValidationModal
          mode={mode}
          form={form}
          setForm={setForm}
          options={options}
          formError={formError}
          isSaving={isSaving}
          selectedRequestId={requestId}
          onClose={closePage}
          onSubmit={submitForm}
          onValidate={validateAndForwardRequest}
          onOpenReturn={openReturnPage}
          onForward={forwardRequest}
          onReturn={returnRequest}
        />
      )}
    </section>
  )
}

function editorTitle(mode) {
  return {
    create: 'New resource request',
    view: 'Resource request details',
    validate: 'Validate resource request',
    edit: 'Edit resource request',
    return: 'Return resource request',
    forward: 'Forward resource request',
  }[mode] || 'Resource request details'
}
