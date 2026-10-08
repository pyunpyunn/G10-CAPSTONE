import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { Archive, ArrowLeft } from 'lucide-react'
import { createBroadcast, createDisasterEvent, getBroadcastWorkspace, updateDisasterEvent } from '../api/broadcastApi'
import { closeActiveEvent } from '../api/dashboardApi'
import BroadcastComposeForm from '../components/broadcast/BroadcastComposeForm'
import BroadcastSidePanel from '../components/broadcast/BroadcastSidePanel'
import CloseActiveEventModal from '../components/broadcast/CloseActiveEventModal'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import {
  apiErrorMessage,
  defaultForm,
  targetAreaLabel,
} from '../utils/broadcastHelpers'

export default function BroadcastPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const requestedEventId = searchParams.get('event_id')
  const [workspace, setWorkspace] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [form, setForm] = useState(defaultForm())
  const [selectedStatuses, setSelectedStatuses] = useState([])
  const [selectedPurok, setSelectedPurok] = useState('')
  const [directPuroks, setDirectPuroks] = useState([])
  const [formError, setFormError] = useState('')
  const [isSaving, setIsSaving] = useState(false)
  const [formNotice, setFormNotice] = useState('')
  const [isCloseModalOpen, setIsCloseModalOpen] = useState(false)
  const [isClosingEvent, setIsClosingEvent] = useState(false)
  const [closeError, setCloseError] = useState('')

  useEffect(() => {
    let ignore = false

    async function loadInitialWorkspace() {
      try {
        const data = await getBroadcastWorkspace(requestedEventId)

        if (!ignore) {
          if (requestedEventId && data.current_event?.status !== 'active') {
            setError('This disaster event is no longer active and cannot be updated.')
            return
          }

          setWorkspace(data)
          initForm(data)
        }
      } catch {
        if (!ignore) {
          setError('Disaster broadcasting cannot be loaded right now. Please check the backend or database connection.')
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadInitialWorkspace()

    return () => {
      ignore = true
    }
  }, [requestedEventId])

  const activeEvent = requestedEventId
    ? workspace?.current_event?.status === 'active' ? workspace.current_event : null
    : workspace?.active_event || null
  const broadcasts = workspace?.broadcasts || []
  const disasterTypes = workspace?.disaster_types || []
  const severityLevels = workspace?.severity_levels || []
  const puroks = workspace?.puroks || []
  const statusOptions = workspace?.status_options || []
  const deliveryEventId = activeEvent?.event_id

  // Delivery runs on the server; keep its status visible without a page reload.
  useEffect(() => {
    if (!deliveryEventId) return
    let cancelled = false
    let timer
    async function refreshDeliveryStatus() {
      try {
        const data = await getBroadcastWorkspace(deliveryEventId)
        if (!cancelled) setWorkspace(data)
      } catch {
        // A status read failure does not undo a saved broadcast.
      } finally {
        if (!cancelled) timer = setTimeout(refreshDeliveryStatus, 5000)
      }
    }
    timer = setTimeout(refreshDeliveryStatus, 2000)
    return () => { cancelled = true; clearTimeout(timer) }
  }, [deliveryEventId])
  function initForm(wsData) {
    const currentWorkspace = wsData || workspace
    const nextForm = defaultForm(currentWorkspace)
    const active = currentWorkspace?.active_event

    if (active) {
      nextForm.type_id = active.type_id || nextForm.type_id
      nextForm.severity_id = active.severity_level_id || nextForm.severity_id
      nextForm.event_name = active.name
    }

    setForm(nextForm)
    setSelectedStatuses((currentWorkspace?.status_options || []).map((option) => option.key))
    setSelectedPurok(currentWorkspace?.puroks?.[0]?.name || '')
    setDirectPuroks([])
    setFormError('')
    setFormNotice('')
  }

  async function loadWorkspace() {
    setIsLoading(true)
    setError('')

    try {
      const data = await getBroadcastWorkspace(requestedEventId)
      setWorkspace(data)
      initForm(data)
    } catch {
      setError('Disaster broadcasting cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  function updateForm(field, value) {
    setForm((current) => ({ ...current, [field]: value }))
    if (field === 'scope_type' && value === 'barangay_wide') {
      setDirectPuroks([])
    }
    setFormNotice('')
  }

  function toggleStatus(statusKey) {
    setSelectedStatuses((current) => {
      if (current.includes(statusKey)) {
        return current.filter((item) => item !== statusKey)
      }

      if (current.length >= 4) {
        return current
      }

      return [...current, statusKey]
    })
  }

  function addDirectPurok() {
    if (!selectedPurok || directPuroks.some((item) => item.name === selectedPurok)) {
      return
    }

    if (directPuroks.length >= 5) {
      setFormError('Select up to five puroks per broadcast.')
      return
    }

    setDirectPuroks((current) => [...current, { name: selectedPurok }])
  }

  function removeDirectPurok(name) {
    setDirectPuroks((current) => current.filter((item) => item.name !== name))
  }

  function closeCloseEventModal() {
    if (isClosingEvent) {
      return
    }

    setIsCloseModalOpen(false)
    setCloseError('')
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setFormError('')

    if (selectedStatuses.length !== 4) {
      setFormError('Select exactly four household mobile status buttons.')
      return
    }

    if (!activeEvent && !form.event_name.trim()) {
      setFormError('Enter the disaster event name before posting the broadcast.')
      return
    }

    if (!form.broadcast_title.trim() || form.message.trim().length < 10) {
      setFormError('Enter a broadcast title and an official instruction of at least 10 characters.')
      return
    }

    if (form.scope_type === 'selected_puroks' && directPuroks.length === 0) {
      setFormError('Select at least one directly affected purok.')
      return
    }

    setIsSaving(true)

    try {
      let eventId

      if (activeEvent) {
        eventId = activeEvent.event_id
        const updatedEventWorkspace = await updateDisasterEvent(eventId, {
          name: form.event_name.trim(),
          type_id: form.type_id,
          severity_level_id: form.severity_id,
        })
        setWorkspace(updatedEventWorkspace)
      } else {
        const eventResult = await createDisasterEvent({
          name: form.event_name,
          type_id: form.type_id,
          severity_level_id: form.severity_id,
          started_at: `${form.started_date} ${form.started_time}`,
        })

        eventId = eventResult.active_event.event_id
      }

      const broadcastResult = await createBroadcast(eventId, {
        broadcast_title: form.broadcast_title,
        message: form.message,
        severity_id: form.severity_id,
        scope_type: form.scope_type,
        target_area: targetAreaLabel(form.scope_type, directPuroks),
        estimated_duration: form.estimated_duration,
        allowed_statuses: selectedStatuses,
        direct_puroks: directPuroks,
      })

      setWorkspace(broadcastResult)
      initForm(broadcastResult)
      setFormNotice('Broadcast posted. Mobile notification delivery starts automatically; you can leave this page.')
    } catch (saveError) {
      setFormError(apiErrorMessage(saveError, 'Unable to save this broadcast. Please check the entries and try again.'))
    } finally {
      setIsSaving(false)
    }
  }

  async function handleCloseActiveEvent() {
    setIsClosingEvent(true)
    setCloseError('')

    try {
      await closeActiveEvent()
      setIsCloseModalOpen(false)
      await loadWorkspace()
    } catch (closeEventError) {
      setCloseError(apiErrorMessage(closeEventError, 'Unable to close the active event. Please try again.'))
    } finally {
      setIsClosingEvent(false)
    }
  }

  return (
    <section className="page broadcast-page active">
      <PageHeader
        title="Disaster Broadcasting"
        actions={
          <>
            <button className="btn btn-secondary btn-sm" type="button" onClick={() => navigate('/dashboard')}>
              <ArrowLeft size={14} />
              Go Back to Dashboard
            </button>
            {activeEvent && (
              <button className="btn btn-warning btn-sm" type="button" onClick={() => setIsCloseModalOpen(true)}>
                <Archive size={14} />
                Close Active Event
              </button>
            )}
          </>
        }
      />

      <CloseActiveEventModal
        activeEvent={activeEvent}
        isOpen={isCloseModalOpen}
        isClosingEvent={isClosingEvent}
        closeError={closeError}
        onClose={closeCloseEventModal}
        onConfirm={handleCloseActiveEvent}
      />

      {isLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && !error && workspace && (
        <div className="broadcast-shell">
          <main className="panel broadcast-form-panel">
            <BroadcastComposeForm
              activeEvent={activeEvent}
              form={form}
              disasterTypes={disasterTypes}
              severityLevels={severityLevels}
              puroks={puroks}
              statusOptions={statusOptions}
              selectedPurok={selectedPurok}
              selectedStatuses={selectedStatuses}
              directPuroks={directPuroks}
              formError={formError}
              formNotice={formNotice}
              isSaving={isSaving}
              onChange={updateForm}
              onSelectPurok={setSelectedPurok}
              onAddPurok={addDirectPurok}
              onRemovePurok={removeDirectPurok}
              onToggleStatus={toggleStatus}
              onSubmit={handleSubmit}
            />
          </main>

          <BroadcastSidePanel activeEvent={activeEvent} broadcasts={broadcasts} />
        </div>
      )}
    </section>
  )
}
