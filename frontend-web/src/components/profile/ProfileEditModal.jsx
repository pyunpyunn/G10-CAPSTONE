import { Save, X } from 'lucide-react'
import Modal from '../ui/Modal'

export default function ProfileEditModal({
  isOpen,
  form,
  setForm,
  formError,
  isSaving,
  onClose,
  onSubmit,
}) {
  function updateField(field, value) {
    setForm((currentForm) => ({
      ...currentForm,
      [field]: value,
    }))
  }

  return (
    <Modal
      title="Edit profile"
      isOpen={isOpen}
      onClose={onClose}
      className="profile-modal"
      footer={
        <>
          <button className="btn btn-secondary btn-sm" type="button" disabled={isSaving} onClick={onClose}>
            <X size={14} />
            Cancel
          </button>
          <button className="btn btn-primary btn-sm" type="submit" form="profile-edit-form" disabled={isSaving}>
            <Save size={14} />
            Save profile
          </button>
        </>
      }
    >
      <form id="profile-edit-form" className="profile-form-grid" onSubmit={onSubmit}>
        {formError && <div className="form-error full">{formError}</div>}

        <label>
          <span>First name</span>
          <input
            value={form.first_name}
            onChange={(event) => updateField('first_name', event.target.value)}
            placeholder="First name"
          />
        </label>

        <label>
          <span>Last name</span>
          <input
            value={form.last_name}
            onChange={(event) => updateField('last_name', event.target.value)}
            placeholder="Last name"
          />
        </label>

        <label>
          <span>Email</span>
          <input
            type="email"
            value={form.email}
            onChange={(event) => updateField('email', event.target.value)}
            placeholder="email@example.com"
          />
        </label>

        <label>
          <span>Mobile number</span>
          <input
            value={form.contact_number}
            onChange={(event) => updateField('contact_number', event.target.value)}
            placeholder="09xx-xxx-xxxx"
          />
        </label>

        <label className="full">
          <span>Assigned station</span>
          <input
            value={form.assigned_center_id}
            onChange={(event) => updateField('assigned_center_id', event.target.value)}
            placeholder="Command desk or assigned station"
          />
        </label>
      </form>
    </Modal>
  )
}
