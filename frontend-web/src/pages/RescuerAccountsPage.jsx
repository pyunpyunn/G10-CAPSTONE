import { useEffect, useRef, useState } from 'react'
import { useLocation, useNavigate, useOutletContext } from 'react-router-dom'
import { createRescueTeam,createRescuer,deleteRescueTeam,deactivateRescuer,deleteRescuer,getRescueTeamConfig,getRescuer,getRescuers,updateRescueTeam,updateRescuer,getPersonnelRoles } from '../api/rescuerApi'
import { getHeadquartersAccounts,saveHeadquartersAccount } from '../api/headquartersAccountsApi'
import { getCurrentUser } from '../api/authApi'
import { buildRescuerPayload,formFromRescuer,rescuerErrorMessage } from '../utils/rescuerHelpers'
import { AccountDialog,AccountEditor } from '../components/rescuers/AccountReferenceForms'
import AccountReferenceTeamEditor from '../components/rescuers/AccountReferenceTeamEditor'
import LegacyTeamManagementPage from './LegacyTeamManagementPage'
import '../styles/account-html.css'

export default function RescuerAccountsPage() {
  const location=useLocation()
  if (location.pathname === '/rescuers/teams') return <LegacyTeamManagementPage />
  return <AccountsWorkspace key={`${location.pathname}${location.search}`} />
}
function AccountsWorkspace() {
  const {onUserChange}=useOutletContext(),location=useLocation(),navigate=useNavigate()
  const route=location.pathname.split('/').pop(),params=new URLSearchParams(location.search)
  const requestedId=params.get('id') || location.state?.rescuer?.responder_id
  const view=route==='teams' ? 'teams' : params.get('manage')==='roles' ? 'roles' : 'accounts'
  const [category,setCategory]=useState(location.state?.category==='headquarters' || params.get('type')==='command_center' ? 'command_center' : 'rescuer')
  const [page,setPage]=useState(1),[teamFilter,setTeamFilter]=useState('all'),[selectedId,setSelectedId]=useState(requestedId || null)
  const [payload,setPayload]=useState(null),[teams,setTeams]=useState(null),[roleWorkspace,setRoleWorkspace]=useState(null),[hq,setHq]=useState(null)
  const [detail,setDetail]=useState(null),[loading,setLoading]=useState(true),[detailLoading,setDetailLoading]=useState(false)
  const [error,setError]=useState(''),[notice,setNotice]=useState(location.state?.notice || ''),[busy,setBusy]=useState(false),[revision,setRevision]=useState(0)
  const [modal,setModal]=useState(null),[newTeam,setNewTeam]=useState('')
  const detailVersion=useRef(0),savedSelection=useRef(null)
  useEffect(() => {
    let active=true
    async function load() {
      setLoading(true);setError('')
      const results=await Promise.allSettled([getRescuers({page,per_page:8,team:teamFilter,account_type:category}),getRescueTeamConfig(),getPersonnelRoles(),getHeadquartersAccounts()])
      if(!active) return
      const setters=[setPayload,setTeams,setRoleWorkspace,setHq],failures=[]
      results.forEach((result,index) => {if(result.status==='fulfilled') setters[index](result.value);else failures.push(rescuerErrorMessage(result.reason,'Account settings could not be loaded.'))})
      if(results[0].status==='fulfilled') {
        const roster=results[0].value.rescuers
        if(page>1 && !roster.data.length) setPage(Math.max(1,roster.last_page))
        else { const savedId=savedSelection.current; savedSelection.current=null; setSelectedId(current => requestedId || savedId || (roster.data.some(r => String(r.responder_id)===String(current)) ? current : roster.data[0]?.responder_id || null)) }
      }
      setError([...new Set(failures)].join(' '));setLoading(false)
    }
    load();return () => {active=false}
  },[page,teamFilter,category,revision,requestedId])
  useEffect(() => {
    if(!selectedId) return
    const version=++detailVersion.current
    let active=true
    async function loadDetail() {
      setDetailLoading(true)
      try {const result=await getRescuer(selectedId);if(active && version===detailVersion.current) setDetail(result.rescuer)}
      catch(failure) {if(active) {setDetail(null);setError(rescuerErrorMessage(failure,'Account details could not be loaded.'))}}
      finally {if(active) setDetailLoading(false)}
    }
    loadDetail();return () => {active=false}
  },[selectedId,revision])
  useEffect(() => {
    const update=() => {if(!document.hidden && !busy && !modal) setRevision(current => current+1)}
    window.addEventListener('personnel-access:changed',update)
    return () => window.removeEventListener('personnel-access:changed',update)
  },[busy,modal])
  useEffect(() => {if(!notice) return;const timer=setTimeout(() => setNotice(''),4500);return () => clearTimeout(timer)},[notice])
  async function refreshAccess() {try{onUserChange?.(await getCurrentUser())}catch{/* The API checks access again on every request. */}}
  function closeModal() {
    if(busy) return
    setModal(null)
    if(['new','edit'].includes(route)) navigate('/rescuers',{state:{category:category==='command_center' ? 'headquarters' : 'responders'}})
  }
  function completed(message) {setModal(null);setNotice(message);setRevision(current => current+1)}
  async function saveAccount(body,type,account) {
    setBusy(true)
    try {
      const result=account ? await updateRescuer(account.responder_id,body) : type==='command_center' ? await saveHeadquartersAccount({...body,account_type:'command_center'}) : await createRescuer(body)
      const saved=account?.responder_id || result.rescuer?.responder_id || result.data?.account?.responder_id
      if(saved) {savedSelection.current=saved;setSelectedId(saved)}
      setCategory(type);setPage(1);setTeamFilter('all')
      if(['new','edit'].includes(route)) navigate('/rescuers',{state:{category:type==='command_center' ? 'headquarters' : 'responders',notice:account ? 'Profile updated.' : 'Account created.'}})
      else completed(account ? 'Profile updated.' : 'Account created.')
      await refreshAccess()
    } finally {setBusy(false)}
  }
  async function saveDutyStatus(account,status) {
    setBusy(true);setError('')
    try {
      await updateRescuer(account.responder_id,buildRescuerPayload({...formFromRescuer(account),duty_status:status},'edit'))
      completed('Duty status updated.')
    } catch(failure) {setError(rescuerErrorMessage(failure))}
    finally {setBusy(false)}
  }
  async function saveTeam(body) {
    setBusy(true)
    try {setTeams(body.team_id ? await updateRescueTeam(body.team_id,body) : await createRescueTeam(body));setNewTeam('');completed('Team saved.');await refreshAccess()}
    finally {setBusy(false)}
  }
  async function confirmAction() {
    if(!modal || busy) return
    setBusy(true)
    try {
      if(modal.action==='team') setTeams(await deleteRescueTeam(modal.record.team_id))
      else if(modal.action==='delete') await deleteRescuer(modal.record.responder_id)
      else if(modal.action==='deactivate') await deactivateRescuer(modal.record.responder_id)
      else await updateRescuer(modal.record.responder_id,buildRescuerPayload({...formFromRescuer(modal.record),account_status:'active',duty_status:modal.record.duty_status?.key==='disabled' ? 'available' : modal.record.duty_status?.key},'edit'))
      completed(modal.action==='team' ? 'Team deleted.' : modal.action==='delete' ? 'Account deleted.' : modal.action==='deactivate' ? 'Account deactivated.' : 'Account reactivated.')
      await refreshAccess()
    } finally {setBusy(false)}
  }
  const rows=payload?.rescuers?.data || [],pager=payload?.rescuers || {},roles=roleWorkspace?.roles || []
  const editor=modal?.kind==='account' ? modal : route==='new' ? {kind:'account',account:null} : route==='edit' && detail ? {kind:'account',account:detail} : null
  const webRoleKeys=['chair','operations_admin','mitigation_prevention','preparedness','response','recovery_rehabilitation']
  const editorCategory=editor?.account && (webRoleKeys.includes(editor.account.role_key) || String(editor.account.team_code).toUpperCase()==='HQCC') ? 'command_center' : category
  const hqCategory=category==='command_center',selectedDetail=detail && String(detail.responder_id)===String(selectedId) ? detail : null
  const ask=(action,record) => setModal({kind:'confirm',action,record})
  const closeManagement=() => navigate('/rescuers')
  return <section className="account-html" aria-label="Account Management"><div className="bar">{view==='accounts' ? <><div className="seg" role="tablist" aria-label="Account category">{[['rescuer','Field teams'],['command_center','Committee / Operations']].map(([key,label]) => <button key={key} role="tab" aria-selected={category===key} disabled={busy} onClick={() => {setCategory(key);setPage(1);setTeamFilter('all');setSelectedId(null);setDetail(null)}}>{label} <small>{teams?.responders?.filter(r => r.account_type===key).length || 0}</small></button>)}</div><span className="sp" /><button className="btn" disabled={busy} onClick={() => navigate('/rescuers?manage=roles')}>Manage roles</button><button className="btn" disabled={busy} onClick={() => navigate('/rescuers/teams')}>Manage teams</button><button className="btn p" disabled={busy || !payload || !roleWorkspace} onClick={() => setModal({kind:'account',account:null})}>+ Create account</button></> : <><button className="btn" onClick={closeManagement}>‹ Back to accounts</button><h2 className="vt">Manage {view}</h2></>}</div>{error && <div className="notices"><div className="err" role="alert">{error} <button className="btn" disabled={busy} onClick={() => setRevision(current => current+1)}>Retry</button></div></div>}
    {view==='accounts' && <div className="grid"><aside className="ros"><div className="rh"><b>{hqCategory ? 'Committee / Operations' : 'Field teams'}</b><span className="chip">{pager.total || 0} total</span></div>{!hqCategory && <><label className="l" htmlFor="account-team-filter">Team</label><select id="account-team-filter" value={teamFilter} disabled={busy} onChange={event => {setTeamFilter(event.target.value);setPage(1)}}><option value="all">All teams</option>{(payload?.team_options || []).filter(t => String(t.team_code).toUpperCase()!=='HQCC').map(t => <option key={t.team_id} value={String(t.team_id)}>{t.team_name}</option>)}</select></>}<div className="rl">{loading ? Array.from({length:4},(_,i) => <div className="skl" key={i} role={i===0 ? 'status' : undefined} aria-label={i===0 ? 'Loading accounts' : undefined} />) : rows.length ? rows.map(r => <button key={r.responder_id} type="button" className={`row ${String(selectedId)===String(r.responder_id) ? 'sel' : ''}`} aria-pressed={String(selectedId)===String(r.responder_id)} onClick={() => setSelectedId(r.responder_id)}><span className="av s">{initials(r.full_name)}</span><span className="rt"><b>{r.full_name}</b><small>{r.title}{r.is_active===false ? ' · Inactive' : ''}</small></span></button>) : <div className="empty">No accounts{teamFilter!=='all' ? ' in this team' : ''}.{teamFilter!=='all' && <p><button className="btn" onClick={() => {setTeamFilter('all');setPage(1)}}>Show all teams</button></p>}</div>}</div><div className="pgn"><span>{pager.total ? `${pager.from}–${pager.to} of ${pager.total}` : '0 of 0'}</span><span><button type="button" className="btn" disabled={loading || page<=1} aria-label="Previous page" onClick={() => setPage(p => p-1)}>‹</button> <button type="button" className="btn" disabled={loading || page>=(pager.last_page || 1)} aria-label="Next page" onClick={() => setPage(p => p+1)}>›</button></span></div></aside><div>{loading || detailLoading ? <div className="card"><div className="skl" /><div className="skl" /></div> : selectedDetail ? <Profile key={selectedDetail.responder_id} account={selectedDetail} dutyStatuses={payload?.form_options?.duty_statuses || []} onDutyChange={saveDutyStatus} onEdit={() => setModal({kind:'account',account:selectedDetail})} onAction={action => ask(action,selectedDetail)} busy={busy} /> : <div className="card empty">Select an account to view its profile.</div>}</div></div>}
    {view==='teams' && <div className="grid management-grid"><div className="card"><label className="l" htmlFor="new-team-name">New team</label><form style={{display:'flex',gap:8,maxWidth:420}} onSubmit={event => {event.preventDefault();setModal({kind:'team',record:{...teams.form_defaults,team_name:newTeam,member_ids:[]},creating:true})}}><input id="new-team-name" required placeholder="Team name" value={newTeam} onChange={event => setNewTeam(event.target.value)} /><button type="submit" className="btn p" disabled={loading || !teams}>Add team</button></form><div className="tbl"><table><thead><tr><th>Team</th><th className="n">Members</th><th>Leader</th><th className="n">Actions</th></tr></thead><tbody>{(teams?.teams || []).map(t => <tr key={t.team_id}><td><b>{t.team_name}</b></td><td className="n">{t.member_count}</td><td>{t.leader_name || 'Not recorded'}</td><td className="n"><button className="btn" onClick={() => setModal({kind:'team',record:t})}>Edit</button> <button className="btn dg" disabled={!t.can_delete} onClick={() => ask('team',t)}>Delete</button></td></tr>)}</tbody></table></div><p className="note">Renaming a team updates its members. Deleting a team leaves its members unassigned and preserves past dispatch records.</p></div></div>}
    {view==='roles' && <div className="grid management-grid"><div className="card"><p className="note">Access roles follow the BDRRMC committee structure. Role definitions are fixed; assign the appropriate role when creating or editing an account.</p><div className="tbl"><table><thead><tr><th>Role</th><th>Account type</th><th>Web access</th><th className="n">Accounts</th></tr></thead><tbody>{roles.map(r => <tr key={r.role_id}><td><b>{r.role_name}</b></td><td>{r.base_role_key.replaceAll('_',' ')}</td><td>{r.web_modules.map(key => key.replaceAll('_',' ')).join(', ') || 'Mobile field access'}</td><td className="n">{r.member_count}</td></tr>)}</tbody></table></div></div></div>}
    {editor && payload && roleWorkspace && <AccountEditor key={editor.account?.responder_id || 'new-account'} account={editor.account} category={editorCategory} payload={payload} roles={roles} hqNextIds={hq?.next_account_ids} busy={busy} onClose={closeModal} onSave={saveAccount} />}
    {modal?.kind==='team' && <AccountReferenceTeamEditor key={modal.record?.team_id || 'new-team'} team={modal.record} workspace={teams} busy={busy} onClose={closeModal} onSave={saveTeam} />}
    {modal?.kind==='confirm' && <Confirmation action={modal.action} record={modal.record} roles={roles} busy={busy} onClose={closeModal} onConfirm={confirmAction} />}
    {notice && <div className="toast" role="status">{notice}</div>}
  </section>
}
function Profile({account:p,dutyStatuses,onDutyChange,onEdit,onAction,busy}) {
  const hq=String(p.team_code).toUpperCase()==='HQCC' || ['chair','operations_admin','mitigation_prevention','preparedness','response','recovery_rehabilitation'].includes(p.role_key),active=p.is_active!==false
  const skills=Array.isArray(p.skills) ? p.skills : String(p.skills || '').split(/[,;\n]/).map(s => s.trim()).filter(Boolean)
  const field=(label,value,link) => <div className="dr" key={label}><dt>{label}</dt><dd>{value ? link ? <a href={`${link}${String(value).replace(/\s/g,'')}`}>{value}</a> : value : <span className="nr">Not recorded</span>}</dd></div>
  return <><div className="card hd"><div className="hdr"><span className="av">{initials(p.full_name)}</span><div className="who"><span className="l" style={{margin:0}}>{hq ? 'Headquarters account' : 'Rescuer account'}</span><h1>{p.full_name}</h1><div className="sub">{p.account_id} · {p.title}</div><div className="chs"><Chip tone={active ? 'green' : 'gray'}>Account {active ? 'active' : 'inactive'}</Chip></div></div><div className="acts"><DutyStatusControl key={p.duty_status?.key} account={p} options={dutyStatuses} busy={busy} onSave={onDutyChange} /><button className="btn p" disabled={busy} onClick={onEdit}>Edit profile</button><details className="mw"><summary className="btn">Actions ▾</summary><div className="menu"><button disabled={busy} onClick={() => onAction(active ? 'deactivate' : 'reactivate')}>{active ? 'Deactivate account' : 'Reactivate account'}</button><hr /><button className="dg" disabled={busy} onClick={() => onAction('delete')}>Delete account…</button></div></details></div></div></div><div className="kp">{[['Team',p.team_name],['Role',p.title],['Blood type',p.blood_type]].map(([label,value]) => <div className="k" key={label}><small>{label}</small><b>{value || <span className="nr">Not recorded</span>}</b></div>)}</div><div className="cols"><div className="card"><h3>About</h3><dl>{field('Mobile',p.contact_number,'tel:')}{field('Email',p.email,'mailto:')}{field('Address',p.address)}{field('Gender',p.gender)}</dl></div><div className="card"><h3>Emergency contact</h3><dl>{field('Name',p.emergency_contact_name)}{field('Phone',p.emergency_contact_number,'tel:')}</dl></div></div>{!hq && <div className="card"><h3>Qualifications</h3><div className="chs" style={{margin:'0 0 6px'}}>{skills.length ? skills.map(skill => <span className="chip" key={skill}>{skill}</span>) : <span className="nr">Skills: Not recorded</span>}</div><dl>{field('Training notes',p.training_notes)}{field('Certification ref.',p.certification_reference)}{field('Equipment',p.equipment_notes)}</dl></div>}</>
}
function DutyStatusControl({account,options,busy,onSave}) {
  const current=account.duty_status?.key || ''
  const [value,setValue]=useState(current)
  return <form className="duty-status-control" onSubmit={event => {event.preventDefault();onSave(account,value)}}>
    <label htmlFor="profile-duty-status">Duty status</label>
    <Chip tone={account.duty_status?.tone || 'gray'}>{account.duty_status?.label || 'Not recorded'}</Chip>
    <div className="duty-status-actions"><select id="profile-duty-status" value={value} disabled={busy} onChange={event => setValue(event.target.value)}>
      {!options.some(option => option.key===current) && <option value={current}>{account.duty_status?.label || 'Select status'}</option>}
      {options.map(option => <option key={option.key} value={option.key}>{option.label}</option>)}
    </select><button className="btn p" type="submit" disabled={busy || !value || value===current}>{busy ? 'Saving…' : 'Update status'}</button></div>
  </form>
}
function Confirmation({action,record,roles,busy,onClose,onConfirm}) {
  const [replacement,setReplacement]=useState(''),[error,setError]=useState('')
  const titles={team:'Delete this team?',role:'Delete this role?',delete:'Delete this account?',deactivate:'Deactivate this account?',reactivate:'Reactivate this account?'}
  const options=roles.filter(r => r.role_id!==record.role_id && r.base_role_key===record.base_role_key)
  const rows=action==='team' ? [['Account assignments',`${record.member_count} members become Unassigned.`],['Roster records','Accounts stay on the roster.'],['Dispatch history','Past records are retained.']] : action==='role' ? [['Assigned accounts','Move to the selected replacement role.'],['Access','Updates to the replacement role’s permissions.']] : [['Login access',action==='reactivate' ? 'Sign-in is restored.' : 'The person can no longer sign in.'],['Roster record',action==='delete' ? 'Removed from the roster.' : action==='deactivate' ? 'Stays on the roster, marked Inactive.' : 'Marked Active again.'],['Dispatch history','Kept unchanged.']]
  async function submit(event) {event.preventDefault();setError('');try{await onConfirm(replacement)}catch(failure){setError(rescuerErrorMessage(failure))}}
  return <AccountDialog title={titles[action]} small busy={busy} onClose={onClose}><form onSubmit={submit}><div className="mb"><b>{record.full_name || record.team_name || record.role_name}</b><ul className="eff">{rows.map(([label,value]) => <li key={label}><b>{label}</b>{value}</li>)}</ul>{action==='role' && record.member_count>0 && <label className="fld">Replacement role<select required value={replacement} disabled={busy} onChange={event => setReplacement(event.target.value)}><option value="">Select…</option>{options.map(r => <option key={r.role_id} value={r.role_id}>{r.role_name}</option>)}</select></label>}{error && <div className="err" role="alert">{error}</div>}</div><div className="mf"><button className="btn" type="button" disabled={busy} onClick={onClose}>Cancel</button><button className={`btn ${['team','role','delete'].includes(action) ? 'dg2' : 'p'}`} type="submit" disabled={busy}>{busy ? 'Working…' : action==='reactivate' ? 'Reactivate' : action==='deactivate' ? 'Deactivate' : 'Delete'}</button></div></form></AccountDialog>
}
function Chip({tone,children}) {return <span className="chip"><i style={{background:`var(--${tone})`}} />{children}</span>}
function initials(name='') {return name.trim().split(/\s+/).filter(Boolean).slice(0,2).map(s => s[0]).join('') || '?'}
