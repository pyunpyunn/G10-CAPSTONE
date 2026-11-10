import { useState } from 'react'
import { Eye, EyeOff, KeyRound, X } from 'lucide-react'
import Modal from '../ui/Modal'

export default function PasswordModal({
  isOpen,
  form,
  setForm,
  isSaving,
  onClose,
  onSubmit,
}) {
  const [visible, setVisible] = useState({})

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
      onClose={isSaving ? () => {} : onClose}
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
          <span>Current password</span>
          <div className="password-eye-field">
            <input type={visible.current_password ? 'text' : 'password'} value={form.current_password}
              onChange={(event) => updateField('current_password', event.target.value)} required disabled={isSaving}
              autoComplete="current-password" maxLength={255} />
            <button type="button" className="password-eye-toggle" aria-label={visible.current_password ? 'Hide password' : 'Show password'}
              onClick={() => setVisible((current) => ({ ...current, current_password: !current.current_password }))}>
              {visible.current_password ? <EyeOff size={17} /> : <Eye size={17} />}
            </button>
          </div>
        </label>

        <label>
          <span>New password</span>
          <div className="password-eye-field">
            <input type={visible.password ? 'text' : 'password'} value={form.password}
              onChange={(event) => updateField('password', event.target.value)} required disabled={isSaving}
              minLength={8} maxLength={128} autoComplete="new-password" />
            <button type="button" className="password-eye-toggle" aria-label={visible.password ? 'Hide password' : 'Show password'}
              onClick={() => setVisible((current) => ({ ...current, password: !current.password }))}>
              {visible.password ? <EyeOff size={17} /> : <Eye size={17} />}
            </button>
          </div>
        </label>

        <label>
          <span>Confirm new password</span>
          <div className="password-eye-field">
            <input type={visible.password_confirmation ? 'text' : 'password'} value={form.password_confirmation}
              onChange={(event) => updateField('password_confirmation', event.target.value)} required disabled={isSaving}
              minLength={8} maxLength={128} autoComplete="new-password" />
            <button type="button" className="password-eye-toggle" aria-label={visible.password_confirmation ? 'Hide password' : 'Show password'}
              onClick={() => setVisible((current) => ({ ...current, password_confirmation: !current.password_confirmation }))}>
              {visible.password_confirmation ? <EyeOff size={17} /> : <Eye size={17} />}
            </button>
          </div>
        </label>
      </form>
    </Modal>
  )
}
