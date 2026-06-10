import { useEffect, useState } from 'react'
import {
  Clock,
  RefreshCcw,
  Route,
} from 'lucide-react'
import { completeDispatch, createDispatch, getDispatchDashboard, updateDispatch } from '../api/dispatchApi'
import DispatchModalForm from '../components/dispatch/DispatchModalForm'
import DispatchSidePanel from '../components/dispatch/DispatchSidePanel'
import DispatchSummary from '../components/dispatch/DispatchSummary'
import DispatchTeamGrid from '../components/dispatch/DispatchTeamGrid'
import LoadingState from '../components/ui/LoadingState'
import Modal from '../components/ui/Modal'
import PageHeader from '../components/ui/PageHeader'
import {
  buildRequestBody,
  defaultForm,
  emptySummary,
  firstAssignmentOption,
  getSaveMessage,
  teamFilters,
} from '../utils/dispatchHelpers'

export default function RescueDispatchPage() {
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [teamFilter, setTeamFilter] = useState('all')
  const [dispatchFilter, setDispatchFilter] = useState('all')
  const [searchText, setSearchText] = useState('')
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editingDispatch, setEditingDispatch] = useState(null)
  const [selectedRiskId, setSelectedRiskId] = useState('')
  const [assignmentOption, setAssignmentOption] = useState('')
  const [form, setForm] = useState(defaultForm())
  const [formError, setFormError] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    let ignore = false

    async function loadPage() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getDispatchDashboard({
          status: dispatchFilter,
          search: searchText.trim(),
          per_page: 20,
        })

        if (!ignore) {
          setPayload(data)
        }
      } catch {
        if (!ignore) {
          setError('Rescue dispatch records cannot be loaded right now. Please check the backend or database connection.')
        }
      } finally {
        if (!ignore) {
          setIsLoading(false)
        }
      }
    }

    loadPage()

    return () => {
      ignore = true
    }
  }, [dispatchFilter, searchText])

  async function loadDispatch() {
    setIsLoading(true)
    setError('')

    try {
      const data = await getDispatchDashboard({
        status: dispatchFilter,
        search: searchText.trim(),
        per_page: 20,
      })
      setPayload(data)
    } catch {
      setError('Rescue dispatch records cannot be loaded right now. Please check the backend or database connection.')
    } finally {
      setIsLoading(false)
    }
  }

  const teams = payload?.teams || []
  const responders = payload?.responders || []
  const riskAreas = payload?.risk_areas || []
  const dispatches = payload?.dispatches?.data || []
  const summary = payload?.summary || emptySummary()
  const hasActiveEvent = Boolean(payload?.active_event)
  const filteredTeams = teamFilter === 'all'
    ? teams
    : teams.filter((team) => team.status_key === teamFilter)

  function firstDispatchableHousehold(area) {
    return area?.recommended_households?.find((household) => household.is_available_for_dispatch && household.has_geotag)
      || area?.households?.find((household) => household.is_available_for_dispatch && household.has_geotag)
      || null
  }

  function openNewDispatch() {
    const firstRisk = riskAreas[0]
    const firstOption = firstAssignmentOption(teams, responders)
    const firstHousehold = firstDispatchableHousehold(firstRisk)

    setEditingDispatch(null)
    setSelectedRiskId(firstRisk?.id || '')
    setAssignmentOption(firstOption)
    setForm({
      ...defaultForm(),
      assigned_area: firstRisk?.area_name || '',
      household_id: firstHousehold?.household_id || '',
      households_to_cover: firstRisk?.to_cover || 0,
      priority_level: firstRisk?.priority || 'high',
    })
    setFormError('')
    setIsModalOpen(true)
  }

  function openUpdateDispatch(team) {
    const dispatch = dispatches.find((item) => item.assignment_id === team.active_assignment_id)

    if (!dispatch) {
      setAssignmentOption(team.active_responder_id ? `responder:${team.active_responder_id}` : '')
      setForm({
        ...defaultForm(),
        assigned_area: team.assigned_area === 'No dispatch area yet' ? '' : team.assigned_area,
        households_to_cover: team.assigned_households || 0,
      })
      setEditingDispatch(null)
      setIsModalOpen(true)
      return
    }

    setEditingDispatch(dispatch)
    setSelectedRiskId('')
    setAssignmentOption(dispatch.team_id ? `team:${dispatch.team_id}` : `responder:${dispatch.responder_id}`)
    setForm({
      assigned_area: dispatch.assigned_area || '',
      household_id: dispatch.household_id || '',
      households_to_cover: dispatch.households_to_cover || 0,
      responder_count: dispatch.responder_count || 1,
      priority_level: dispatch.priority_level || 'monitor',
      status: dispatch.status?.key || 'dispatched',
      dispatch_notes: dispatch.dispatch_notes || '',
      route_notes: dispatch.route_notes || '',
      safe_count: dispatch.outcomes?.safe || 0,
      evacuated_count: dispatch.outcomes?.evacuated || 0,
      unsafe_count: dispatch.outcomes?.unsafe || 0,
      injured_count: dispatch.outcomes?.injured || 0,
      missing_count: dispatch.outcomes?.missing || 0,
      pending_count: dispatch.outcomes?.pending || 0,
      outcome_notes: dispatch.outcomes?.notes || '',
    })
    setFormError('')
    setIsModalOpen(true)
  }

  function selectRiskArea(area) {
    const firstHousehold = firstDispatchableHousehold(area)

    setSelectedRiskId(area.id)
    setForm((current) => ({
      ...current,
      assigned_area: area.area_name,
      household_id: firstHousehold?.household_id || '',
      households_to_cover: area.to_cover,
      priority_level: area.priority,
    }))
  }

  function selectRiskHousehold(area, household) {
    setSelectedRiskId(area.id)
    setForm((current) => ({
      ...current,
      assigned_area: area.area_name,
      household_id: household.household_id,
      households_to_cover: Math.max(1, current.households_to_cover || area.to_cover || 1),
      priority_level: household.priority_level || area.priority || current.priority_level,
    }))
  }

  function closeModal() {
    if (isSaving) {
      return
    }

    setIsModalOpen(false)
    setEditingDispatch(null)
    setFormError('')
  }

  async function submitDispatch(event) {
    event.preventDefault()
    setFormError('')

    if (!hasActiveEvent) {
      setFormError('Dispatch assignment requires an active disaster event.')
      return
    }

    if (!assignmentOption) {
      setFormError('Select a team or responder first.')
      return
    }

    if (!form.assigned_area.trim()) {
      setFormError('Assigned area is required.')
      return
    }

    if (!editingDispatch && !form.household_id) {
      setFormError('Select a household with GPS from the affected area list. This is required for routed dispatch.')
      return
    }

    const requestBody = buildRequestBody(assignmentOption, form)
    setIsSaving(true)

    try {
      if (editingDispatch && form.status === 'completed') {
        await completeDispatch(editingDispatch.assignment_id, requestBody)
      } else if (editingDispatch) {
        await updateDispatch(editingDispatch.assignment_id, requestBody)
      } else {
        await createDispatch(requestBody)
      }

      setIsModalOpen(false)
      setEditingDispatch(null)
      await loadDispatch()
    } catch (saveError) {
      setFormError(getSaveMessage(saveError))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <section className="page active dispatch-page">
      <PageHeader
        title="Rescue Dispatch"
        actions={
          <>
            <button className="btn btn-secondary btn-sm" type="button" onClick={loadDispatch}>
              <RefreshCcw size={14} />
              Refresh
            </button>
            <button className="btn btn-secondary btn-sm" type="button" onClick={() => setDispatchFilter('all')}>
              <Clock size={14} />
              Dispatch history
            </button>
            <button className="btn btn-primary btn-sm" type="button" disabled={!hasActiveEvent} onClick={openNewDispatch}>
              <Route size={14} />
              New dispatch
            </button>
          </>
        }
      />

      {isLoading && <LoadingState />}
      {error && <div className="form-error">{error}</div>}

      {!isLoading && !error && (
        <>
          {!hasActiveEvent && (
            <div className="standby-strip">
              <strong>No active disaster event</strong>
              <span>New dispatch assignments are enabled after HQ/Admin declares an active event.</span>
            </div>
          )}

          <DispatchSummary summary={summary} />

          <div className="dp-dispatch-layout">
            <DispatchSidePanel
              teams={teams}
              responders={responders}
              logs={payload?.activity_log || []}
              dispatches={dispatches}
              filter={dispatchFilter}
              setFilter={setDispatchFilter}
              searchText={searchText}
              setSearchText={setSearchText}
              onSearch={loadDispatch}
            />

            <main className="dp-main-column dp-team-side-panel">
              <div className="dp-team-toolbar">
                <span>Team status cards</span>
                <select className="dp-filter-select" value={teamFilter} onChange={(event) => setTeamFilter(event.target.value)} aria-label="Filter team status cards">
                  {teamFilters.map((filter) => (
                    <option value={filter.key} key={filter.key}>{filter.label}</option>
                  ))}
                </select>
              </div>

              <DispatchTeamGrid teams={filteredTeams} onOpenUpdate={openUpdateDispatch} onOpenNew={openNewDispatch} />
            </main>
          </div>
        </>
      )}

      <Modal
        title={editingDispatch ? 'Update Dispatch' : 'New Dispatch'}
        isOpen={isModalOpen}
        onClose={closeModal}
        className="dp-dispatch-modal"
        footer={
          <>
            <button className="btn btn-secondary" type="button" disabled={isSaving} onClick={closeModal}>Cancel</button>
            <button className="btn btn-primary" type="submit" form="dispatchForm" disabled={isSaving || !hasActiveEvent}>
              {isSaving ? 'Saving...' : editingDispatch ? 'Save update' : 'Dispatch responders'}
            </button>
          </>
        }
      >
        <DispatchModalForm
          editingDispatch={editingDispatch}
          form={form}
          setForm={setForm}
          formError={formError}
          assignmentOption={assignmentOption}
          setAssignmentOption={setAssignmentOption}
          teams={teams}
          responders={responders}
          riskAreas={riskAreas}
          selectedRiskId={selectedRiskId}
          onSelectRiskArea={selectRiskArea}
          onSelectRiskHousehold={selectRiskHousehold}
          onSubmit={submitDispatch}
        />
      </Modal>
    </section>
  )
}
