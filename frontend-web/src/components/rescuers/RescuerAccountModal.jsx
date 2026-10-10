import { Pencil, Save, ShieldCheck, X } from 'lucide-react'
import { createPortal } from 'react-dom'
import { accountIdForTeam, fullNameFromForm } from '../../utils/rescuerHelpers'
import Badge from '../ui/Badge'


export default function RescuerAccountModal({ mode, isOpen, form, setForm, formError, isSaving, teamOptions = [], accountIdOptions = [], fallbackAccountId, formOptions, onClose, onReset, onSubmit, onEdit, embedded = false }) {
  if (!isOpen) return null
  const isView = mode === 'view'
  const isEdit = mode === 'edit'
  const name = (isView ? form.full_name : fullNameFromForm(form)) || 'New rescuer'
  const constraints = formOptions?.constraints?.[isEdit ? 'edit' : 'create'] || {}
  const dutyStatus = isView ? form.duty_status_display : formOptions?.duty_statuses?.find((status) => status.key === form.duty_status)
  const accountStatus = isView ? form.account_status_display : formOptions?.account_statuses?.find((status) => status.key === form.account_status)
  const initials = [form.first_name?.[0], form.last_name?.[0]].filter(Boolean).join('') || 'R'
  const update = (key, value) => setForm((current) => ({ ...current, [key]: value }))
  function selectTeam(value) {
    const selected = teamOptions.find((team) => team.team_name === value)
    setForm((current) => ({ ...current,
      account_id: mode === 'create' ? accountIdForTeam(accountIdOptions, value, fallbackAccountId) : current.account_id,
      team_id: selected?.team_id || '', team_code: selected?.team_code || '',
      team_name: selected?.team_name || value, team_type: selected?.team_type || '',
    }))
  }
  function input(key, label, type = 'text') {
    return <Field label={label}><input type={type} value={form[key] || ''} {...constraints[key]} onChange={(event) => update(key, event.target.value)} /></Field>
  }
  function select(key, label, options) {
    return <Field label={label}><select value={form[key]} {...constraints[key]} onChange={(event) => update(key, event.target.value)}><option value="">Select {label.toLowerCase()}</option>{options.map((option) => { const value = typeof option === 'string' ? option : option.key; return <option key={value} value={value}>{typeof option === 'string' ? option : option.label}</option> })}</select></Field>
  }
  function text(key, label) {
    return <Field label={label} full><textarea value={form[key]} {...constraints[key]} rows={3} onChange={(event) => update(key, event.target.value)} /></Field>
  }
  const content = <div className={embedded ? 'ra-profile-page' : 'ra-modal-overlay open'}>
    <div className={embedded ? 'ra-profile-shell' : 'ra-modal ra-profile-shell'} role={embedded ? 'region' : 'dialog'} aria-modal={embedded ? undefined : true} aria-label={isView ? 'Rescuer profile' : 'Rescuer profile form'}>
      <header className="ra-profile-hero">
        <div className="ra-profile-avatar" aria-hidden="true">{initials}</div>
        <div className="ra-profile-identity"><span className="rtc-kicker"><ShieldCheck size={14} /> Rescuer profile</span><h2>{name}</h2><p>{form.title || 'Not recorded'} · {(isView ? form.team_display_name : form.team_name) || 'No team selected'}</p><div className="ra-profile-badges"><Badge tone={accountStatus?.tone}>{accountStatus?.label || 'Not recorded'}</Badge><Badge tone={dutyStatus?.tone}>{dutyStatus?.label || 'Not recorded'}</Badge><span>{form.account_id || 'Account ID assigned on creation'}</span></div></div>
        {isView && <button className="button review" type="button" onClick={onEdit}><Pencil size={16} />Update profile</button>}
        {!embedded && <button type="button" className="ra-modal-close" aria-label="Close profile" onClick={onClose}><X size={16} /></button>}
      </header>
      {formError && <div className="form-error" role="alert">{formError}</div>}
      {isView ? <div className="ra-profile-layout">
        <div className="ra-profile-main">
          <Section title="About" description="Personal and contact information"><Details items={[["Mobile number", form.contact_number], ["Email", form.email], ["Home address / purok", form.address], ["Date of birth", form.date_of_birth], ["Gender", form.gender], ["Blood type", form.blood_type]]} /></Section>
          <Section title="Qualifications" description="Skills and operational preparation"><Details items={[["Skills", form.skills], ["Training / certifications", form.training_notes], ["Certification reference", form.certification_reference], ["Issued equipment", form.equipment_notes]]} /></Section>
        </div>
        <aside className="ra-profile-side"><Section title="Assignment"><Details items={[["Team", form.team_display_name], ["Team type", form.team_type], ["Role", form.title], ["Duty availability", dutyStatus?.label], ["Responder code", form.responder_code]]} /></Section><Section title="Emergency contact"><Details items={[["Contact name", form.emergency_contact_name], ["Contact number", form.emergency_contact_number]]} /></Section></aside>
      </div> : <form id="rescuerAccountForm" className="ra-profile-layout" onSubmit={onSubmit}>
        <div className="ra-profile-main">
          <Section title="Personal information" description="Keep the responder’s identity and contact details current."><div className="ra-form-grid">
            {input('first_name', 'First name', 'text')}{input('last_name', 'Last name', 'text')}{input('middle_initial', 'Middle initial')}{input('contact_number', 'Mobile number', 'tel')}{input('email', 'Email', 'email')}{input('date_of_birth', 'Date of birth', 'date')}{input('gender', 'Gender')}{select('blood_type', 'Blood type', formOptions?.blood_types || [])}<Field label="Home address / purok" full><input {...constraints.address} value={form.address} onChange={(event) => update('address', event.target.value)} /></Field>
          </div></Section>
          <Section title="Qualifications & equipment" description="Record capabilities that help dispatchers assign the right responder."><div className="ra-form-grid">{text('skills', 'Skills')}{text('training_notes', 'Training / certifications')}{input('certification_reference', 'Certification reference')}{text('equipment_notes', 'Issued equipment')}</div></Section>
          <Section title="Emergency contact"><div className="ra-form-grid">{input('emergency_contact_name', 'Contact name')}{input('emergency_contact_number', 'Contact number', 'tel')}</div></Section>
        </div>
        <aside className="ra-profile-side">
          <Section title="Team & duty" description="Set the responder’s operational assignment."><div className="ra-form-grid ra-form-stack">
            <Field label="Team"><select value={form.team_name} {...constraints.team_name} onChange={(event) => selectTeam(event.target.value)}><option value="">Unassigned</option>{[...new Set([form.team_name, ...teamOptions.map((team) => team.team_name)].filter(Boolean))].map((value) => <option key={value} value={value}>{value}</option>)}</select></Field>
            <Field label="Role"><input list="rescuerRoles" value={form.title} {...constraints.title} onChange={(event) => update('title', event.target.value)} /><datalist id="rescuerRoles">{(formOptions?.roles || []).map((role) => <option value={role} key={role} />)}</datalist></Field>{select('duty_status', 'Duty availability', formOptions?.duty_statuses || [])}{select('account_status', 'Account status', formOptions?.account_statuses || [])}
          </div></Section>
          <Section title="Account access"><div className="ra-form-grid ra-form-stack"><Field label="Account ID"><input {...constraints.account_id} value={form.account_id} readOnly /><span className="ra-field-note">{isEdit ? 'Permanent account identifier.' : 'Generated from the selected team.'}</span></Field><Field label={isEdit ? 'New password' : 'Temporary password'}><input type="password" autoComplete="new-password" value={form.password} {...constraints.password} onChange={(event) => update('password', event.target.value)} /><span className="ra-field-note">{isEdit ? 'Leave blank to keep the current password.' : `Use at least ${constraints.password?.minLength} characters.`}</span></Field></div></Section>
        </aside>
      </form>}
      {!isView && <footer className="ra-profile-footer"><span>{isEdit ? 'Update the account and operational profile.' : 'Create a rescuer account for the team roster.'}</span><div><button className="button secondary" type="button" disabled={isSaving} onClick={onClose}>Cancel</button><button className="button secondary" type="button" disabled={isSaving} onClick={onReset}>Reset changes</button><button className="button review" form="rescuerAccountForm" type="submit" disabled={isSaving}><Save size={16} />{isSaving ? 'Saving...' : isEdit ? 'Save changes' : 'Create account'}</button></div></footer>}
    </div>
  </div>
  return embedded ? content : createPortal(content, document.body)
}

function Field({ label, full = false, children }) {
  return <label className={full ? 'full' : ''}><span className="ra-mini-label">{label}</span>{children}</label>
}
function Section({ title, description, children }) {
  return <section className="ra-profile-section"><h3>{title}</h3>{description && <p className="ra-section-description">{description}</p>}{children}</section>
}
function Details({ items }) {
  return <dl className="ra-profile-details">{items.map(([label, value]) => <div key={label}><dt>{label}</dt><dd>{value || 'Not recorded'}</dd></div>)}</dl>
}
