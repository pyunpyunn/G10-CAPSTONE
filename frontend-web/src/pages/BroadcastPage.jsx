import { statusColor as unifiedStatusColor } from '../utils/statusPalette'
import { useCallback, useEffect, useState } from 'react'

import { useNavigate, useSearchParams } from 'react-router-dom'

import { ArrowLeft, Radio, Trash2 } from 'lucide-react'

import { createBroadcast, createDisasterEvent, createDisasterType, createStatusOption, deleteDisasterType, deleteStatusOption, getBroadcastWorkspace, updateDisasterEvent } from '../api/broadcastApi'

import { closeActiveEvent } from '../api/dashboardApi'

import BroadcastComposeForm from '../components/broadcast/BroadcastComposeForm'

import CloseActiveEventModal from '../components/broadcast/CloseActiveEventModal'

import LoadingState from '../components/ui/LoadingState'

import Modal from '../components/ui/Modal'

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

  const [isCloseModalOpen, setIsCloseModalOpen] = useState(false)

  const [isClosingEvent, setIsClosingEvent] = useState(false)

  const [closeError, setCloseError] = useState('')

  const [view, setView] = useState('compose')

  const [savedDraft, setSavedDraft] = useState('')

  const [catalogKind, setCatalogKind] = useState('statuses')

  const [isCatalogOpen, setIsCatalogOpen] = useState(false)

  const [catalogName, setCatalogName] = useState('')

  const [catalogSafety, setCatalogSafety] = useState('unsafe')

  const [isCatalogSaving, setIsCatalogSaving] = useState(false)

  const [pendingCatalogDelete, setPendingCatalogDelete] = useState(null)

  const [clock, setClock] = useState(0)



  const initForm = useCallback((wsData) => {

    const currentWorkspace = wsData

    const nextForm = defaultForm(currentWorkspace)

    const active = currentWorkspace?.active_event || (currentWorkspace?.current_event?.status === 'active' ? currentWorkspace.current_event : null)

    const latest = currentWorkspace?.broadcasts?.[0]

    let nextAreas = []

    let nextStatuses = []



    if (active) {

      nextForm.type_id = active.type_id || nextForm.type_id

      nextForm.severity_id = active.severity_level_id || nextForm.severity_id

      nextForm.event_name = active.name

    }



    if (latest) {

      nextForm.broadcast_title = latest.broadcast_title || ''

      nextForm.message = latest.message || ''

      nextForm.scope_type = latest.scope_type || 'barangay_wide'

      nextForm.estimated_duration = latest.estimated_duration || nextForm.estimated_duration

      nextAreas = Array.isArray(latest.direct_puroks) ? latest.direct_puroks : []

      if (Array.isArray(latest.allowed_statuses) && latest.allowed_statuses.length === 4) nextStatuses = latest.allowed_statuses

    }



    setForm(nextForm)

    setSelectedStatuses([...nextStatuses.slice(0, 4), '', '', '', ''].slice(0, 4))

    setSelectedPurok('')

    setDirectPuroks(nextAreas)

    setFormError('')

    setSavedDraft(JSON.stringify({ form: nextForm, statuses: [...nextStatuses.slice(0, 4), '', '', '', ''].slice(0, 4), areas: nextAreas }))

  }, [])



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

  }, [requestedEventId, initForm])



  const activeEvent = requestedEventId

    ? workspace?.current_event?.status === 'active' ? workspace.current_event : null

    : workspace?.active_event || null

  const broadcasts = workspace?.broadcasts || []

  const disasterTypes = workspace?.disaster_types || []

  const severityLevels = workspace?.severity_levels || []

  const puroks = workspace?.puroks || []

  const statusOptions = workspace?.status_options || []

  const deliveryEventId = activeEvent?.event_id

  const latestBroadcast = broadcasts[0] || null

  const draftFingerprint = JSON.stringify({ form, statuses: selectedStatuses, areas: directPuroks })

  const hasChanges = Boolean(activeEvent && savedDraft && draftFingerprint !== savedDraft)



  useEffect(() => {

    const timer = window.setInterval(() => setClock(Date.now()), 1000)

    return () => window.clearInterval(timer)

  }, [])



  useEffect(() => {

    if (!formError) return undefined

    const timer = window.setTimeout(() => setFormError(''), 3000)

    return () => window.clearTimeout(timer)

  }, [formError])



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

  }



  function handleStatusChange(slot, statusKey) {

    if (slot < 0) return

    setSelectedStatuses((current) => current.map((item, index) => index === slot ? statusKey : item))

  }



  function addDirectPurok(name = selectedPurok) {

    if (!name || directPuroks.some((item) => item.name === name)) {

      return

    }



    setDirectPuroks((current) => [...current, { name }])

    setSelectedPurok('')

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



    const statuses = selectedStatuses.filter(Boolean)

    if (statuses.length !== 4 || new Set(statuses).size !== 4) {

      setFormError('Select exactly four household mobile status buttons.')

      return

    }



    if (!form.event_name.trim() || form.event_name.length > 20) {

      setFormError('Enter a disaster name (20 characters maximum).')

      return

    }



    if (!form.type_id || !form.severity_id) {

      setFormError('Choose a disaster type and severity.')

      return

    }



    if (!form.broadcast_title.trim() || form.broadcast_title.length > 50 || !form.message.trim() || form.message.length > 50) {

      setFormError('Enter a broadcast title and official instruction (50 characters maximum each).')

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

        allowed_statuses: statuses,

        direct_puroks: directPuroks,

      })



      setWorkspace(broadcastResult)

      initForm(broadcastResult)

      const pushDelivery = broadcastResult.push_delivery || {}
      setFormError(`Broadcast posted. Mobile push ${pushDelivery.status || 'submitted'}: ${Number(pushDelivery.sent_count || 0)} of ${Number(pushDelivery.recipient_count || 0)} devices accepted.`)

      setView('live')

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

      setView('compose')

    } catch (closeEventError) {

      setCloseError(apiErrorMessage(closeEventError, 'Unable to close the active event. Please try again.'))

    } finally {

      setIsClosingEvent(false)

    }

  }



  function openCatalog(kind) {

    setCatalogKind(kind)

    setCatalogSafety('unsafe')

    setCatalogName('')

    setFormError('')

    setIsCatalogOpen(true)

  }



  async function handleAddCatalogItem(event) {

    event.preventDefault()

    setFormError('')

    setIsCatalogSaving(true)

    try {

      if (catalogKind === 'statuses') {

        const result = await createStatusOption({ label: catalogName.trim(), safety_basis: catalogSafety })

        setWorkspace((current) => current ? { ...current, status_options: result.status_options || current.status_options } : current)

        setFormError('Household status added.')

      } else {

        const result = await createDisasterType({ name: catalogName.trim() })

        setWorkspace((current) => current ? { ...current, disaster_types: result.disaster_types || current.disaster_types } : current)

        if (result.created_type?.type_id) setForm((current) => ({ ...current, type_id: String(result.created_type.type_id) }))

        setFormError('Disaster type added.')

      }

      setCatalogName('')

    } catch (saveError) {

      setFormError(apiErrorMessage(saveError, `Unable to add this ${catalogKind === 'statuses' ? 'status' : 'disaster type'}.`))

    } finally {

      setIsCatalogSaving(false)

    }

  }



  async function confirmDeleteCatalogItem() {

    if (!pendingCatalogDelete) return

    setIsCatalogSaving(true)

    setFormError('')

    try {

      if (pendingCatalogDelete.kind === 'statuses') {

        const result = await deleteStatusOption(pendingCatalogDelete.key)

        setWorkspace((current) => current ? { ...current, status_options: result.status_options || current.status_options } : current)

        setSelectedStatuses((current) => current.map((key) => key === pendingCatalogDelete.key ? '' : key))

      } else {

        const result = await deleteDisasterType(pendingCatalogDelete.id)

        setWorkspace((current) => current ? { ...current, disaster_types: result.disaster_types || current.disaster_types } : current)

        if (String(form.type_id) === String(pendingCatalogDelete.id)) {

          setForm((current) => ({ ...current, type_id: String(result.disaster_types?.[0]?.type_id || '') }))

        }

      }

      setFormError(`${pendingCatalogDelete.label} deleted.`)

      setPendingCatalogDelete(null)

    } catch (deleteError) {

      setFormError(apiErrorMessage(deleteError, `Unable to delete ${pendingCatalogDelete.label}.`))

      setPendingCatalogDelete(null)

    } finally {

      setIsCatalogSaving(false)

    }

  }



  return (

    <section className="page broadcast-page active">

      <div className="bc-console-nav">

        <div className="bc-view-tabs" role="tablist" aria-label="Broadcast views">

          <button type="button" role="tab" aria-selected={view === 'compose'} className={view === 'compose' ? 'is-active' : ''} onClick={() => setView('compose')}>Compose</button>

          <button type="button" role="tab" aria-selected={view === 'live'} className={view === 'live' ? 'is-active' : ''} onClick={() => setView('live')}>Live View</button>

        </div>

        <button className="bc-back-button" type="button" onClick={() => navigate('/dashboard')} title="Return to Dashboard"><ArrowLeft size={17} />Return to Dashboard</button>

      </div>

      <div id="toast" className={formError ? 's' : ''} role="alert" aria-live="assertive">{formError}</div>



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



      {!isLoading && !error && workspace && view === 'compose' && (

        <div className="bc-compose-layout">

          <main className="bc-editor-wrap">

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

              householdCount={workspace.household_count || 0}

              isSaving={isSaving}

              hasChanges={hasChanges}

              onChange={updateForm}

              onSelectPurok={setSelectedPurok}

              onAddPurok={addDirectPurok}

              onRemovePurok={removeDirectPurok}

              onStatusChange={handleStatusChange}

              onAddStatus={() => openCatalog('statuses')}

              onManageDisasterTypes={() => openCatalog('types')}

              onSubmit={handleSubmit}

            />

          </main>



          <BroadcastPreview

            activeEvent={activeEvent}

            latestBroadcast={latestBroadcast}

            form={form}

            directPuroks={directPuroks}

            puroks={puroks}

            householdCount={workspace.household_count || 0}

            severityLevels={severityLevels}

            selectedStatuses={selectedStatuses}

            statusOptions={statusOptions}

          />

        </div>

      )}



      {!isLoading && !error && workspace && view === 'live' && (

        <BroadcastLiveView

          activeEvent={activeEvent}

          latestBroadcast={latestBroadcast}

          broadcasts={broadcasts}

          clock={clock}

          onEdit={() => setView('compose')}

          onClose={() => setIsCloseModalOpen(true)}

        />

      )}



      <Modal

        title={catalogKind === 'statuses' ? 'Manage household statuses' : 'Manage disaster types'}

        isOpen={isCatalogOpen}

        onClose={() => !isCatalogSaving && setIsCatalogOpen(false)}

        footer={(

          <>

            <button className="btn btn-secondary btn-sm" type="button" disabled={isCatalogSaving} onClick={() => setIsCatalogOpen(false)}>Close</button>

            <button className="btn btn-primary btn-sm" type="submit" form="bc-catalog-form" disabled={isCatalogSaving || !catalogName.trim()}>{isCatalogSaving ? 'Saving...' : catalogKind === 'statuses' ? 'Add status' : 'Add disaster type'}</button>

          </>

        )}

      >

        <div className="bc-catalog-manager">

          <p>{catalogKind === 'statuses' ? 'Saved household status options' : 'Saved disaster types'}</p>

          <div className="bc-catalog-list">

            {(catalogKind === 'statuses' ? statusOptions : disasterTypes).map((item) => {

              const itemKey = catalogKind === 'statuses' ? item.key : item.type_id

              const itemLabel = catalogKind === 'statuses' ? item.label : item.type_name

              return (

                <div className="bc-catalog-row" key={itemKey}>

                  <span>{itemLabel}</span>

                  <button

                    type="button"

                    className="bc-catalog-delete"

                    aria-label={`Delete ${itemLabel}`}

                    disabled={isCatalogSaving}

                    onClick={() => setPendingCatalogDelete({ kind: catalogKind, key: item.key, id: item.type_id, label: itemLabel })}

                  ><Trash2 size={15} /></button>

                </div>

              )

            })}

            {(catalogKind === 'statuses' ? statusOptions : disasterTypes).length === 0 && <div className="bc-catalog-empty">No saved items.</div>}

          </div>

          <form id="bc-catalog-form" onSubmit={handleAddCatalogItem} className="bc-add-status-form">

            <label><span>{catalogKind === 'statuses' ? 'New status name' : 'New disaster type name'}</span><input autoFocus value={catalogName} maxLength={catalogKind === 'statuses' ? 16 : 100} onChange={(event) => setCatalogName(event.target.value)} required /></label>

            {catalogKind === 'statuses' && <label><span>Record reports as</span><select value={catalogSafety} onChange={(event) => setCatalogSafety(event.target.value)} required><option value="safe">Safe</option><option value="unsafe">Unsafe</option></select></label>}

          </form>

        </div>

      </Modal>



      <Modal

        title="Confirm deletion"

        isOpen={Boolean(pendingCatalogDelete)}

        onClose={() => !isCatalogSaving && setPendingCatalogDelete(null)}

        footer={(

          <>

            <button className="btn btn-secondary btn-sm" type="button" disabled={isCatalogSaving} onClick={() => setPendingCatalogDelete(null)}>Cancel</button>

            <button className="btn btn-warning btn-sm" type="button" disabled={isCatalogSaving} onClick={confirmDeleteCatalogItem}>{isCatalogSaving ? 'Deleting...' : 'Delete'}</button>

          </>

        )}

      >

        <p className="bc-catalog-confirm">Delete <strong>{pendingCatalogDelete?.label}</strong>? Only this catalog option will be removed; existing household records will not be changed.</p>

      </Modal>

    </section>

  )

}



