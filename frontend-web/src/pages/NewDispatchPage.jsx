import { useCallback, useEffect, useMemo, useState } from 'react'
import { createPortal } from 'react-dom'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
import { X, ArrowRight } from 'lucide-react'
import { completeDispatch, createDispatch, getDispatchDashboard, getDispatch, getSitioPriorities, updateDispatch } from '../api/dispatchApi'
import DispatchModalForm from '../components/dispatch/DispatchModalForm'
import RescueManagementPage from './RescueManagementPage'
import { useModuleData } from '../utils/useModuleData'
import { splitDispatchAreaAvailability } from '../utils/dispatchAreaAvailability'
import { buildDispatchAreaGroups, defaultForm, getSaveMessage, planSelectedAreaResponders, shuffleDispatchItems, splitCoverageHouseholds } from '../utils/dispatchHelpers'

export default function NewDispatchPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const { assignmentId } = useParams()
  const id = assignmentId || location.state?.dispatch?.assignment_id
  const loader = useCallback(async () => {
    const [workspace, priorities, detail] = await Promise.all([getDispatchDashboard({ status:'active', per_page:100 }), getSitioPriorities(), id ? getDispatch(id) : null])
    const groups = buildDispatchAreaGroups(Array.isArray(priorities) ? priorities : [], workspace?.risk_areas || [])
    return { workspace, groups: detail?.dispatch ? groups : splitDispatchAreaAvailability(groups, workspace).available, detail }
  }, [id])
  const { data, error, loading, refresh } = useModuleData(loader, ['dispatch', 'households', 'disasters'])
  const close = useCallback(() => navigate('/rescue-management'), [navigate])
  useEffect(() => {
    const keyboard = (event) => { if (event.key === 'Escape' && !document.querySelector('.detail-overlay')) close() }
    document.addEventListener('keydown', keyboard)
    return () => document.removeEventListener('keydown', keyboard)
  }, [close])
  return <><RescueManagementPage />{createPortal(<div className="ro-reference ro-overlay" onClick={(event) => { if (event.currentTarget === event.target) close() }}>
    {loading || error ? <div className="md"><div className="mh2"><h3>{id ? 'Update Dispatch' : 'New Dispatch'}</h3><span className="sp" /><button className="x" type="button" aria-label="Close assignment editor" onClick={close}><X size={16} /></button></div><div className="as">{loading ? <div className="empty-reference">Loading assignment…</div> : <div className="form-error" role="alert">{error}<button className="bt" type="button" onClick={refresh}>Retry</button></div>}</div></div>
      : <AssignmentEditor key={`${id || 'new'}-${location.key}`} workspace={data.workspace} groups={data.groups} detail={data.detail} initialSitio={location.state?.selectedSitio?.sitio_id} initialHousehold={location.state?.selectedHousehold} close={close} />}
  </div>, document.body)}</>
}

