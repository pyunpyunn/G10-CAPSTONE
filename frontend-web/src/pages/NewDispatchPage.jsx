import { useCallback, useState } from 'react'
import { ArrowLeft } from 'lucide-react'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
import { completeDispatch, createDispatch, getDispatchDashboard, getDispatch, updateDispatch } from '../api/dispatchApi'
import DispatchModalForm from '../components/dispatch/DispatchModalForm'
import { useModuleData } from '../utils/useModuleData'
import LoadingState from '../components/ui/LoadingState'
import PageHeader from '../components/ui/PageHeader'
import {
  buildRequestBody,
  defaultForm,
  firstAssignmentOption,
  getSaveMessage,
} from '../utils/dispatchHelpers'

export default function NewDispatchPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const { assignmentId } = useParams()
  const editingId = assignmentId || location.state?.dispatch?.assignment_id
  const returnTo = location.pathname.startsWith('/rescue-management') || location.state?.returnTo === '/rescue-management' ? '/rescue-management' : '/dispatch'
  const loader = useCallback(async () => {
    const [workspace, detail] = await Promise.all([getDispatchDashboard({ per_page: 1 }), editingId ? getDispatch(editingId) : Promise.resolve(null)])
    return { workspace, dispatch: detail?.dispatch || null }
  }, [editingId])
  const { data, error, loading, refresh } = useModuleData(loader)
  if (loading) return <main className="ops-page new-dispatch-page"><LoadingState /></main>
  if (error) return <main className="ops-page new-dispatch-page"><PageHeader title={editingId ? 'Update Dispatch Assignment' : 'Create New Dispatch Assignment'} />
    <div className="form-error" role="alert">{error}</div><button className="btn btn-secondary" type="button" onClick={refresh}>Retry</button>
    <button className="btn btn-secondary" type="button" onClick={() => navigate(returnTo)}>Back to Rescue Management</button></main>
  return <DispatchAssignmentEditor key={`${editingId || 'new'}-${location.key}`} payload={data.workspace} editingDispatch={data.dispatch} returnTo={returnTo} />
}

