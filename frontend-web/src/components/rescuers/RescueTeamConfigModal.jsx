import { useState } from 'react'
import { createPortal } from 'react-dom'
import RescuerTeamGrid from './RescuerTeamGrid'
import { StatusDot } from './RescuerAccountModal'

const initials = (name = '') => name.replace(/^Hon\.\s*/i, '').split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('') || '?'

const teamForm = team => ({ team_id: team?.team_id || null, team_code: team?.team_code || '', team_name: team?.team_name || '', team_type: team?.team_type || '', duty_status: team?.duty_status || '', assigned_purok_id: team?.assigned_purok_id || '', leader_responder_id: team?.leader_responder_id || '', member_ids: [...(team?.member_ids || [])] })

export default function RescueTeamConfigModal({ isOpen, workspace, isLoading, isSaving, error, onRetry, onClose, onSave, onDelete, embedded = false }) {
  const teams = workspace?.teams || []
  const responders = workspace?.responders || []
  const initial = teams.find(t => Number(t.team_id) === Number(workspace?.selected_team_id)) || teams[0]
  const [form, setForm] = useState(() => initial ? teamForm(initial) : { ...teamForm(), ...workspace?.form_defaults })
  const [editing, setEditing] = useState(false)
  const [localError, setLocalError] = useState('')
  const [pages, setPages] = useState({ available: 1, members: 1 })
  if (!isOpen) return null
  const selected = teams.find(t => String(t.team_id) === String(form.team_id))
  const memberSet = new Set(form.member_ids.map(Number))
  const isHqTeam = (type, code) => String(code).toUpperCase() === 'HQCC' || ['HQ', 'HQCC', 'HEADQUARTERS'].includes(String(type).toUpperCase())
  const hqTeam = isHqTeam(form.team_type, form.team_code)
  const members = responders.filter(r => memberSet.has(Number(r.responder_id)))
  const available = responders.filter(r => (r.account_type === 'command_center') === hqTeam && !memberSet.has(Number(r.responder_id)) && (!r.team_id || Number(r.team_id) === Number(form.team_id)))
  const constraints = workspace?.form_constraints || {}
  function choose(team) { setForm(teamForm(team)); setEditing(false); setLocalError(''); setPages({ available: 1, members: 1 }) }
  function startNew() { setForm({ ...teamForm(), ...workspace?.form_defaults, member_ids: [] }); setEditing(true); setLocalError(''); setPages({ available: 1, members: 1 }) }
  function update(key, value) {
    setForm(current => {
      if (key !== 'team_type') return { ...current, [key]: value }
      const ids = current.member_ids.filter(id => responders.some(r => Number(r.responder_id) === Number(id) && (r.account_type === 'command_center') === isHqTeam(value, current.team_code)))
      return { ...current, team_type: value, member_ids: ids, leader_responder_id: ids.map(Number).includes(Number(current.leader_responder_id)) ? current.leader_responder_id : '' }
    })
  }
  function move(responder) {
    if (responder.can_change_membership === false || responder.is_deployed) return
    const id = Number(responder.responder_id)
    const next = memberSet.has(id) ? form.member_ids.filter(x => Number(x) !== id) : [...form.member_ids, id]
    setForm(current => ({ ...current, member_ids: next, leader_responder_id: next.map(Number).includes(Number(current.leader_responder_id)) ? current.leader_responder_id : '' }))
  }
  async function submit(event) {
    event.preventDefault()
    setLocalError('')
    if (!form.team_name.trim() || !form.team_type || !form.duty_status) { setLocalError('Complete the team name, type, and duty status.'); return }
    if (form.leader_responder_id && !memberSet.has(Number(form.leader_responder_id))) { setLocalError('Select a leader from the team members.'); return }
    await onSave({ ...form, assigned_purok_id: form.assigned_purok_id || null, leader_responder_id: form.leader_responder_id || null })
  }
  function memberRows(list, pool) {
    const perPage = 8, count = Math.max(1, Math.ceil(list.length / perPage)), page = Math.min(pages[pool], count)
    return <><div>{list.slice((page - 1) * perPage, page * perPage).map(r => <div className="mr" key={r.responder_id}><span className="av s" style={{ '--c': '#3b82f6' }}>{initials(r.full_name)}</span><div><span>{r.full_name}</span> <em>{r.title}</em></div>{!editing && Number(form.leader_responder_id) === Number(r.responder_id) ? <span className="rt" style={{ '--c': '#7c3aed' }}>Leader</span> : !editing ? <span /> : null}{editing ? <button className="mv" type="button" aria-label={`${pool === 'available' ? 'Add' : 'Remove'} ${r.full_name}`} title={r.can_change_membership === false || r.is_deployed ? 'Deployed members are locked' : undefined} disabled={isSaving || r.can_change_membership === false || r.is_deployed} onClick={() => move(r)}>{pool === 'available' ? '→' : '←'}</button> : <StatusDot muted={r.is_deployed}>{r.membership_status?.label || (r.is_deployed ? 'Deployed' : 'Available')}</StatusDot>}</div>)}</div>{!list.length && <div className="emp">{pool === 'members' ? 'No members' : 'No unassigned rescuers'}</div>}{count > 1 && <div className="pgr"><span>{(page - 1) * perPage + 1}–{Math.min(list.length, page * perPage)} of {list.length}</span><span className="sp" /><button type="button" aria-label={`Previous ${pool}`} disabled={page <= 1} onClick={() => setPages(current => ({ ...current, [pool]: page - 1 }))}>‹</button><button type="button" aria-label={`Next ${pool}`} disabled={page >= count} onClick={() => setPages(current => ({ ...current, [pool]: page + 1 }))}>›</button></div>}</>
  }
  const content = <div className="tm-team-workspace">
    {(error || localError) && <div className="tm-feedback" role="alert">{error || localError}{!workspace && <button className="bt sm" type="button" onClick={onRetry}>Retry</button>}</div>}
    {isLoading ? <div className="cd emp" role="status">Loading team configuration…</div> : workspace && <>
      <RescuerTeamGrid teams={teams} selectedId={form.team_id} onSelect={team => { if (!isSaving) choose(team) }} />
      <div className="tw2"><aside className="cd"><div className="ch">Teams<em className="mu">{teams.length}</em><span className="sp" /><button className="bt p nx sm" type="button" disabled={isSaving} onClick={startNew}>Add team</button></div><div className="cb">{editing && !form.team_id && <div className="tr on"><b>New team</b><span className="rt" style={{ '--c': '#0ea5e9' }}>New</span></div>}{teams.map(team => <button className={`tr${Number(team.team_id) === Number(form.team_id) ? ' on' : ''}`} type="button" key={team.team_id} disabled={isSaving} onClick={() => choose(team)}><b>{team.team_name}</b><em>{team.team_code}</em><span className="rt" style={{ '--c': '#64748b' }}>Saved</span><span className="s"><span>Members</span><b>{team.member_count || 0}</b></span></button>)}{!teams.length && <div className="emp">No teams</div>}</div></aside>
      <section className="cd tm-team-panel">{!editing ? selected ? <><div className="th"><span className="tt">{selected.team_name}</span><span className="cdc">{selected.team_code}</span><StatusDot muted={/off|disabled/i.test(selected.duty_status)}>{selected.duty_status_display?.label || selected.duty_status}</StatusDot><span className="sp" /><button className="bt sm" type="button" disabled={isSaving} onClick={() => setEditing(true)}>Update team</button><button className="bt d sm" type="button" disabled={isSaving || !selected.can_delete} title={!selected.can_delete ? 'Teams with active assignments or deployed members cannot be deleted' : undefined} onClick={() => onDelete(selected.team_id, selected.team_name)}>Delete team</button></div><div className="ln"><span>Leader<b>{selected.leader_name || '—'}</b></span><span>Type<b>{selected.team_type || '—'}</b></span><span>Assigned area<b>{selected.assigned_purok || 'Barangay-wide'}</b></span></div>{memberRows(members, 'members')}</> : <div className="emp">Select a team or add a new team.</div> : <form className="tm-team-form" onSubmit={submit}><div className="th"><span className="tt">{form.team_id ? 'Update team' : 'New team'}</span>{!form.team_id && <span className="rt" style={{ '--c': '#0ea5e9' }}>New</span>}</div><fieldset disabled={isSaving}><div className="fg"><label className="fl s3"><span>Team name</span><input {...constraints.team_name} required value={form.team_name} onChange={e => update('team_name', e.target.value)} /></label><label className="fl s3"><span>Team code</span><input disabled value={form.team_code} placeholder="Assigned on save" /></label><label className="fl s2"><span>Type</span><input required {...constraints.team_type} list="tm-team-types" value={form.team_type} onChange={e => update('team_type', e.target.value)} /><datalist id="tm-team-types">{(workspace.team_types || []).map(type => <option key={type} value={type} />)}</datalist></label><label className="fl s2"><span>Duty status</span><select required value={form.duty_status} onChange={e => update('duty_status', e.target.value)}><option value="">Select</option>{(workspace.duty_statuses || []).map(status => <option key={status.key} value={status.key}>{status.label}</option>)}</select></label><label className="fl s2"><span>Assigned area</span><select value={form.assigned_purok_id} onChange={e => update('assigned_purok_id', e.target.value)}><option value="">Barangay-wide</option>{(workspace.puroks || []).map(purok => <option key={purok.address_id} value={purok.address_id}>{purok.label}</option>)}</select></label><label className="fl s6"><span>Team leader</span><select value={form.leader_responder_id} onChange={e => update('leader_responder_id', e.target.value)}><option value="">Select</option>{members.map(r => <option key={r.responder_id} value={r.responder_id}>{r.full_name}</option>)}</select></label></div></fieldset><div className="xf"><div className="xl"><div className="xh">Available<span className="sp" />{available.length}</div>{memberRows(available, 'available')}</div><div className="xl"><div className="xh">Members<span className="sp" />{members.length}</div>{memberRows(members, 'members')}</div></div><div className="tm-team-actions"><button className="bt" type="button" disabled={isSaving} onClick={() => { if (selected) choose(selected); else if (teams[0]) choose(teams[0]); else { setEditing(false); setLocalError('') } }}>Cancel</button><button className="bt p nx" type="submit" disabled={isSaving}>{isSaving ? 'Saving…' : 'Save team'}</button></div></form>}</section>
      </div>
    </>}
  </div>
  return embedded ? content : createPortal(<div className="tm-page ov on"><div className="md" role="dialog" aria-modal="true" aria-label="Team management"><div className="mh2"><h3>Team Management</h3><span className="sp" /><button className="x" onClick={onClose} aria-label="Close">×</button></div>{content}</div></div>, document.body)
}