function BroadcastPreview({ activeEvent, latestBroadcast, form, severityLevels, selectedStatuses, statusOptions }) {

  const severity = severityLevels.find((item) => String(item.severity_id) === String(form.severity_id))

  const statusKeys = selectedStatuses.filter(Boolean)



  return (

    <aside className="bc-preview-column">

      <section className="bc-push-preview" style={{ '--broadcast-severity': severityTone(severity?.severity_key) }}>

        <div className="bc-preview-label">Mobile push preview</div>

        <div className="bc-notification-preview">

          <span className="bc-notification-icon"><Radio size={17} /></span>

          <div><strong>{form.broadcast_title || 'Broadcast title'}</strong><p>{form.message || 'Official instruction'}</p></div>

          <small>now</small>

        </div>

        <div className="bc-preview-statuses">

          {statusKeys.map((key) => {

            const item = statusOptions.find((option) => option.key === key)

            return <span key={key}><i style={{ background: statusColor(item?.safety_basis || key) }} />{item?.label || key}</span>

          })}

        </div>

      </section>

      <section className="bc-event-meta">

        <div><span>Event ID</span><strong>{activeEvent?.event_id || 'Generated on post'}</strong></div>

        <div><span>Date created</span><strong>{formatDate(activeEvent?.started_at) || 'On declaration'}</strong></div>

        <div><span>Time started</span><strong>{activeEvent?.started_time || 'On declaration'}</strong></div>

        <div><span>Last updated</span><strong>{latestBroadcast?.sent_time || activeEvent?.started_time || 'Not posted'}</strong></div>

      </section>

    </aside>

  )

}



