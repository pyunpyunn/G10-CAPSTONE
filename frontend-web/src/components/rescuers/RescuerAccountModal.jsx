import { useState } from 'react'
import { createPortal } from 'react-dom'
import { AlertTriangle, Award, BookOpen, Calendar, CheckCircle, Droplet, Mail, MapPin, MoreHorizontal, Phone, Shield, User, Users } from 'lucide-react'
import { accountIdForTeam, fullNameFromForm } from '../../utils/rescuerHelpers'

export function StatusDot({ children, muted = false }) {
  return <span className="bg" style={{ '--c': muted ? '#94a3b8' : '#0ea5e9' }}>{children}</span>
}

export function AccountProfile({ form, onEdit, onDeactivate, onDelete, onReactivate, role = 'Rescuer' }) {
  const [menu, setMenu] = useState(false)
  const name = form.full_name || fullNameFromForm(form) || 'Unnamed account'
  const accountStatus = form.account_status_display?.label || (form.is_active === false ? 'Inactive' : 'Active')
  const active = accountStatus.toLowerCase() === 'active'
  const duty = form.duty_status_display?.label
  const skills = Array.isArray(form.skills) ? form.skills : String(form.skills || '').split(/[,;\n]/).map(s => s.trim()).filter(Boolean)
  const metric = (Icon, color, label, value) => <div className="k" style={{ '--c': color }}><span className="ic"><Icon size={20} /></span><div><em>{label}</em><b title={value || ''}>{value || 'Not recorded'}</b></div></div>
  const field = (Icon, label, value, link) => <div className="fd"><span className="fi"><Icon size={16} /></span><div><em>{label}</em><b>{value ? link ? <a href={`${link}${value}`}>{value}</a> : value : <span className="mu">Not recorded</span>}</b></div></div>
  return <article className="cd prof" aria-label={`${name} profile`}>
    <div className="cover"><span className="slot" /><span className="rl2">{role}</span>
      <div className="ca">{onEdit && <button className="bt sm" type="button" onClick={onEdit}>Edit profile</button>}
        {(onDeactivate || onDelete || onReactivate) && <div className="tm-menu-anchor"><button className="mb3" type="button" aria-label="Profile actions" aria-expanded={menu} onClick={() => setMenu(!menu)}><MoreHorizontal size={16} /></button>{menu && <><button className="tm-menu-dismiss" aria-label="Close profile actions" onClick={() => setMenu(false)} /><div className="pp tm-menu" role="menu">{onEdit && <button role="menuitem" onClick={() => { setMenu(false); onEdit() }}>Edit</button>}{active && onDeactivate && <button role="menuitem" onClick={() => { setMenu(false); onDeactivate() }}>Deactivate</button>}{!active && onReactivate && <button role="menuitem" onClick={() => { setMenu(false); onReactivate() }}>Reactivate</button>}{onDelete && <button role="menuitem" onClick={() => { setMenu(false); onDelete() }}>Delete user</button>}</div></>}</div>}
      </div>
      <span className="pav" style={{ '--c': '#3b82f6' }}>{initials(name)}</span><div className="cn"><div className="nm">{name}</div><div className="cm">{form.account_id || form.responder_code || '—'} · {form.title || role}</div><div className="pbd">{duty && <StatusDot muted={/off|disabled/i.test(duty)}>{duty}</StatusDot>}<StatusDot muted={!active}>{accountStatus}</StatusDot></div></div>
    </div>
    <div className="pbody">
      <div className="kp">{metric(Users, '#2563eb', 'Team', form.team_display_name || form.team_name || 'Unassigned')}{metric(Shield, '#7c3aed', 'Role', form.title || role)}{metric(Droplet, '#0891b2', 'Blood type', form.blood_type)}{metric(Calendar, '#0d9488', 'Born', form.date_of_birth)}</div>
      <div className="pgd"><ProfileSection Icon={User} title="About"><div className="pf2">{field(Phone, 'Mobile', form.contact_number, 'tel:')}{field(Mail, 'Email', form.email, 'mailto:')}{field(MapPin, 'Address', form.address)}{field(User, 'Gender', form.gender)}</div></ProfileSection>
        <ProfileSection Icon={AlertTriangle} title="Emergency contact" className="emg">{form.emergency_contact_name ? <><div className="ec"><span className="av" style={{ '--c': '#0891b2' }}>{initials(form.emergency_contact_name)}</span><div><b>{form.emergency_contact_name}</b><em>Primary contact</em></div></div>{form.emergency_contact_number && <a className="call" href={`tel:${form.emergency_contact_number}`}><Phone size={15} />{form.emergency_contact_number}</a>}</> : <span className="mu">Not recorded</span>}</ProfileSection>
      </div>
      {role === 'Rescuer' && <ProfileSection Icon={Award} title="Qualifications"><div><em className="lb">Skills</em>{skills.length ? <div className="chs sk">{skills.map(skill => <span key={skill} className="rt" style={{ '--c': '#3b82f6' }}>{skill}</span>)}</div> : <span className="mu">Not recorded</span>}</div><div className="pf2">{field(BookOpen, 'Training', form.training_notes)}{field(CheckCircle, 'Certification', form.certification_reference)}{field(Shield, 'Equipment', form.equipment_notes)}</div></ProfileSection>}
    </div>
  </article>
}

