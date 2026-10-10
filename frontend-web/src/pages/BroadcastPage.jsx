import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { AlertOctagon, Edit3, MoreVertical, PlusCircle, RefreshCcw, RotateCcw } from 'lucide-react'
import {
  createBroadcast,
  createDisasterEvent,
  getBroadcastWorkspace,
  updateDisasterEvent,
} from '../api/broadcastApi'
import { closeActiveEvent } from '../api/dashboardApi'
import BroadcastComposeForm from '../components/broadcast/BroadcastComposeForm'
import BroadcastSidePanel from '../components/broadcast/BroadcastSidePanel'
import CloseActiveEventModal from '../components/broadcast/CloseActiveEventModal'
import UpdateActiveEventModal from '../components/broadcast/UpdateActiveEventModal'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import {
  apiErrorMessage,
  defaultForm,
  getRecipientNote,
  targetAreaLabel,
} from '../utils/broadcastHelpers'

export default function BroadcastPage() {
  const [searchParams] = useSearchParams()
  const requestedEventId = searchParams.get('event_id')
  const [workspace, setWorkspace] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [form, setForm] = useState(defaultForm())
  const [selectedStatuses, setSelectedStatuses] = useState([])
  const [selectedPurok, setSelectedPurok] = useState('')
  const [selectedPriority, setSelectedPriority] = useState('high')
  const [directPuroks, setDirectPuroks] = useState([])
  const [formError, setFormError] = useState('')
  const [isSaving, setIsSaving] = useState(false)
  const [formNotice, setFormNotice] = useState('')

  const [isCloseModalOpen, setIsCloseModalOpen] = useState(false)
  const [isClosingEvent, setIsClosingEvent] = useState(false)
  const [closeError, setCloseError] = useState('')

  const [isUpdateModalOpen, setIsUpdateModalOpen] = useState(false)
  const [isUpdatingEvent, setIsUpdatingEvent] = useState(false)
  const [updateError, setUpdateError] = useState('')

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
  const evacuationCenters = workspace?.evacuation_centers || []
  const affectedAreas = workspace?.affected_areas || []

  const selectedType = disasterTypes.find((type) => String(type.type_id) === String(form.type_id))
  const currentTypeName = activeEvent?.type_name || selectedType?.type_name || 'Disaster event'
  const recipientNote = useMemo(
    () => getRecipientNote(currentTypeName, form.scope_type, directPuroks),
    [currentTypeName, form.scope_type, directPuroks],
  )

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
      nextForm.broadcast_title = `${active.type_name} update`
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
      setFormError('Add up to 5 direct-impact puroks only.')
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

    if (!form.broadcast_title.trim() || !form.message.trim()) {
      setFormError('Broadcast title and official instruction message are required.')
      return
    }

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

      if (!eventId) {
        const eventResult = await createDisasterEvent({
          name: form.event_name,
          type_id: form.type_id,
          severity_level_id: form.severity_id,
          started_at: `${form.started_date} ${form.started_time}`,
        })

        eventId = eventResult.active_event.event_id
      } else {
        await updateDisasterEvent(eventId, {
          name: form.event_name.trim(),
          type_id: form.type_id,
          severity_level_id: form.severity_id,
        })
      }

      const broadcastResult = await createBroadcast(eventId, {
        broadcast_title: form.broadcast_title,
        message: form.message,
        severity_id: form.severity_id,
        scope_type: form.scope_type,
        target_area: targetAreaLabel(form.scope_type, directPuroks),
        estimated_duration: form.estimated_duration,
        attach_route: form.attach_route,
        attached_evacuation_center_id: form.attached_evacuation_center_id || null,
        attached_affected_area_ids: form.attached_affected_area_ids || [],
        allowed_statuses: selectedStatuses,
        direct_puroks: directPuroks,
      })

      const updatedWorkspace = broadcastResult.workspace || broadcastResult
      setWorkspace(updatedWorkspace)
      initForm(updatedWorkspace)
      setFormNotice(activeEvent
        ? 'Disaster updated and broadcast posted. OneSignal push to subscribed Android devices is queued; check Mobile push in the broadcast log for the result.'
        : 'Disaster declared and broadcast posted. OneSignal push to subscribed Android devices is queued; check Mobile push in the broadcast log for the result.')
    } catch (saveError) {
      setFormError(apiErrorMessage(saveError, 'Unable to save this broadcast. Please check all entries and try again.'))
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

  async function handleUpdateActiveEvent(updatedData) {
    if (!activeEvent) return

    setIsUpdatingEvent(true)
    setUpdateError('')

    try {
      await updateDisasterEvent(activeEvent.event_id, updatedData)
      setIsUpdateModalOpen(false)
      await loadWorkspace()
    } catch (err) {
      setUpdateError(apiErrorMessage(err, 'Unable to update active disaster event.'))
    } finally {
      setIsUpdatingEvent(false)
    }
  }

  return (
    <section className="page broadcast-page active">
      <PageHeader
        title="Disaster Broadcasting"
        actions={
          <>
            <button className="btn btn-secondary btn-sm" type="button" onClick={() => initForm()}>
              <RotateCcw size={14} />
              Reset Form
            </button>
            <button className="btn btn-secondary btn-sm" type="button" onClick={loadWorkspace}>
              <RefreshCcw size={14} />
              Refresh
            </button>
            <HeaderActionMenu
              activeEvent={activeEvent}
              onCloseActiveEvent={() => setIsCloseModalOpen(true)}
              onUpdateActiveEvent={() => setIsUpdateModalOpen(true)}
              onDeclareActiveEvent={() => initForm()}
            />
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

      <UpdateActiveEventModal
        activeEvent={activeEvent}
        disasterTypes={disasterTypes}
        severityLevels={severityLevels}
        isOpen={isUpdateModalOpen}
        isUpdating={isUpdatingEvent}
        updateError={updateError}
        onClose={() => setIsUpdateModalOpen(false)}
        onConfirm={handleUpdateActiveEvent}
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
              evacuationCenters={evacuationCenters}
              affectedAreas={affectedAreas}
              statusOptions={statusOptions}
              selectedPurok={selectedPurok}
              selectedPriority={selectedPriority}
              selectedStatuses={selectedStatuses}
              directPuroks={directPuroks}
              recipientNote={recipientNote}
              formError={formError}
              formNotice={formNotice}
              isSaving={isSaving}
              onChange={updateForm}
              onSelectPurok={setSelectedPurok}
              onSelectPriority={setSelectedPriority}
              onAddPurok={addDirectPurok}
              onRemovePurok={removeDirectPurok}
              onToggleStatus={toggleStatus}
              onSubmit={handleSubmit}
              onCancel={() => initForm()}
            />
          </main>

          <BroadcastSidePanel
            activeEvent={activeEvent}
            broadcasts={broadcasts}
            evacuationCenters={evacuationCenters}
            affectedAreas={affectedAreas}
            onCloseActiveEvent={() => setIsCloseModalOpen(true)}
            onUpdateActiveEvent={() => setIsUpdateModalOpen(true)}
            onDeclareActiveEvent={() => initForm()}
          />
        </div>
      )}
    </section>
  )
}

