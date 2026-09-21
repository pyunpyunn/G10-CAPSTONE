import { useEffect, useState } from 'react'
import { ArrowLeft } from 'lucide-react'
import { useLocation, useNavigate } from 'react-router-dom'
import { completeDispatch, createDispatch, getDispatchDashboard, updateDispatch } from '../api/dispatchApi'
import DispatchModalForm from '../components/dispatch/DispatchModalForm'
import LoadingState from '../components/ui/LoadingState'
import {
  buildRequestBody,
  defaultForm,
  firstAssignmentOption,
  getSaveMessage,
} from '../utils/dispatchHelpers'

export default function NewDispatchPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const [payload, setPayload] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [selectedRiskId, setSelectedRiskId] = useState('')
  const [assignmentOption, setAssignmentOption] = useState('')
  const [form, setForm] = useState(defaultForm())
  const [formError, setFormError] = useState('')
  const [isSaving, setIsSaving] = useState(false)
  const [isInitialized, setIsInitialized] = useState(false)
  const editingDispatch = location.state?.dispatch || null

  useEffect(() => {
    let ignore = false

    async function loadPage() {
      setIsLoading(true)
      setError('')

      try {
        const data = await getDispatchDashboard({ status: 'all', per_page: 20 })
        if (!ignore) {
          setPayload(data)
        }
      } catch {
        if (!ignore) {
          setError('New dispatch cannot be loaded right now. Please check the backend or database connection.')
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
  }, [])

  const teams = payload?.teams || []
  const responders = payload?.responders || []
  const riskAreas = payload?.risk_areas || []
  const hasActiveEvent = Boolean(payload?.active_event)

  useEffect(() => {
    if (!payload || isInitialized) {
      return
    }

    const selectedHousehold = location.state?.selectedHousehold
    const selectedTeam = location.state?.team
    const existingDispatch = editingDispatch
    const selectedAreaName = selectedHousehold?.purok || existingDispatch?.assigned_area
    const matchingArea = selectedAreaName
      ? riskAreas.find((area) => {
          const areaName = String(area.area_name || '').toLowerCase()
          const targetArea = String(selectedAreaName || '').toLowerCase()
          return areaName === targetArea || targetArea.includes(areaName) || areaName.includes(targetArea)
        })
      : null
    const firstHousehold = matchingArea?.households?.find((household) => household.household_id === existingDispatch?.household_id)
      || (matchingArea ? firstDispatchableHousehold(matchingArea) : null)
    const teamOption = existingDispatch?.team_id
      ? `team:${existingDispatch.team_id}`
      : selectedTeam?.team_id && selectedTeam.is_available
      ? `team:${selectedTeam.team_id}`
      : selectedTeam?.available_responder_id
        ? `responder:${selectedTeam.available_responder_id}`
        : firstAssignmentOption(teams)

    setAssignmentOption(teamOption)
    setSelectedRiskId(matchingArea?.id || '')
    setForm({
      ...defaultForm(),
      target_household: selectedHousehold || firstHousehold,
      assigned_area: matchingArea?.area_name || selectedHousehold?.purok || existingDispatch?.assigned_area || '',
      household_id: selectedHousehold?.household_id || selectedHousehold?.id || firstHousehold?.household_id || existingDispatch?.household_id || '',
      households_to_cover: matchingArea?.to_cover || (selectedHousehold ? 1 : existingDispatch?.households_to_cover || 0),
      priority_level: selectedHousehold?.priority_level || matchingArea?.priority || existingDispatch?.priority_level || 'high',
      status: existingDispatch?.status?.key || 'dispatched',
      selected_responder_ids: existingDispatch?.selected_responder_ids || [],
      responder_count: existingDispatch?.responder_count || 1,
      dispatch_notes: existingDispatch?.dispatch_notes || '',
      route_notes: existingDispatch?.route_notes || '',
      safe_count: existingDispatch?.outcomes?.safe || 0,
      evacuated_count: existingDispatch?.outcomes?.evacuated || 0,
      unsafe_count: existingDispatch?.outcomes?.unsafe || 0,
      pending_count: existingDispatch?.outcomes?.pending || 0,
      outcome_notes: existingDispatch?.outcomes?.notes || '',
    })
    setIsInitialized(true)
  }, [location.state, payload, riskAreas, teams, isInitialized, editingDispatch])

  function selectRiskArea(area) {
    const firstHousehold = firstDispatchableHousehold(area)

    setSelectedRiskId(area.id)
    setForm((current) => ({
      ...current,
      assigned_area: area.area_name,
      household_id: firstHousehold?.household_id || '',
      households_to_cover: area.to_cover,
      safe_count: area.safe_households || 0,
      unsafe_count: area.unsafe_households || 0,
      pending_count: area.unchecked_households || 0,
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
      households_to_cover: Math.max(1, current.households_to_cover || area.to_cover || 1),
      priority_level: household.priority_level || area.priority || current.priority_level,
    }))
  }

  async function submitDispatch(event) {
    event.preventDefault()
    setFormError('')

    if (!hasActiveEvent) {
      setFormError('Dispatch assignment requires an active disaster event.')
      return
    }

    if (!assignmentOption) {
      setFormError('Select an available team first.')
      return
    }

    if (!form.assigned_area.trim()) {
      setFormError('Assigned area is required.')
      return
    }

    if (!form.household_id) {
      setFormError('Select a household with GPS from the affected area list. This is required for routed dispatch.')
      return
    }

    if (!form.selected_responder_ids || form.selected_responder_ids.length === 0) {
      setFormError('Select at least one available responder from the selected team.')
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
      navigate('/dispatch', { replace: true })
    } catch (saveError) {
      setFormError(getSaveMessage(saveError))
    } finally {
      setIsSaving(false)
    }
  }

  if (isLoading) {
    return <main className="ops-page new-dispatch-page"><LoadingState /></main>
  }

  return (
    <main className="ops-page new-dispatch-page">
      <header className="household-status-page-header dispatch-page-header">
        <div className="new-dispatch-header-row">
          <div className="household-status-header-copy">
            <h1>{editingDispatch ? 'Update dispatch' : 'New dispatch'}</h1>
          </div>
          <button className="new-dispatch-back" type="button" onClick={() => navigate('/dispatch')}>
            <ArrowLeft size={16} />
            Back to dispatch
          </button>
        </div>
      </header>

      {error && <div className="form-error">{error}</div>}
      {!hasActiveEvent && (
        <div className="standby-strip">
          <strong>No active disaster event</strong>
          <span>New dispatch assignments are disabled until HQ/Admin declares an active event.</span>
        </div>
      )}

      <section className="new-dispatch-shell" aria-label="New dispatch form">
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
          showOutcomeUpdate={Boolean(editingDispatch)}
        />
        <div className="new-dispatch-actions">
          <button className="btn btn-secondary" type="button" disabled={isSaving} onClick={() => navigate('/dispatch')}>Cancel</button>
          <button className="btn btn-primary" type="submit" form="dispatchForm" disabled={isSaving || !hasActiveEvent}>
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
