import { KeyRound, X } from 'lucide-react'
import Modal from '../ui/Modal'

export default function PasswordModal({
  isOpen,
  form,
  setForm,
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
      title="Change password"
      isOpen={isOpen}
      onClose={onClose}
      className="profile-modal"
      footer={
        <>
          <button className="btn btn-secondary btn-sm" type="button" disabled={isSaving} onClick={onClose}>
            <X size={14} />
            Cancel
          </button>
          <button className="btn btn-primary btn-sm" type="submit" form="profile-password-form" disabled={isSaving}>
            <KeyRound size={14} />
            Update password
          </button>
        </>
      }
    >
      <form id="profile-password-form" className="profile-form-grid" onSubmit={onSubmit} noValidate>

        <label className="full">
          <span>Old password</span>
          <input
            type="password"
            value={form.current_password}
            onChange={(event) => updateField('current_password', event.target.value)}
            placeholder="Enter old password"
            required
            autoComplete="current-password"
          />
        </label>

        <label>
          <span>New password</span>
          <input
            type="password"
            value={form.password}
            onChange={(event) => updateField('password', event.target.value)}
            placeholder="At least 8 characters"
            required
            minLength={8}
            maxLength={128}
            autoComplete="new-password"
          />
        </label>

        <label>
          <span>Confirm password</span>
          <input
            type="password"
            value={form.password_confirmation}
            onChange={(event) => updateField('password_confirmation', event.target.value)}
            placeholder="Repeat new password"
            required
            minLength={8}
            maxLength={128}
            autoComplete="new-password"
          />
        </label>
      </form>
    </Modal>
  )
}
