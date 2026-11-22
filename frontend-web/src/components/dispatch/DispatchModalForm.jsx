import { statusColor } from '../../utils/statusPalette'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, ChevronDown, ChevronLeft, ChevronRight, RotateCw } from 'lucide-react'
import Modal from '../ui/Modal'
import DispatchStatusBadge from './DispatchStatusBadge'
import { splitCoverageHouseholds } from '../../utils/dispatchHelpers'

const initials = (name) => String(name || '').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('')
const priorityColor = key => statusColor(key || 'low')

export default function DispatchModalForm({ editingDispatch, groups, group, coverage, sitioCoverage = [], onAddTeam, allowAssignment = true, onGroupChange, selections, teamId, onTeamChange, onMemberChange, householdPlans, plans, teams, responders, dispatches, coveredHouseholds, form, setForm, error, saving, closed, syncing, onSync, onAutoAssign, hasActiveEvent }) {
  const [collapsed, setCollapsed] = useState(false)
  const [details, setDetails] = useState(null)
  const [memberTab, setMemberTab] = useState(0)
  const [limit, setLimit] = useState(20)
  const [teamMenuOpen, setTeamMenuOpen] = useState(false)
  const openDetails = (title, households, names, ids, savedGroups) => {
    const coverage = savedGroups && Object.keys(savedGroups).length && ids?.length ? ids.map((id) => households.filter((household) => (savedGroups[String(id)] || []).includes(household.household_id))) : splitCoverageHouseholds(households, Math.max(1, names.length || 2))
    setDetails({ title, groups:coverage, names, ids });setMemberTab(0);setLimit(20)
  }
  const householdProfileUrl = (household) => {
    const path = `/households/${encodeURIComponent(household.household_id)}`
    if (!editingDispatch?.assignment_id) return path
    const context = new URLSearchParams({ dispatch_id:String(editingDispatch.assignment_id), dispatch_code:editingDispatch.assignment_code })
    if (details?.ids?.[memberTab] != null) context.set('responder_id', String(details.ids[memberTab]))
    return `${path}?${context}`
  }
  const responderRows = (title, households, names, ids, savedGroups) => {
    const coverage = savedGroups && Object.keys(savedGroups).length && ids?.length ? ids.map((id) => households.filter((household) => (savedGroups[String(id)] || []).includes(household.household_id))) : splitCoverageHouseholds(households, Math.max(1, names.length || 2))
    return <div className="sv">{coverage.map((items, index) => <div className="sc2" key={ids?.[index] || index}><span className="av s">{names[index] ? initials(names[index]) : `M${index + 1}`}</span><b>{ids?.[index] != null ? <Link to="/rescuers" state={{ fromDispatchAssignment:true, selectedProfileId: String(ids[index]) }}>{names[index] || `Member ${index + 1}`}</Link> : names[index] || `Member ${index + 1}`}</b><em>M{index + 1}</em><span className="pc">{items.length} HH</span>{items.some((household) => Number(household.reported_unsafe_count) > 0) && <span className="pc u">{items.filter((household) => Number(household.reported_unsafe_count) > 0).length} unsafe</span>}{items.some((household) => household.has_contact_channel === false) && <span className="pc nt3">{items.filter((household) => household.has_contact_channel === false).length} no contact</span>}<button className="xp sm" type="button" onClick={() => { openDetails(title, households, names, ids, savedGroups);setMemberTab(index) }}>Details <ArrowRight size={12} /></button></div>)}</div>
  }
  const outcomeEnabled = !closed && ['on_scene','completed'].includes(form.status)
  const statuses = [['dispatched','Dispatched',statusColor('dispatched')], ['on_scene','On-scene',statusColor('on_scene')], ['returning','Returning',statusColor('returning')], ['completed','Completed',statusColor('completed')], ['cancelled','Cancelled',statusColor('cancelled')]]
  const currentStage = ['accepted','en_route'].includes(form.status) ? 'dispatched' : form.status
  const selectedCards = group?.cards || []
  const coverageBySitio = new Map(sitioCoverage.map(item => [String(item.sitio_id), item]))
  const groupLists = [
    { label: 'Awaiting assignment', groups: groups.filter(item => !coverageBySitio.get(String(item.sitio_id))?.is_assigned) },
    { label: 'Already assigned sitios', groups: groups.filter(item => coverageBySitio.get(String(item.sitio_id))?.is_assigned) },
  ]
  const availableIds = coverage ? new Set(coverage.dispatchable_household_ids) : null
  const freeRespondersByTeam = responders.reduce((counts, member) => {
    if (member.is_available && !member.active_assignment_id && member.team_id != null) {
      const id = String(member.team_id)
      counts.set(id, (counts.get(id) || 0) + 1)
    }
    return counts
  }, new Map())
  const selectableTeams = teams.filter((team) => team.team_id && (freeRespondersByTeam.get(String(team.team_id)) || 0) > 0)
  const selectedTeam = selectableTeams.find((team) => String(team.team_id) === String(teamId))
  return <>
    <div className={`eb${editingDispatch ? ' one' : collapsed ? ' col' : ''}`}>
      {!editingDispatch && (collapsed ? <aside className="ar cl"><button className="x" type="button" aria-label="Expand priority" onClick={() => setCollapsed(false)}><ChevronRight size={16} /></button><span>Priority</span></aside> : <aside className="ar"><div className="arh"><b style={{ flex:1, alignSelf:'center' }}>Priority</b><button className="x" type="button" aria-label="Collapse priority" onClick={() => setCollapsed(true)}><ChevronLeft size={16} /></button></div><div className="hl">
        {groupLists.filter(list => list.groups.length).map(list => <section className="sitio-priority-list" key={list.label} aria-label={list.label}><h3>{list.label}</h3>{list.groups.map(item => <button key={item.id} className={`sr${group?.id === item.id ? ' on' : ''}`} type="button" disabled={saving} onClick={() => { setTeamMenuOpen(false); onGroupChange(item.id) }}><span className="rk">{item.rank}</span><b>{item.name}</b>{!coverageBySitio.get(String(item.sitio_id))?.is_assigned && <span className="pl" style={{ '--c':priorityColor(item.band?.key) }}>{item.band?.label || 'Low'}</span>}<em>{item.cards.filter(card => card.households?.length && card.households.every(household => !household.is_available_for_dispatch)).length}/{item.cards.length}</em></button>)}</section>)}
      </div></aside>)}
      <section className="as">
        {error && <div className="form-error" role="alert">{error}</div>}
        {!editingDispatch ? <>
          <div className="sh3"><b>{group?.name || 'No priority areas'}</b>{group && <span className="pl" style={{ '--c':coverage?.is_assigned ? statusColor('assigned') : priorityColor(group.band?.key) }}>{coverage?.is_assigned ? 'Already assigned' : group.band?.label || 'Low'}</span>}<span className="sp" />{coverage?.can_add_team && !allowAssignment && <button type="button" className="bt p" disabled={saving} onClick={onAddTeam}>Add another rescue team</button>}<button className="bt" type="button" disabled={saving || !hasActiveEvent || !allowAssignment || !selectedCards.some(card => card.households?.some(h => h.is_available_for_dispatch && (!availableIds || availableIds.has(h.household_id))))} onClick={onAutoAssign}>Auto assign</button></div>
          <div className="pgrid"><div className="dispatch-team-select"><button className="dispatch-team-trigger" type="button" aria-label={`Team to deploy in ${group?.name || 'sitio'}`} aria-haspopup="listbox" aria-expanded={teamMenuOpen} disabled={saving || !hasActiveEvent || !allowAssignment || !selectableTeams.length} onClick={() => setTeamMenuOpen((open) => !open)}><span>{selectedTeam?.team_name || 'Select team for sitio'}</span>{selectedTeam && <span className="dispatch-team-free">{freeRespondersByTeam.get(String(selectedTeam.team_id))} free</span>}<ChevronDown size={16} /></button>{teamMenuOpen && <div className="dispatch-team-options" role="listbox" aria-label="Available rescue teams">{selectableTeams.map((team) => <button key={team.team_id} type="button" role="option" aria-selected={String(team.team_id) === String(teamId)} onClick={() => { onTeamChange(String(team.team_id)); setTeamMenuOpen(false) }}><span>{team.team_name}</span><span className="dispatch-team-free">{freeRespondersByTeam.get(String(team.team_id))} free</span></button>)}</div>}</div>{selectedCards.map((card) => {
            const households = card.households || []
            const eligible = households.filter(household => household.is_available_for_dispatch && (!availableIds || availableIds.has(household.household_id)))
            const busy = households.length > 0 && !eligible.length
            const assignment = dispatches.find((item) => households.some((household) => String(household.active_assignment_id) === String(item.assignment_id)))
            const selectedMembers = selections[card.id] || []
            const plan = plans[card.id]
            const usedMembers = new Set(Object.entries(selections).filter(([id]) => id !== card.id).flatMap(([,members]) => members).filter(Boolean).map(String))
            const availableTeamMembers = responders.filter((member) => member.is_available && !member.active_assignment_id && String(member.team_id) === String(teamId))
            const selectableResponders = availableTeamMembers.filter((member) => !usedMembers.has(String(member.responder_id)) && !selectedMembers.includes(String(member.responder_id)))
            return <div key={card.id} className={`pk${busy ? ' off' : ''}${selectedMembers.some(Boolean) && !busy ? ' on' : ''}`}><div className="pkh"><b>{card.name}</b><span className="pc">{card.total_households ?? households.length} HH</span>{coverage?.is_assigned && <span className="pc">{eligible.length} uncovered</span>}<span className="pc u">{households.filter((household) => Number(household.reported_unsafe_count) > 0).length} unsafe</span>{households.some((household) => household.has_contact_channel === false) && <span className="pc nt3">{households.filter((household) => household.has_contact_channel === false).length} no contact</span>}</div>
              {busy ? <div className="dn"><DispatchStatusBadge status={assignment?.status || { key:'dispatched', label:'Dispatched' }} /><b>{assignment?.team_name || 'Active dispatch'}</b></div> : <div className="member-selectors"><div className="rescuer-picker"><select aria-label={`Select rescuer for ${card.name}`} value="" disabled={saving || !hasActiveEvent || !allowAssignment || !eligible.length || !teamId || !selectableResponders.length} onChange={(event) => { if (event.target.value) onMemberChange(card.id, event.target.value) }}><option value="" disabled hidden></option>{selectableResponders.map((member) => <option key={member.responder_id} value={String(member.responder_id)}>{member.full_name}</option>)}</select><span className="rescuer-picker-placeholder">Select rescuer</span></div></div>}
              {selectedMembers.some(Boolean) && plan === null && <div className="er">Choose distinct available rescuers from the deployed team for this area.</div>}
              {plan && responderRows(card.name, eligible, plan.map((member) => member.full_name), plan.map((member) => member.responder_id), householdPlans[card.id])}
              {busy && responderRows(card.name, households, assignment?.selected_responders || [], assignment?.selected_responder_ids, assignment?.responder_households)}
            </div>
          })}</div>
          {allowAssignment && !selectedCards.some(card => card.households?.some(h => h.is_available_for_dispatch && (!availableIds || availableIds.has(h.household_id)))) && <div className="empty-reference">No uncovered households are currently available for dispatch in this sitio.</div>}
        </> : <>
          {closed && <div className="cl2">Closed · read-only</div>}
          {responderRows(editingDispatch.assigned_area, coveredHouseholds, editingDispatch.selected_responders || [], editingDispatch.selected_responder_ids, editingDispatch.responder_households)}
          <div className="fl w2"><label>Status</label><div className="sg2">{statuses.map(([key, name, color]) => <button key={key} type="button" disabled={closed || saving} className={currentStage === key ? 'on' : ''} style={{ '--c':color }} onClick={() => setForm((current) => ({ ...current, status:key }))}>{name}</button>)}</div></div>
          <div className="fl w2"><label>Outcome<button aria-label="Sync" className={`sy${syncing ? ' spin' : ''}`} type="button" disabled={!outcomeEnabled || saving || syncing} onClick={onSync}><i><RotateCw size={12} /></i> Sync</button></label><div className="out">{[['safe_count','Safe'], ['evacuated_count','Evacuated'], ['unsafe_count','Unsafe'], ['pending_count','Pending']].map(([key,name]) => <div className="fl" key={key}><span className="mu" style={{ fontSize:12, fontWeight:500 }}>{name}</span><div className="stp"><button type="button" disabled={!outcomeEnabled || saving || Number(form[key]) <= 0} aria-label={`Decrease ${name}`} onClick={() => setForm((current) => ({ ...current, [key]:Math.max(0, Number(current[key]) - 1) }))}>−</button><input aria-label={name} type="number" min="0" value={form[key]} disabled={!outcomeEnabled || saving} onChange={(event) => setForm((current) => ({ ...current, [key]:Math.max(0, Number(event.target.value) || 0) }))} /><button type="button" disabled={!outcomeEnabled || saving} aria-label={`Increase ${name}`} onClick={() => setForm((current) => ({ ...current, [key]:Number(current[key]) + 1 }))}>+</button></div></div>)}</div></div>
          <div className="fl w2"><label>Remarks</label><input value={form.dispatch_notes} maxLength={1000} disabled={closed || saving} onChange={(event) => setForm((current) => ({ ...current, dispatch_notes:event.target.value }))} /></div>
        </>}
      </section>
    </div>
    <Modal className="reference-drawer" title={details?.title || 'Household details'} isOpen={Boolean(details)} onClose={() => setDetails(null)}>
      {details && <><div className="sg2 dtab">{details.groups.map((items, index) => <button key={index} type="button" className={memberTab === index ? 'on' : ''} onClick={() => { setMemberTab(index);setLimit(20) }}>M{index + 1}{details.names[index] ? ` · ${details.names[index]}` : ''} · {items.length}</button>)}</div><div className="dlst">{(details.groups[memberTab] || []).slice(0, limit).map((household) => <div className="hl2" key={household.household_id}><b><Link to={householdProfileUrl(household)}>{household.household_name || household.household_id}</Link></b><span>{household.household_id}</span><span>{household.member_count ?? 'Not recorded'} members</span>{Number(household.reported_unsafe_count) > 0 && <span className="u">{household.reported_unsafe_count} unsafe</span>}{household.has_contact_channel === false && <span className="nc">No contact channel</span>}</div>)}{(details.groups[memberTab]?.length || 0) > limit && <button className="bt more" type="button" onClick={() => setLimit((current) => current + 20)}>Show more · {details.groups[memberTab].length - limit}</button>}</div></>}
    </Modal>
  </>
}
