import { useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, ChevronLeft, ChevronRight, RotateCw } from 'lucide-react'
import Modal from '../ui/Modal'
import DispatchStatusBadge from './DispatchStatusBadge'
import { splitCoverageHouseholds } from '../../utils/dispatchHelpers'

const initials = (name) => String(name || '').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('')
const priorityColor = (key) => ['high','critical'].includes(key) ? '#dc2626' : ['moderate','watch'].includes(key) ? '#d97706' : '#2563eb'

export default function DispatchModalForm({ editingDispatch, groups, group, onGroupChange, selections, teamId, onTeamChange, onMemberChange, householdPlans, plans, teams, responders, dispatches, coveredHouseholds, form, setForm, error, saving, closed, syncing, onSync, onAutoAssign, hasActiveEvent }) {
  const [collapsed, setCollapsed] = useState(false)
  const [details, setDetails] = useState(null)
  const [memberTab, setMemberTab] = useState(0)
  const [limit, setLimit] = useState(20)
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
    return <div className="sv">{coverage.map((items, index) => <div className="sc2" key={ids?.[index] || index}><span className="av s">{names[index] ? initials(names[index]) : `M${index + 1}`}</span><b>{editingDispatch && ids?.[index] != null ? <Link to={`/rescuers/view?id=${encodeURIComponent(ids[index])}`} state={{ fromDispatchAssignment:true }}>{names[index] || `Member ${index + 1}`}</Link> : names[index] || `Member ${index + 1}`}</b><em>M{index + 1}</em><span className="pc">{items.length} HH</span>{items.some((household) => Number(household.reported_unsafe_count) > 0) && <span className="pc u">{items.filter((household) => Number(household.reported_unsafe_count) > 0).length} unsafe</span>}{items.some((household) => household.has_contact_channel === false) && <span className="pc nt3">{items.filter((household) => household.has_contact_channel === false).length} no contact</span>}<button className="xp sm" type="button" onClick={() => { openDetails(title, households, names, ids, savedGroups);setMemberTab(index) }}>Details <ArrowRight size={12} /></button></div>)}</div>
  }
  const outcomeEnabled = !closed && ['on_scene','completed'].includes(form.status)
  const statuses = [['dispatched','Dispatched','#3b82f6'], ['on_scene','On-scene','#f59e0b'], ['returning','Returning','#8b5cf6'], ['completed','Completed','#0ea5e9'], ['cancelled','Cancelled','#64748b']]
  const currentStage = ['accepted','en_route'].includes(form.status) ? 'dispatched' : form.status
  const selectedCards = group?.cards || []
  return <>
    <div className={`eb${editingDispatch ? ' one' : collapsed ? ' col' : ''}`}>
      {!editingDispatch && (collapsed ? <aside className="ar cl"><button className="x" type="button" aria-label="Expand priority" onClick={() => setCollapsed(false)}><ChevronRight size={16} /></button><span>Priority</span></aside> : <aside className="ar"><div className="arh"><b style={{ flex:1, alignSelf:'center' }}>Priority</b><button className="x" type="button" aria-label="Collapse priority" onClick={() => setCollapsed(true)}><ChevronLeft size={16} /></button></div><div className="hl">{groups.map((item) => <button key={item.id} className={`sr${group?.id === item.id ? ' on' : ''}`} type="button" disabled={saving} onClick={() => onGroupChange(item.id)}><span className="rk">{item.rank}</span><b>{item.name}</b><span className="pl" style={{ '--c':priorityColor(item.band?.key) }}>{item.band?.label || 'Low'}</span><em>{item.cards.filter((card) => card.households?.length && card.households.every((household) => !household.is_available_for_dispatch)).length}/{item.cards.length}</em></button>)}</div></aside>)}
      <section className="as">
        {error && <div className="form-error" role="alert">{error}</div>}
        {!editingDispatch ? <>
          {!hasActiveEvent && <div className="nb">No active event · dispatch disabled</div>}
          <div className="sh3"><b>{group?.name || 'No priority areas'}</b>{group && <span className="pl" style={{ '--c':priorityColor(group.band?.key) }}>{group.band?.label || 'Low'}</span>}<span className="sp" /><select aria-label={`Team to deploy in ${group?.name || 'sitio'}`} value={teamId} disabled={saving || !hasActiveEvent} onChange={(event) => onTeamChange(event.target.value)}><option value="">Select team for sitio</option>{teams.filter((team) => team.team_id).map((team) => <option key={team.team_id} value={String(team.team_id)} disabled={!responders.some((member) => member.is_available && String(member.team_id) === String(team.team_id))}>{team.team_name} · {team.available_responder_count} free</option>)}</select><button className="bt" type="button" disabled={saving || !hasActiveEvent || !selectedCards.length} onClick={onAutoAssign}>Auto assign</button></div>
          <div className="pgrid">{selectedCards.map((card) => {
            const households = card.households || []
            const eligible = households.filter((household) => household.is_available_for_dispatch)
            const busy = households.length > 0 && !eligible.length
            const assignment = dispatches.find((item) => households.some((household) => String(household.active_assignment_id) === String(item.assignment_id)))
            const selectedMembers = selections[card.id] || []
            const plan = plans[card.id]
            const usedMembers = new Set(Object.entries(selections).filter(([id]) => id !== card.id).flatMap(([,members]) => members).filter(Boolean).map(String))
            return <div key={card.id} className={`pk${busy ? ' off' : ''}${selectedMembers.some(Boolean) && !busy ? ' on' : ''}`}><div className="pkh"><b>{card.name}</b><span className="pc">{card.total_households ?? households.length} HH</span><span className="pc u">{households.filter((household) => Number(household.reported_unsafe_count) > 0).length} unsafe</span>{households.some((household) => household.has_contact_channel === false) && <span className="pc nt3">{households.filter((household) => household.has_contact_channel === false).length} no contact</span>}</div>
              {busy ? <div className="dn"><DispatchStatusBadge status={assignment?.status || { key:'dispatched', label:'Dispatched' }} /><b>{assignment?.team_name || 'Active dispatch'}</b></div> : <div className="member-selectors">{[0,1].map((slot) => <select key={slot} aria-label={`Rescuer ${slot + 1} for ${card.name}`} value={selectedMembers[slot] || ''} disabled={saving || !hasActiveEvent || !eligible.length || !teamId} onChange={(event) => onMemberChange(card.id, slot, event.target.value)}><option value="">{slot === 0 ? 'Select rescuer' : 'Second rescuer (optional)'}</option>{responders.filter((member) => member.is_available && String(member.team_id) === String(teamId)).map((member) => <option key={member.responder_id} value={String(member.responder_id)} disabled={usedMembers.has(String(member.responder_id)) || selectedMembers.some((id, index) => index !== slot && String(id) === String(member.responder_id))}>{member.full_name}</option>)}</select>)}</div>}
              {selectedMembers.some(Boolean) && plan === null && <div className="er">Select distinct available rescuers from the deployed team.</div>}
              {plan && responderRows(card.name, eligible, plan.map((member) => member.full_name), plan.map((member) => member.responder_id), householdPlans[card.id])}
              {busy && responderRows(card.name, households, assignment?.selected_responders || [], assignment?.selected_responder_ids, assignment?.responder_households)}
            </div>
          })}</div>
          {!selectedCards.length && <div className="empty-reference">No puroks available for this area.</div>}
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
