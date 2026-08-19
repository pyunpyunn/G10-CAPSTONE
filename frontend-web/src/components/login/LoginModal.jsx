import { useState } from 'react'
import { Eye, EyeOff, KeyRound, LogIn, X } from 'lucide-react'
import { createPortal } from 'react-dom'
import { getRecoveryQuestions, resetPassword } from '../../api/authApi'

export default function LoginModal({ error, onClose, onLogin }) {
  const [form, setForm] = useState({ login: '', password: '', remember: false })
  const [submitting, setSubmitting] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [recoveryOpen, setRecoveryOpen] = useState(false)
  const [recoveryMethod, setRecoveryMethod] = useState('previous_password')
  const [recoveryQuestions, setRecoveryQuestions] = useState(null)
  const [recoveryForm, setRecoveryForm] = useState({ previous_password: '', answer_1: '', answer_2: '', password: '', password_confirmation: '' })
  const [recoveryError, setRecoveryError] = useState('')
  const [recoveryMessage, setRecoveryMessage] = useState('')

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

  async function openRecovery() {
    setRecoveryOpen(true)
    setRecoveryError('')
    setRecoveryMessage('')

    if (!form.login.trim()) {
      return
    }

    try {
      const data = await getRecoveryQuestions(form.login.trim())
      setRecoveryQuestions(data.questions)
    } catch (recoveryRequestError) {
      setRecoveryError(recoveryRequestError?.response?.data?.message || 'Unable to load recovery questions.')
    }
  }

  async function handleRecoverySubmit(event) {
    event.preventDefault()
    setRecoveryError('')
    setRecoveryMessage('')

    try {
      const data = await resetPassword({
        login: form.login.trim(),
        method: recoveryMethod,
        previous_password: recoveryForm.previous_password,
        answer_1: recoveryForm.answer_1,
        answer_2: recoveryForm.answer_2,
        password: recoveryForm.password,
        password_confirmation: recoveryForm.password_confirmation,
      })
      setRecoveryMessage(data.message || 'Password reset successfully. You can sign in now.')
    } catch (recoveryRequestError) {
      setRecoveryError(recoveryRequestError?.response?.data?.message || recoveryRequestError?.response?.data?.errors?.password?.[0] || 'Recovery verification failed.')
    }
  }

  function updateRecoveryField(event) {
    setRecoveryForm({ ...recoveryForm, [event.target.name]: event.target.value })
  }

  return createPortal(
    <div className="login-modal open" aria-hidden="false">
      <section className="login-card" aria-labelledby="loginTitle" role="dialog" aria-modal="true">
        <div className="login-top">
          <span className="brand-mark">
            <img className="brand-logo-image" src="/favicon.svg" alt="" aria-hidden="true" />
          </span>
          <button
            className="modal-close"
            type="button"
            aria-label="Close login"
            onClick={onClose}
          >
            <X size={16} />
          </button>
        </div>
        <h2 id="loginTitle">{recoveryOpen ? 'Reset password' : 'Sign in'}</h2>

        {!recoveryOpen ? <form className="login-form" onSubmit={handleSubmit}>
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
          <label className="login-remember">
            <input type="checkbox" checked={form.remember} onChange={(event) => setForm({ ...form, remember: event.target.checked })} />
            <span>Keep me signed in on this browser</span>
          </label>
          {error && <div className="form-error">{error}</div>}
          <button className="primary-button" type="submit" disabled={submitting}>
            <LogIn size={16} />
            {submitting ? 'Signing in...' : 'Sign in'}
          </button>
          <button className="login-link-button" type="button" onClick={openRecovery}>Forgot password?</button>
        </form> : <form className="login-form" onSubmit={handleRecoverySubmit}>
          <input type="text" value={form.login} onChange={(event) => setForm({ ...form, login: event.target.value })} placeholder="Account ID" aria-label="Account ID" autoComplete="username" />
          <div className="login-recovery-methods">
            <label><input type="radio" checked={recoveryMethod === 'previous_password'} onChange={() => setRecoveryMethod('previous_password')} /> Verify previous password</label>
            <label><input type="radio" checked={recoveryMethod === 'security_questions'} onChange={() => setRecoveryMethod('security_questions')} /> Answer two security questions</label>
          </div>
          {recoveryMethod === 'previous_password' ? <input type="password" name="previous_password" value={recoveryForm.previous_password} onChange={updateRecoveryField} placeholder="Previous password" /> : <>
            <label><span>{recoveryQuestions?.first || 'First security question'}</span><input type="text" name="answer_1" value={recoveryForm.answer_1} onChange={updateRecoveryField} /></label>
            <label><span>{recoveryQuestions?.second || 'Second security question'}</span><input type="text" name="answer_2" value={recoveryForm.answer_2} onChange={updateRecoveryField} /></label>
          </>}
          <input type="password" name="password" value={recoveryForm.password} onChange={updateRecoveryField} placeholder="New password" />
          <input type="password" name="password_confirmation" value={recoveryForm.password_confirmation} onChange={updateRecoveryField} placeholder="Confirm new password" />
          {recoveryError && <div className="form-error">{recoveryError}</div>}
          {recoveryMessage && <div className="form-success">{recoveryMessage}</div>}
          <button className="primary-button" type="submit"><KeyRound size={16} /> Reset password</button>
          <button className="login-link-button" type="button" onClick={() => setRecoveryOpen(false)}>Back to sign in</button>
        </form>}
      </section>
    </div>,
    document.body,
  )
}
