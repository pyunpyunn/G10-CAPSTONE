import { useEffect, useMemo, useState } from 'react'
import { Archive, RefreshCcw, Siren } from 'lucide-react'
import { createBroadcast, createDisasterEvent, getBroadcastWorkspace } from '../api/broadcastApi'
import { closeActiveEvent } from '../api/dashboardApi'
import BroadcastComposeForm from '../components/broadcast/BroadcastComposeForm'
import BroadcastLifecycleCard from '../components/broadcast/BroadcastLifecycleCard'
import BroadcastSidePanel from '../components/broadcast/BroadcastSidePanel'
import BroadcastStartPanel from '../components/broadcast/BroadcastStartPanel'
import CloseActiveEventModal from '../components/broadcast/CloseActiveEventModal'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import {
  apiErrorMessage,
  defaultForm,
  defaultStatusKeys,
  getRecipientNote,
  targetAreaLabel,
} from '../utils/broadcastHelpers'

export default function BroadcastPage() {
  const [workspace, setWorkspace] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState(defaultForm())
  const [selectedStatuses, setSelectedStatuses] = useState(defaultStatusKeys)
  const [selectedPurok, setSelectedPurok] = useState('')
  const [selectedPriority, setSelectedPriority] = useState('high')
  const [directPuroks, setDirectPuroks] = useState([])
  const [formError, setFormError] = useState('')
  const [isSaving, setIsSaving] = useState(false)
  const [isCloseModalOpen, setIsCloseModalOpen] = useState(false)
  const [isClosingEvent, setIsClosingEvent] = useState(false)
  const [closeError, setCloseError] = useState('')

  useEffect(() => {
    let ignore = false

    async function loadInitialWorkspace() {
      try {
        const data = await getBroadcastWorkspace()

        if (!ignore) {
          setWorkspace(data)
          setForm(defaultForm(data))
          setSelectedPurok(data.puroks?.[0]?.name || '')
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
  }, [])

  const activeEvent = workspace?.active_event || null
  const broadcasts = workspace?.broadcasts || []
  const disasterTypes = workspace?.disaster_types || []
  const severityLevels = workspace?.severity_levels || []
  const puroks = workspace?.puroks || []
  const statusOptions = workspace?.status_options || []
  const lifecycleState = activeEvent ? 'active' : 'monitoring'
  const selectedType = disasterTypes.find((type) => String(type.type_id) === String(form.type_id))
  const currentTypeName = activeEvent?.type_name || selectedType?.type_name || 'Disaster event'
  const recipientNote = useMemo(
    () => getRecipientNote(currentTypeName, form.scope_type, directPuroks),
    [currentTypeName, form.scope_type, directPuroks],
  )

  async function loadWorkspace() {
    setIsLoading(true)
    setError('')

    try {
      const data = await getBroadcastWorkspace()
      setWorkspace(data)
      setSelectedPurok(data.puroks?.[0]?.name || '')
    } catch {
      setError('Disaster broadcasting cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  function openCompose() {
    const nextForm = defaultForm(workspace)

    if (activeEvent) {
      nextForm.broadcast_title = `${activeEvent.type_name} update`
      nextForm.type_id = activeEvent.type_id || nextForm.type_id
      nextForm.severity_id = activeEvent.severity_level_id || nextForm.severity_id
      nextForm.event_name = activeEvent.name
    }

    setForm(nextForm)
    setSelectedStatuses(defaultStatusKeys)
    setDirectPuroks([])
    setFormError('')
    setIsFormOpen(true)
  }

  function updateForm(field, value) {
    setForm((current) => ({ ...current, [field]: value }))
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
      setFormError('Add up to 5 direct-impact puroks only until the broadcast metadata columns are approved.')
      return
    }

    setDirectPuroks((current) => [...current, { name: selectedPurok, priority: selectedPriority }])
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
      setFormError('Enter the disaster event name before sending the first broadcast.')
      return
    }

    if (['selected_puroks', 'local_direct_impact'].includes(form.scope_type) && directPuroks.length === 0) {
      setFormError('Select at least one directly affected purok.')
      return
    }

    setIsSaving(true)

    try {
      let eventId = activeEvent?.event_id
      let nextActiveEvent = activeEvent
      let nextEvents = workspace.events

      if (!eventId) {
        const eventResult = await createDisasterEvent({
          name: form.event_name,
          type_id: form.type_id,
          severity_level_id: form.severity_id,
          started_at: `${form.started_date} ${form.started_time}`,
        })

        eventId = eventResult.active_event.event_id
        nextActiveEvent = eventResult.active_event
        nextEvents = eventResult.events
      }

      const broadcastResult = await createBroadcast(eventId, {
        broadcast_title: form.broadcast_title,
        message: form.message,
        severity_id: form.severity_id,
        scope_type: form.scope_type,
        target_area: targetAreaLabel(form.scope_type, directPuroks),
        estimated_duration: form.estimated_duration,
        attach_route: form.attach_route,
        allowed_statuses: selectedStatuses,
        direct_puroks: directPuroks,
      })

      setWorkspace((current) => ({
        ...current,
        active_event: nextActiveEvent,
        events: nextEvents,
        broadcasts: broadcastResult.broadcasts,
      }))
      setIsFormOpen(false)
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
      setIsFormOpen(false)
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
            <button className="btn btn-secondary btn-sm" type="button" onClick={loadWorkspace}>
              <RefreshCcw size={14} />
              Refresh
            </button>
            {activeEvent ? (
              <button className="btn btn-warning btn-sm" type="button" onClick={() => setIsCloseModalOpen(true)}>
                <Archive size={14} />
                Close Active Event
              </button>
            ) : (
              <button className="btn btn-danger btn-sm" type="button" onClick={openCompose}>
                <Siren size={14} />
                Declare Disaster Broadcast
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
            <BroadcastLifecycleCard
              state={lifecycleState}
              activeEvent={activeEvent}
              broadcastCount={broadcasts.length}
              onCloseEvent={() => setIsCloseModalOpen(true)}
            />

            <BroadcastStartPanel activeEvent={activeEvent} onCompose={openCompose} />

            {isFormOpen && (
              <BroadcastComposeForm
                activeEvent={activeEvent}
                form={form}
                disasterTypes={disasterTypes}
                severityLevels={severityLevels}
                puroks={puroks}
                statusOptions={statusOptions}
                selectedPurok={selectedPurok}
                selectedPriority={selectedPriority}
                selectedStatuses={selectedStatuses}
                directPuroks={directPuroks}
                recipientNote={recipientNote}
                formError={formError}
                isSaving={isSaving}
                onChange={updateForm}
                onSelectPurok={setSelectedPurok}
                onSelectPriority={setSelectedPriority}
                onAddPurok={addDirectPurok}
                onRemovePurok={removeDirectPurok}
                onToggleStatus={toggleStatus}
                onSubmit={handleSubmit}
                onCancel={() => setIsFormOpen(false)}
              />
            )}
          </main>

          <BroadcastSidePanel activeEvent={activeEvent} broadcasts={broadcasts} />
        </div>
      )}
    </section>
  )
}
