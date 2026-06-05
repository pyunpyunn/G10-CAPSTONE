import { CheckCircle2, RotateCcw, Save, UserPlus, X } from 'lucide-react'
import { createPortal } from 'react-dom'
import { accountIdForTeam } from '../../utils/rescuerHelpers'

const completionChecks = [
  'Identity recorded',
  'Team assigned',
  'Contact + ICE',
  'Training logged',
  'PPE / equipment',
  'Audit trail',
]

export default function RescuerAccountModal({
  mode,
  isOpen,
  form,
  setForm,
  formError,
  isSaving,
  teamOptions,
  accountIdOptions,
  fallbackAccountId,
  roles,
  bloodTypes,
  onClose,
  onReset,
  onSubmit,
}) {
  if (!isOpen) {
    return null
  }

  const isView = mode === 'view'
  const title = isView ? 'View rescuer account' : mode === 'edit' ? 'Update rescuer account' : 'Create verified rescuer account'

  function updateField(field, value) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  function selectTeam(value) {
    const selected = teamOptions.find((team) => team.team_name === value)
    setForm((current) => ({
      ...current,
      account_id: mode === 'create' ? accountIdForTeam(accountIdOptions, value, fallbackAccountId) : current.account_id,
      team_id: selected?.team_id || '',
      team_code: selected?.team_code || '',
      team_name: selected?.team_name || value,
      team_type: selected?.team_type || '',
    }))
  }

  return createPortal(
    <div
      className="ra-modal-overlay open"
      role="presentation"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) {
          onClose()
        }
      }}
    >
      <div className="ra-modal" role="dialog" aria-modal="true" aria-labelledby="rescuerAccountModalTitle">
        <div className="ra-modal-head">
          <div>
            <div className="ra-modal-title" id="rescuerAccountModalTitle">
              <UserPlus size={18} />
              {title}
            </div>
            <div className="ra-modal-sub">HR/HQ records identity, team assignment, credentials, contact, and issued equipment.</div>
          </div>
          <button className="ra-modal-close" type="button" aria-label="Close create account modal" title="Close" onClick={onClose}>
            <X size={16} />
          </button>
        </div>

        <div className="ra-modal-body">
          {formError && <div className="form-error">{formError}</div>}

          <form className="ra-form-grid" id="rescuerAccountForm" onSubmit={onSubmit}>
            <Field label="Team">
              <select value={form.team_name} disabled={isView} onChange={(event) => selectTeam(event.target.value)}>
                {teamOptions.map((team) => (
                  <option value={team.team_name} key={`${team.team_name}-${team.source}`}>{team.team_name}</option>
                ))}
              </select>
            </Field>
            <Field label="Account ID">
              <input value={form.account_id} readOnly />
              <span className="ra-field-note">Auto-generated from selected team code.</span>
            </Field>
            <div className="ra-name-grid full">
              <Field label="First name">
                <input value={form.first_name} readOnly={isView} placeholder="First name" onChange={(event) => updateField('first_name', event.target.value)} />
              </Field>
              <Field label="MI">
                <input value={form.middle_initial} readOnly={isView} placeholder="M." maxLength={3} onChange={(event) => updateField('middle_initial', event.target.value)} />
              </Field>
              <Field label="Last name">
                <input value={form.last_name} readOnly={isView} placeholder="Last name" onChange={(event) => updateField('last_name', event.target.value)} />
              </Field>
            </div>
            <Field label="Account status">
              <select value={form.account_status} disabled={isView} onChange={(event) => updateField('account_status', event.target.value)}>
                <option value="active">Active</option>
                <option value="reserve">Reserve</option>
                <option value="disabled">Disabled</option>
              </select>
            </Field>
            <Field label="Role">
              <select value={form.title} disabled={isView} onChange={(event) => updateField('title', event.target.value)}>
                {roles.map((role) => <option value={role} key={role}>{role}</option>)}
              </select>
            </Field>
            <Field label="Mobile number">
              <input value={form.contact_number} readOnly={isView} type="tel" placeholder="09xx-xxx-xxxx" onChange={(event) => updateField('contact_number', event.target.value)} />
            </Field>
            <Field label="Email">
              <input value={form.email} readOnly={isView} type="email" placeholder="optional email" onChange={(event) => updateField('email', event.target.value)} />
            </Field>
            {!isView && (
              <Field label={mode === 'edit' ? 'New password' : 'Temporary password'}>
                <input value={form.password} type="password" placeholder="minimum 6 characters" onChange={(event) => updateField('password', event.target.value)} />
              </Field>
            )}
            <Field label="Emergency contact">
              <input value={form.emergency_contact_name} readOnly={isView} placeholder="Name" onChange={(event) => updateField('emergency_contact_name', event.target.value)} />
            </Field>
            <Field label="ICE mobile">
              <input value={form.emergency_contact_number} readOnly={isView} placeholder="09xx-xxx-xxxx" onChange={(event) => updateField('emergency_contact_number', event.target.value)} />
            </Field>
            <Field label="Home address / purok">
              <input value={form.address} readOnly={isView} placeholder="Barangay address" onChange={(event) => updateField('address', event.target.value)} />
            </Field>
            <Field label="Blood type">
              <select value={form.blood_type} disabled={isView} onChange={(event) => updateField('blood_type', event.target.value)}>
                {bloodTypes.map((type) => <option value={type} key={type}>{type}</option>)}
              </select>
            </Field>
            <Field label="Duty availability">
              <select value={form.duty_status} disabled={isView} onChange={(event) => updateField('duty_status', event.target.value)}>
                <option value="on_duty">On duty</option>
                <option value="standby">Stand-by</option>
                <option value="reserve">Reserve</option>
                <option value="off_duty">Off duty</option>
                <option value="unavailable">Unavailable</option>
                <option value="dispatched">Dispatched</option>
                <option value="on_scene">On-scene</option>
              </select>
            </Field>
            <Field label="Training / certifications" full>
              <textarea value={form.training_notes} readOnly={isView} placeholder="ICS, first aid/BLS, SAR, evacuation, logistics, radio, driver authorization..." onChange={(event) => updateField('training_notes', event.target.value)} />
            </Field>
            <Field label="Certification reference" full>
              <input value={form.certification_reference} readOnly={isView} placeholder="certificate number, validity, or refresh due date" onChange={(event) => updateField('certification_reference', event.target.value)} />
            </Field>
            <Field label="Skills" full>
              <textarea value={form.skills} readOnly={isView} placeholder="Search and rescue, medical, logistics, radio, evacuation..." onChange={(event) => updateField('skills', event.target.value)} />
            </Field>
            <Field label="Issued equipment" full>
              <textarea value={form.equipment_notes} readOnly={isView} placeholder="PPE, radio, medical kit, vehicle access, rescue gear..." onChange={(event) => updateField('equipment_notes', event.target.value)} />
            </Field>
          </form>

          <div className="ra-checks">
            {completionChecks.map((item) => (
              <div className="ra-check" key={item}><CheckCircle2 size={14} />{item}</div>
            ))}
          </div>
        </div>

        <div className="ra-modal-actions">
          <span className="ra-subtle">Create, view, update, and deactivate actions are audit logged.</span>
          <div className="right">
            {!isView && (
              <button className="btn btn-secondary btn-sm" type="button" disabled={isSaving} onClick={onReset}>
                <RotateCcw size={14} />
                Reset
              </button>
            )}
            <button className="btn btn-secondary btn-sm" type="button" disabled={isSaving} onClick={onClose}>
              <X size={14} />
              Cancel
            </button>
            {!isView && (
              <button className="btn btn-primary btn-sm" type="submit" form="rescuerAccountForm" disabled={isSaving}>
                <Save size={14} />
                {isSaving ? 'Saving...' : 'Save account'}
              </button>
            )}
          </div>
        </div>
      </div>
    </div>,
    document.body,
  )
}

function Field({ label, full = false, children }) {
  return (
    <label className={full ? 'full' : ''}>
      <span className="ra-mini-label">{label}</span>
      {children}
    </label>
  )
}