function HeaderActionMenu({ activeEvent, onCloseActiveEvent, onUpdateActiveEvent, onDeclareActiveEvent }) {
  const [isOpen, setIsOpen] = useState(false)
  const menuRef = useRef(null)

  useEffect(() => {
    function handleClickOutside(event) {
      if (menuRef.current && !menuRef.current.contains(event.target)) {
        setIsOpen(false)
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  return (
    <div className="bc-card-head-actions" ref={menuRef} style={{ position: 'relative' }}>
      <button
        className="btn btn-secondary btn-sm"
        type="button"
        aria-label="Disaster options"
        aria-expanded={isOpen}
        onClick={() => setIsOpen((prev) => !prev)}
      >
        <MoreVertical size={16} />
      </button>

      {isOpen && (
        <div className="bc-card-dropdown" style={{ right: 0, top: 'calc(100% + 6px)' }}>
          {activeEvent ? (
            <>
              <button
                className="bc-dropdown-item danger"
                type="button"
                onClick={() => {
                  setIsOpen(false)
                  onCloseActiveEvent?.()
                }}
              >
                <AlertOctagon size={14} />
                <span>CLOSE ACTIVE EVENT</span>
              </button>
              <button
                className="bc-dropdown-item"
                type="button"
                onClick={() => {
                  setIsOpen(false)
                  onUpdateActiveEvent?.()
                }}
              >
                <Edit3 size={14} />
                <span>UPDATE ACTIVE EVENT</span>
              </button>
            </>
          ) : (
            <button
              className="bc-dropdown-item primary"
              type="button"
              onClick={() => {
                setIsOpen(false)
                onDeclareActiveEvent?.()
              }}
            >
              <PlusCircle size={14} />
              <span>DECLARE ACTIVE EVENT</span>
            </button>
          )}
        </div>
      )}
    </div>
  )
}
