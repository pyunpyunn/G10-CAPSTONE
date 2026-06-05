import { useState } from 'react'
import { Eye, EyeOff, LogIn, X } from 'lucide-react'
import { createPortal } from 'react-dom'

export default function LoginModal({ error, onClose, onLogin }) {
  const [form, setForm] = useState({ login: '', password: '' })
  const [submitting, setSubmitting] = useState(false)
  const [showPassword, setShowPassword] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)

    try {
      await onLogin(form)
    } finally {
      setSubmitting(false)
    }
  }

  function updateField(event) {
    setForm({
      ...form,
      [event.target.name]: event.target.value,
    })
  }

  return createPortal(
    <div className="login-modal open" aria-hidden="false">
      <section className="login-card" aria-labelledby="loginTitle" role="dialog" aria-modal="true">
        <div className="login-top">
          <span className="brand-mark">R</span>
          <button
            className="modal-close"
            type="button"
            aria-label="Close login"
            onClick={onClose}
          >
            <X size={16} />
          </button>
        </div>
        <h2 id="loginTitle">Sign in to RESQPERATION</h2>
        <p>Use the account ID assigned by the administrator. The system detects the correct role from the ID and credentials.</p>

        <form className="login-form" onSubmit={handleSubmit}>
          <input
            type="text"
            name="login"
            value={form.login}
            onChange={updateField}
            placeholder="Account ID"
            aria-label="Account ID"
            autoComplete="username"
          />
          <div className="login-password-field">
            <input
              type={showPassword ? 'text' : 'password'}
              name="password"
              value={form.password}
              onChange={updateField}
              placeholder="Password"
              aria-label="Password"
              autoComplete="current-password"
            />
            <button
              className="login-password-toggle"
              type="button"
              aria-label={showPassword ? 'Hide password' : 'Show password'}
              title={showPassword ? 'Hide password' : 'Show password'}
              onClick={() => setShowPassword(!showPassword)}
            >
              {showPassword ? <EyeOff size={17} /> : <Eye size={17} />}
            </button>
          </div>
          <div className="login-note">
            Role access is automatically matched from the account ID, such as HQ/Admin,
            Rescuer, or Household.
          </div>
          {error && <div className="form-error">{error}</div>}
          <button className="primary-button" type="submit" disabled={submitting}>
            <LogIn size={16} />
            {submitting ? 'Logging in...' : 'Login'}
          </button>
        </form>
      </section>
    </div>,
    document.body,
  )
}