function BroadcastLiveView({ activeEvent, latestBroadcast, broadcasts, clock, onEdit, onClose }) {

  const title = latestBroadcast?.broadcast_title || (activeEvent ? activeEvent.name : '')

  const instruction = latestBroadcast?.message || ''

  const elapsed = activeEvent?.started_at ? formatElapsed(clock - new Date(activeEvent.started_at).getTime()) : '00:00:00'

  const lastUpdated = latestBroadcast?.sent_at ? formatAgo(clock - new Date(latestBroadcast.sent_at).getTime()) : 'Not updated'

  const statuses = latestBroadcast?.allowed_statuses || []
  const statusLabels = statuses.map((key) => latestBroadcast?.status_options?.find((option) => option.key === key)?.label || key.replace(/_/g, ' '))

  const area = latestBroadcast?.target_area || latestBroadcast?.scope_label || 'Barangay-wide'

  const tone = severityTone(latestBroadcast?.severity_key || activeEvent?.severity_key)

  const total = latestBroadcast?.recipient_count ?? 0

  const reached = Number(latestBroadcast?.accepted_count || 0)



  return (

    <main className="bc-live-workspace">

      <div className="bc-live-heading">

        <div><h1>Live View</h1><span className={`bc-mode-tag ${activeEvent ? 'is-live' : ''}`}>{activeEvent ? 'ON AIR' : 'STANDBY'}</span></div>

        <div className="bc-live-actions">

          {activeEvent && <button className="bc-quiet-button" type="button" onClick={onEdit}>Edit broadcast</button>}

          {activeEvent && <button className="bc-close-button" type="button" onClick={onClose}>Close disaster alert</button>}

        </div>

      </div>

      <div className="bc-live-grid">

        <section className={`bc-news-panel ${activeEvent ? '' : 'is-standby'}`} style={{ '--broadcast-severity': tone }}>

          {activeEvent ? (

            <>

              <header className="bc-news-top"><span className="bc-live-badge"><i /> LIVE</span><span className="bc-event-code">{activeEvent.event_id}</span><strong className="bc-elapsed-clock">{elapsed}</strong></header>

              <div className="bc-news-main">

                <span className="bc-news-kicker">{activeEvent.type_name || 'DISASTER RESPONSE'}</span>

                <h2>{title}</h2>

                <p>{instruction}</p>

              </div>

              <div className="bc-lower-third"><strong>{activeEvent.severity_label || 'Active'}</strong><span>{activeEvent.name}</span><small>{area}</small></div>

              <div className="bc-news-ticker"><span>{[title, instruction, activeEvent.type_name, activeEvent.name, area].filter(Boolean).join(' | ')}    {[title, instruction, activeEvent.type_name, activeEvent.name, area].filter(Boolean).join(' | ')}</span></div>

            </>

          ) : <div className="bc-standby-message"><Radio size={40} /><h2>No active broadcast</h2><p>Standing by for a declaration</p></div>}

        </section>

        <aside className="bc-live-stats" style={{ '--broadcast-severity': tone }}>

          <StatTile label="Status options pushed" value={statuses.length ? statusLabels.join(' · ') : '—'} />

          <StatTile label="Estimated duration" value={latestBroadcast?.estimated_duration || '—'} />

          <StatTile label="Push accepted / audience" value={`${reached.toLocaleString()} / ${Number(total).toLocaleString()}`} />

          <StatTile label="Last updated" value={lastUpdated} />

        </aside>

        <section className="bc-log-table-wrap">

          <div className="bc-log-table-heading"><h2>Broadcast Logs</h2><span>{broadcasts.length} records</span></div>

          <div className="bc-log-overflow"><table className="bc-log-table"><thead><tr><th>Broadcast title</th><th>Official instruction</th><th>Disaster</th><th>Declared</th><th>Affected areas</th><th>Recipients / push</th></tr></thead>

            <tbody>{broadcasts.length ? broadcasts.map((item) => (

              <tr key={item.broadcast_id}>

                <td><strong>{item.broadcast_title}</strong></td>

                <td>{item.message}</td>

                <td>{activeEvent?.name || 'Disaster event'}</td>

                <td>{item.sent_at || item.sent_time || '—'}</td>

                <td>{item.target_area || item.scope_label || 'Barangay-wide'}</td>

                <td>{Number(item.recipient_count || 0).toLocaleString()} <small>OneSignal: {pushDeliveryLabel(item.push_status)}</small>{(item.provider_ids || []).map((id) => <small key={id}>Message ID: {id}</small>)}{(item.push_errors || []).map((error, index) => <small key={index}>{error}</small>)}</td>

              </tr>

            )) : <tr><td className="bc-log-empty" colSpan="6">No broadcast records yet.</td></tr>}</tbody>

          </table></div>

        </section>

      </div>

    </main>

  )

}



function StatTile({ label, value }) {

  return <section className="bc-stat-tile"><span>{label}</span><strong>{value}</strong></section>

}



function pushDeliveryLabel(status) {

  return ({ pending_mobile_push: 'Queued', onesignal_sent: 'Accepted', onesignal_partial: 'Partially accepted', onesignal_failed: 'Failed - see delivery details', onesignal_no_recipients: 'No subscribed devices', onesignal_not_configured: 'Not configured' })[status] || 'Pending'

}



function statusColor(key) { return unifiedStatusColor(key) }



function severityTone(key) {

  return unifiedStatusColor(key || 'warning')

}



function formatElapsed(milliseconds) {

  const seconds = Math.max(0, Math.floor(milliseconds / 1000))

  return [Math.floor(seconds / 3600), Math.floor((seconds % 3600) / 60), seconds % 60].map((value) => String(value).padStart(2, '0')).join(':')

}



function formatAgo(milliseconds) {

  const seconds = Math.max(0, Math.floor(milliseconds / 1000))

  if (seconds < 5) return 'Just now'

  if (seconds < 60) return `${seconds}s ago`

  return `${Math.floor(seconds / 60)}m ago`

}



function formatDate(value) {

  if (!value) return ''

  const date = new Date(value)

  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString()

}