function DispatchAssignmentEditor({ payload, editingDispatch, returnTo }) {
  const location = useLocation()
  const navigate = useNavigate()
  const teams = payload?.teams || []
  const riskAreas = payload?.risk_areas || []
  const hasActiveEvent = Boolean(payload?.active_event)
  const isClosed = ['completed', 'cancelled'].includes(editingDispatch?.status?.key)
  const initial = initialAssignment(payload, editingDispatch, location.state)
  const [selectedRiskId, setSelectedRiskId] = useState(initial.selectedRiskId)
  const [assignmentOption, setAssignmentOption] = useState(initial.assignmentOption)
  const [form, setForm] = useState(initial.form)
  const [formError, setFormError] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  function selectRiskArea(area) {
    const firstHousehold = firstDispatchableHousehold(area)

    setSelectedRiskId(area.id)
    setForm((current) => ({
      ...current,
      assigned_area: area.area_name,
      household_id: firstHousehold?.household_id || '',
      target_household: firstHousehold,
      households_to_cover: firstHousehold ? 1 : 0,
      safe_count: 0,
      evacuated_count: 0,
      injured_count: 0,
      missing_count: 0,
      unsafe_count: 0,
      pending_count: 0,
      priority_level: area.priority,
    }))
  }

  function selectRiskHousehold(area, household) {
    setSelectedRiskId(area.id)
    setForm((current) => ({
      ...current,
      assigned_area: area.area_name,
      household_id: household.household_id,
      target_household: household,
      households_to_cover: 1,
      priority_level: household.priority_level || area.priority || current.priority_level,
    }))
  }

  async function submitDispatch(event) {
    event.preventDefault()
    setFormError('')

    if (isClosed) {
      setFormError('This assignment is already closed. Return to Rescue Management and refresh the list.')
      return
    }

    if (!editingDispatch && !hasActiveEvent) {
      setFormError('Dispatch assignment requires an active disaster event.')
      return
    }

    if (!editingDispatch && !assignmentOption) {
      setFormError('Select an available team first.')
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

    const selectedTeam = teams.find((team) => String(team.team_id) === String(assignmentOption.split(':')[1]))
    if (!editingDispatch && (!form.responder_count || form.responder_count < 1 || form.responder_count > (selectedTeam?.available_responder_count || 0))) {
      setFormError('Choose a number of rescuers within the team’s current available count.')
      return
    }

    setIsSaving(true)

    try {
      const requestBody = buildRequestBody(assignmentOption, form)
      if (editingDispatch && form.status === 'completed') {
        await completeDispatch(editingDispatch.assignment_id, requestBody)
      } else if (editingDispatch) {
        await updateDispatch(editingDispatch.assignment_id, requestBody)
      } else {
        await createDispatch(requestBody)
      }
      navigate(returnTo, { replace: true })
    } catch (saveError) {
      setFormError(getSaveMessage(saveError))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <main className="ops-page new-dispatch-page">
      <PageHeader
        title={editingDispatch ? 'Update Dispatch Assignment' : 'Create New Dispatch Assignment'}
        actions={(
          <button className="new-dispatch-back" type="button" onClick={() => navigate(returnTo)}>
            <ArrowLeft size={15} /> Back to dispatch
          </button>
        )}
      />

      {!editingDispatch && !hasActiveEvent && (
        <div className="standby-strip">
          <strong>No active disaster event</strong>
          <span>New dispatch assignments are disabled until HQ/Admin declares an active event.</span>
        </div>
      )}

      {isClosed && <div className="form-error">This assignment is already completed or cancelled. It cannot be updated.</div>}
      <section className="new-dispatch-shell" aria-label="New dispatch form">
        <fieldset className="response-assignment-fields" disabled={isClosed || isSaving}>
        <DispatchModalForm
          editingDispatch={editingDispatch}
          form={form}
          setForm={setForm}
          formError={formError}
          assignmentOption={assignmentOption}
          setAssignmentOption={setAssignmentOption}
          teams={teams}
          riskAreas={riskAreas}
          selectedRiskId={selectedRiskId}
          onSelectRiskArea={selectRiskArea}
          onSelectRiskHousehold={selectRiskHousehold}
          onSubmit={submitDispatch}
          showOutcomeUpdate={Boolean(editingDispatch)}
        />
        </fieldset>
        <div className="new-dispatch-actions">
          <button className="btn btn-secondary" type="button" disabled={isSaving} onClick={() => navigate(returnTo)}>Cancel</button>
          <button className="btn btn-primary" type="submit" form="dispatchForm" disabled={isSaving || isClosed || (!editingDispatch && !hasActiveEvent)}>
            {isSaving ? 'Saving...' : editingDispatch ? 'Save update' : 'Dispatch responders'}
          </button>
        </div>
      </section>
    </main>
  )
}

function firstDispatchableHousehold(area) {
  return area?.recommended_households?.find((household) => household.is_available_for_dispatch && household.has_geotag)
    || area?.households?.find((household) => household.is_available_for_dispatch && household.has_geotag)
    || null
}

function initialAssignment(payload, dispatch, state) {
  const areas = payload?.risk_areas || []
  const household = state?.selectedHousehold
  const areaName = household?.purok || dispatch?.assigned_area
  const area = areas.find((item) => String(item.area_name).toLowerCase() === String(areaName || '').toLowerCase())
  const target = dispatch
    ? areas.flatMap((item) => item.households || []).find((item) => String(item.household_id) === String(dispatch.household_id))
      || { household_id: dispatch.household_id, household_name: dispatch.household_id }
    : household || firstDispatchableHousehold(area)
  const option = dispatch?.team_id ? `team:${dispatch.team_id}`
    : dispatch?.responder_id ? `responder:${dispatch.responder_id}`
    : state?.team?.team_id && state.team.is_available ? `team:${state.team.team_id}` : firstAssignmentOption((payload?.teams || []).filter((team) => state?.dispatchType !== 'welfare_check' || Number(team.available_responder_count) >= 2))
  return { selectedRiskId: area?.id || '', assignmentOption: option, form: {
    ...defaultForm(), target_household: target,
    household_id: dispatch?.household_id || household?.household_id || household?.id || target?.household_id || '',
    assigned_area: dispatch?.assigned_area || area?.area_name || household?.purok || '',
    households_to_cover: dispatch?.households_to_cover ?? (target ? 1 : 0),
    priority_level: dispatch?.priority_level || household?.priority_level || area?.priority || 'high',
    status: dispatch?.status?.key || 'dispatched',
    selected_responder_ids: dispatch?.selected_responder_ids || [],
    responder_count: dispatch?.responder_count || (state?.dispatchType === 'welfare_check' ? 2 : 1),
    dispatch_type: dispatch?.dispatch_type || state?.dispatchType || 'rescue',
    dispatch_notes: dispatch?.dispatch_notes || '', route_notes: dispatch?.route_notes || '',
    safe_count: dispatch?.outcomes?.safe || 0, evacuated_count: dispatch?.outcomes?.evacuated || 0,
    unsafe_count: dispatch?.outcomes?.unsafe || 0, injured_count: dispatch?.outcomes?.injured || 0,
    missing_count: dispatch?.outcomes?.missing || 0, pending_count: dispatch?.outcomes?.pending || 0,
    outcome_notes: dispatch?.outcomes?.notes || '',
  } }
}