function AssignmentEditor({ workspace, groups, detail, initialSitio, initialHousehold, close }) {
  const dispatch = detail?.dispatch
  const [groupId, setGroupId] = useState(initialSitio ? `sitio:${initialSitio}` : groups.find((group) => group.cards.some((card) => card.households?.some((household) => String(household.household_id) === String(initialHousehold?.household_id))))?.id || groups[0]?.id || '')
  const [selections, setSelections] = useState({})
  const [teamId, setTeamId] = useState('')
  const [form, setForm] = useState({ ...defaultForm(), status:dispatch?.status?.key || 'dispatched', dispatch_notes:dispatch?.dispatch_notes || '', safe_count:dispatch?.outcomes?.safe || 0, evacuated_count:dispatch?.outcomes?.evacuated || 0, unsafe_count:dispatch?.outcomes?.unsafe || 0, pending_count:dispatch?.outcomes?.pending || 0 })
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const [syncing, setSyncing] = useState(false)
  const group = groups.find((item) => item.id === groupId) || groups[0]
  const cards = useMemo(() => group?.cards || [], [group])
  const plans = useMemo(() => planSelectedAreaResponders(cards, selections, workspace?.responders || [], teamId), [cards, selections, workspace, teamId])
  const householdPlans = useMemo(() => Object.fromEntries(cards.filter((card) => plans[card.id]).map((card) => [card.id, Object.fromEntries(splitCoverageHouseholds(shuffleDispatchItems((card.households || []).filter((household) => household.is_available_for_dispatch)), plans[card.id].length).map((items, index) => [plans[card.id][index].responder_id, items.map((household) => household.household_id)]))])), [cards, plans])
  const selected = cards.filter((card) => selections[card.id]?.some(Boolean))
  const closed = ['completed','cancelled'].includes(dispatch?.status?.key)
  const canDispatch = Boolean(workspace?.active_event && selected.length && selected.every((card) => plans[card.id] && card.households?.some((household) => household.is_available_for_dispatch)))
  function autoAssign() {
    const teams = (workspace.teams || []).filter((team) => (workspace.responders || []).some((member) => member.is_available && String(member.team_id) === String(team.team_id)))
    const chosenTeam = teamId || String(shuffleDispatchItems(teams)[0]?.team_id || '')
    if (!chosenTeam) { setError('No team has available rescuers.'); return }
    const available = shuffleDispatchItems((workspace.responders || []).filter((member) => member.is_available && String(member.team_id) === chosenTeam))
    const next = {}
    const eligibleCards = cards.filter((card) => card.households?.some((household) => household.is_available_for_dispatch))
    // Cover as many puroks as possible before assigning a second rescuer.
    for (const card of shuffleDispatchItems(eligibleCards)) {
      if (available.length) next[card.id] = [String(available.shift().responder_id)]
    }
    for (const card of shuffleDispatchItems(eligibleCards)) {
      if (next[card.id] && available.length && card.households.filter((household) => household.is_available_for_dispatch).length > 1) next[card.id].push(String(available.shift().responder_id))
    }
    setTeamId(chosenTeam)
    setSelections(next)
    setError('')
  }
  async function syncOutcomes() {
    setError('');setSyncing(true)
    try {
      const response = await getDispatch(dispatch.assignment_id)
      if (!response?.report_outcomes) throw new Error('Field report outcomes are currently unavailable.')
      if (!response.report_outcomes.report_count) throw new Error('No saved field reports are available for this assignment’s households.')
      setForm((current) => ({ ...current, ...Object.fromEntries(['safe_count','evacuated_count','unsafe_count','pending_count'].map((key) => [key, Number(response.report_outcomes[key]) || 0])) }))
    } catch (failure) { setError(failure.response?.data?.message || failure.message || 'Reports could not be synced.') }
    finally { setSyncing(false) }
  }
  async function submit(event) {
    event.preventDefault();setError('')
    if (closed || (!dispatch && !canDispatch)) return
    setSaving(true)
    try {
      if (dispatch) {
        const body = { status:form.status, dispatch_notes:form.dispatch_notes, safe_count:Number(form.safe_count), evacuated_count:Number(form.evacuated_count), unsafe_count:Number(form.unsafe_count), pending_count:Number(form.pending_count) }
        if (form.status === 'completed') await completeDispatch(dispatch.assignment_id, body)
        else await updateDispatch(dispatch.assignment_id, body)
      } else {
        const assignments = selected.map((card) => {
          const households = card.households.filter((household) => household.is_available_for_dispatch)
          const members = plans[card.id]
          return { team_id:Number(teamId), selected_responder_ids:members.map((member) => member.responder_id), assigned_area:card.area_name, household_id:null, household_ids:households.map((household) => household.household_id), household_groups:members.map((member) => householdPlans[card.id][member.responder_id]), households_to_cover:households.length, responder_count:members.length, dispatch_type:'rescue', priority_level:card.priority || 'high', status:'dispatched', dispatch_notes:'' }
        })
        await createDispatch({ assignments })
      }
      close()
    } catch (failure) { setError(getSaveMessage(failure)) }
    finally { setSaving(false) }
  }
  return <div className="md" role="dialog" aria-modal="true" aria-label={dispatch ? 'Update Dispatch Assignment' : 'New Dispatch'}>
    <div className="mh2"><h3>{dispatch?.assignment_code || 'New Dispatch'}</h3>{dispatch && <div className="ln"><span>Area <b>{dispatch.assigned_area}</b></span><span>Team <b>{dispatch.team_name}</b></span><span>Households <b>{dispatch.households_to_cover}</b></span></div>}<span className="sp" /><button className="x" type="button" disabled={saving} aria-label="Close assignment editor" onClick={close}><X size={16} /></button></div>
    <form className="editor-form" onSubmit={submit}><DispatchModalForm editingDispatch={dispatch} groups={groups} group={group} onGroupChange={(id) => { setGroupId(id);setTeamId('');setSelections({});setError('') }} selections={selections} teamId={teamId} onTeamChange={(team) => { setTeamId(team);setSelections({});setError('') }} onMemberChange={(id, slot, member) => setSelections((current) => { const members = [...(current[id] || ['',''])];members[slot] = member;return { ...current, [id]:members } })} householdPlans={householdPlans} plans={plans} teams={workspace?.teams || []} responders={workspace?.responders || []} dispatches={workspace?.dispatches?.data || []} coveredHouseholds={detail?.covered_households || []} form={form} setForm={setForm} error={error} saving={saving} closed={closed} syncing={syncing} onSync={syncOutcomes} onAutoAssign={autoAssign} hasActiveEvent={Boolean(workspace?.active_event)} />
      <div className="mf">{!dispatch && <span className="mu" style={{ marginRight:'auto', alignSelf:'center' }}>{selected.length} puroks · {selected.reduce((sum, card) => sum + (plans[card.id]?.length || 0), 0)} rescuers · {selected.reduce((sum, card) => sum + (card.households || []).filter((household) => household.is_available_for_dispatch).length, 0)} HH</span>}<button className="bt" type="button" disabled={saving} onClick={close}>Cancel</button><button className="bt p" type="submit" disabled={saving || closed || (!dispatch && !canDispatch)}>{saving ? 'Saving…' : dispatch ? 'Save update' : 'Dispatch responders'}<i><ArrowRight size={15} /></i></button></div>
    </form>
  </div>
}