export default function RescuerAccountModal({ mode, isOpen, form, setForm, formError, isSaving, teamOptions = [], accountIdOptions = [], fallbackAccountId, formOptions, onClose, onReset, onSubmit, onEdit, embedded = false, roleSelector }) {
  const [activeSection, setActiveSection] = useState('personal')
  const [customSkill, setCustomSkill] = useState('')
  if (!isOpen) return null
  const isEdit = mode === 'edit'
  const constraints = formOptions?.constraints?.[isEdit ? 'edit' : 'create'] || {}
  const update = (key, value) => setForm(current => ({ ...current, [key]: value }))
  const skills = String(form.skills || '').split(/[,;\n]/).map(s => s.trim()).filter(Boolean)
  const skillOptions = [...new Set(['First Aid', 'Water Rescue', 'Search & Rescue', 'Basic Life Support', ...skills])]
  const sections = [['personal', 'Personal'], ['qualifications', 'Qualifications'], ['emergency', 'Emergency'], ['assignment', 'Team & duty'], ['access', 'Access']]
  if (isEdit) sections.unshift(...sections.splice(3, 1))
  function selectTeam(value) {
    const team = teamOptions.find(t => t.team_name === value)
    setForm(current => ({ ...current, account_id: isEdit ? current.account_id : accountIdForTeam(accountIdOptions, value, fallbackAccountId), team_id: team?.team_id || '', team_code: team?.team_code || '', team_name: value, team_type: team?.team_type || '' }))
  }
  const field = (key, label, span = 's2', type = 'text') => <label className={`fl ${span}`}><span>{label}</span><input type={type} value={form[key] || ''} {...constraints[key]} onChange={event => update(key, event.target.value)} /></label>
  const select = (key, label, options, span = 's3') => <label className={`fl ${span}`}><span>{label}</span><select value={form[key] || ''} {...constraints[key]} onChange={event => update(key, event.target.value)}><option value="">Select</option>{options.map(option => <option key={typeof option === 'string' ? option : option.key} value={typeof option === 'string' ? option : option.key}>{typeof option === 'string' ? option : option.label}</option>)}</select></label>
  const bodies = {
    personal: <div className="fg">{field('first_name', 'First name')}{field('last_name', 'Last name')}{field('middle_initial', 'M.I.')}{field('contact_number', 'Mobile', 's2', 'tel')}{field('email', 'Email', 's4', 'email')}{field('date_of_birth', 'Date of birth', 's2', 'date')}{select('gender', 'Gender', ['Male', 'Female', 'Other'], 's2')}{select('blood_type', 'Blood type', formOptions?.blood_types || ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'], 's2')}{field('address', 'Address / Purok', 's6')}</div>,
    qualifications: <><div className="fl"><span>Skills</span><div className="chs">{skillOptions.map(skill => <button type="button" key={skill} className={skills.includes(skill) ? 'on' : ''} onClick={() => update('skills', (skills.includes(skill) ? skills.filter(s => s !== skill) : [...skills, skill]).join(', '))}>{skill}</button>)}<input className="ski" aria-label="Custom skill" placeholder="+ Skill" value={customSkill} onChange={e => setCustomSkill(e.target.value)} onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); if (customSkill.trim()) { update('skills', [...new Set([...skills, customSkill.trim()])].join(', ')); setCustomSkill('') } } }} onBlur={() => { if (customSkill.trim()) { update('skills', [...new Set([...skills, customSkill.trim()])].join(', ')); setCustomSkill('') } }} /></div></div><div className="fg">{field('training_notes', 'Training / certification', 's3')}{field('certification_reference', 'Certification reference', 's3')}{field('equipment_notes', 'Equipment', 's6')}</div></>,
    emergency: <div className="fg">{field('emergency_contact_name', 'Name', 's3')}{field('emergency_contact_number', 'Number', 's3', 'tel')}</div>,
    assignment: <div className="fg"><label className="fl s3"><span>Team</span><select value={form.team_name || ''} {...constraints.team_name} onChange={e => selectTeam(e.target.value)}><option value="">Unassigned</option>{[...new Set([form.team_name, ...teamOptions.map(t => t.team_name)].filter(Boolean))].map(name => <option key={name}>{name}</option>)}</select></label><label className="fl s3"><span>Title</span><input list="tm-rescuer-titles" value={form.title || ''} {...constraints.title} onChange={e => update('title', e.target.value)} /><datalist id="tm-rescuer-titles">{(formOptions?.roles || []).map(title => <option key={title} value={title} />)}</datalist></label>{select('duty_status', 'Duty', formOptions?.duty_statuses || [])}{select('account_status', 'Status', formOptions?.account_statuses || [])}</div>,
    access: <div className="fg"><label className="fl s3"><span>Account ID</span><input value={form.account_id || fallbackAccountId || ''} disabled /></label><label className="fl s3"><span>{isEdit ? 'New password (optional)' : 'Temporary password'}</span><div className="tm-password"><input type="password" autoComplete="new-password" value={form.password || ''} {...constraints.password} onChange={e => update('password', e.target.value)} /><button type="button" className="bt sm" onClick={() => update('password', `Tmp-${Array.from(crypto.getRandomValues(new Uint8Array(8)), n => n.toString(16).padStart(2, '0')).join('')}`)}>Generate</button></div></label></div>,
  }
  const content = mode === 'view' ? <AccountProfile form={form} onEdit={onEdit} /> : <div className="cd tm-account-editor"><header className="mh2"><h3>{isEdit ? 'Edit account' : 'New account'}</h3></header>{formError && <div className="tm-feedback" role="alert">{formError}</div>}<form id="rescuerAccountForm" className="fm" onSubmit={onSubmit}><aside className="rail">{sections.map(([id, label], i) => <button type="button" key={id} className={activeSection === id ? 'on' : ''} onClick={() => { setActiveSection(id); document.getElementById(`tm-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }) }}><i>{i + 1}</i>{label}</button>)}</aside><section className="fbody">{roleSelector}{sections.map(([id, label]) => <section className="sec" id={`tm-${id}`} key={id}><div className="st">{label === 'Qualifications' ? 'Qualifications & equipment' : label === 'Access' ? 'Account access' : label === 'Emergency' ? 'Emergency contact' : label}</div>{bodies[id]}</section>)}</section></form><footer className="mf"><button className="bt" type="button" disabled={isSaving} onClick={onClose}>Cancel</button><button className="bt" type="button" disabled={isSaving} onClick={onReset}>Reset changes</button><button className="bt p nx" type="submit" form="rescuerAccountForm" disabled={isSaving}>{isSaving ? 'Saving…' : isEdit ? 'Save changes' : 'Create account'}</button></footer></div>
  return embedded ? content : createPortal(<div className="tm-page ov on" onClick={e => { if (e.target === e.currentTarget) onClose() }}><div className="md" role="dialog" aria-modal="true" aria-label="Account profile"><button className="x tm-dialog-close" onClick={onClose} aria-label="Close">×</button>{content}</div></div>, document.body)
}

function ProfileSection({ Icon, title, children, className = '' }) {
  return <section className={`pc ${className}`}><header><Icon size={16} /><h4>{title}</h4></header>{children}</section>
}
function initials(name = '') {
  return name.replace(/^Hon\.\s*/i, '').split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('') || '?'
}
