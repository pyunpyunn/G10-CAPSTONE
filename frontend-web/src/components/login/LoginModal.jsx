import { useState } from 'react'
import { Eye, EyeOff, KeyRound, LogIn, X } from 'lucide-react'
import { createPortal } from 'react-dom'
import { getRecoveryQuestions, resetPassword } from '../../api/authApi'

const recoveryQuestionOptions = [
  ['first_pet', "What was your first pet's name?"],
  ['birth_city', "What's the name of the city where you were born?"],
  ['childhood_nickname', 'What was your childhood nickname?'],
  ['parents_met_city', "What's the name of the city where your parents met?"],
  ['eldest_cousin_first_name', "What's the first name of your eldest cousin?"],
  ['first_school', "What's the name of the first school you attended?"],
]

export default function LoginModal({ error, onClose, onLogin }) {
  const [form, setForm] = useState({ login: '', password: '', remember: false })
  const [submitting, setSubmitting] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [recoveryOpen, setRecoveryOpen] = useState(false)
  const [recoveryStep, setRecoveryStep] = useState('account')
  const [firstTimeChoice, setFirstTimeChoice] = useState('new_password')
  const [recoveryMethod, setRecoveryMethod] = useState('previous_password')
  const [recoveryQuestions, setRecoveryQuestions] = useState(null)
  const [recoveryConfigured, setRecoveryConfigured] = useState(false)
  const [recoveryLookupLoading, setRecoveryLookupLoading] = useState(false)
  const [selectedQuestion1, setSelectedQuestion1] = useState('')
  const [selectedQuestion2, setSelectedQuestion2] = useState('')
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
    setRecoveryError('')
    setRecoveryMessage('')

    if (!form.login.trim()) {
      setRecoveryError('Enter your account ID first so we can find your recovery setup.')
      return
    }

    setRecoveryOpen(true)
    setRecoveryStep('account')
    setRecoveryQuestions(null)
    setRecoveryError('')
  }

  async function lookupRecoveryAccount(event) {
    event.preventDefault()
    setRecoveryError('')
    setRecoveryLookupLoading(true)

    try {
      const data = await getRecoveryQuestions(form.login.trim())
      setRecoveryQuestions(data.questions)
      setRecoveryConfigured(Boolean(data.configured))
      setRecoveryMethod(data.configured ? 'security_questions' : 'previous_password')
      setRecoveryStep('method')
    } catch (recoveryRequestError) {
      setRecoveryError(recoveryRequestError?.response?.data?.message || 'Unable to load recovery questions.')
    } finally {
      setRecoveryLookupLoading(false)
    }
  }

  async function handleRecoverySubmit(event) {
    event.preventDefault()
    setRecoveryError('')
    setRecoveryMessage('')

    if (recoveryStep === 'method') {
      setRecoveryStep('verification')
      return
    }

    if (!recoveryConfigured && firstTimeChoice === 'security_questions' && (!recoveryForm.previous_password || !selectedQuestion1 || !selectedQuestion2 || selectedQuestion1 === selectedQuestion2 || !recoveryForm.answer_1 || !recoveryForm.answer_2)) {
      setRecoveryError('Verify your previous password, choose two different questions, and answer both.')
      return
    }

    if (!recoveryForm.previous_password && recoveryMethod === 'previous_password') {
      setRecoveryError('Enter your last used password first.')
      return
    }

    if (!recoveryForm.password || recoveryForm.password !== recoveryForm.password_confirmation) {
      setRecoveryError('Enter and confirm the new password.')
      return
    }

    try {
      const data = await resetPassword({
        login: form.login.trim(),
        method: recoveryConfigured ? recoveryMethod : 'previous_password',
        previous_password: recoveryForm.previous_password,
        question_1: selectedQuestion1,
        question_2: selectedQuestion2,
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

  function advanceRecovery(event) {
    event.preventDefault()
    setRecoveryError('')

    if (recoveryConfigured && recoveryMethod === 'previous_password' && !recoveryForm.previous_password) {
      setRecoveryError('Enter your last used password first.')
      return
    }

    if (recoveryConfigured && recoveryMethod === 'security_questions' && (!recoveryForm.answer_1 || !recoveryForm.answer_2)) {
      setRecoveryError('Answer both security questions.')
      return
    }

    if (!recoveryConfigured && firstTimeChoice === 'security_questions' && (!recoveryForm.previous_password || !selectedQuestion1 || !selectedQuestion2 || selectedQuestion1 === selectedQuestion2 || !recoveryForm.answer_1 || !recoveryForm.answer_2)) {
      setRecoveryError('Enter the last used password, choose two different questions, and answer both.')
      return
    }

    setRecoveryStep('password')
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
        </form> : recoveryLookupLoading ? <div className="login-form"><div>Looking up account recovery...</div></div> : recoveryStep === 'account' ? <form className="login-form" onSubmit={lookupRecoveryAccount}>
          <input type="text" value={form.login} onChange={(event) => setForm({ ...form, login: event.target.value })} placeholder="Account ID" aria-label="Account ID" autoComplete="username" />
          {recoveryError && <div className="form-error">{recoveryError}</div>}
          <button className="primary-button" type="submit">Continue</button>
          <button className="login-link-button" type="button" onClick={() => setRecoveryOpen(false)}>Back to sign in</button>
        </form> : recoveryStep === 'method' ? <div className="login-form">
          <div className="login-recovery-note">Account found. Choose one recovery method.</div>
          {recoveryConfigured ? <>
            <button className="login-method-button" type="button" onClick={() => { setRecoveryMethod('previous_password'); setRecoveryStep('verification') }}>Enter last used password</button>
            <button className="login-method-button" type="button" onClick={() => { setRecoveryMethod('security_questions'); setRecoveryStep('verification') }}>Answer security questions</button>
          </> : <>
            <button className="login-method-button" type="button" onClick={() => { setFirstTimeChoice('new_password'); setRecoveryMethod('previous_password'); setRecoveryStep('verification') }}>Set a new password</button>
            <button className="login-method-button" type="button" onClick={() => { setFirstTimeChoice('security_questions'); setRecoveryMethod('security_questions'); setRecoveryStep('verification') }}>Set up security questions</button>
          </>}
          <button className="login-link-button" type="button" onClick={() => setRecoveryOpen(false)}>Cancel</button>
        </div> : recoveryStep === 'verification' ? <form className="login-form" onSubmit={advanceRecovery}>
          {recoveryConfigured && recoveryMethod === 'previous_password' ? <input type="password" name="previous_password" value={recoveryForm.previous_password} onChange={updateRecoveryField} placeholder="Last used password" /> : null}
          {!recoveryConfigured && firstTimeChoice === 'security_questions' ? <>
            <input type="password" name="previous_password" value={recoveryForm.previous_password} onChange={updateRecoveryField} placeholder="Last used password" />
            <label><span>Choose question 1</span><select value={selectedQuestion1} onChange={(event) => setSelectedQuestion1(event.target.value)}><option value="">Select a question</option>{recoveryQuestionOptions.map(([value, label]) => <option key={value} value={value} disabled={value === selectedQuestion2}>{label}</option>)}</select></label>
            <input type="text" name="answer_1" value={recoveryForm.answer_1} onChange={updateRecoveryField} placeholder="Answer 1" />
            <label><span>Choose question 2</span><select value={selectedQuestion2} onChange={(event) => setSelectedQuestion2(event.target.value)}><option value="">Select a different question</option>{recoveryQuestionOptions.map(([value, label]) => <option key={value} value={value} disabled={value === selectedQuestion1}>{label}</option>)}</select></label>
            <input type="text" name="answer_2" value={recoveryForm.answer_2} onChange={updateRecoveryField} placeholder="Answer 2" />
          </> : null}
          {recoveryConfigured && recoveryMethod === 'security_questions' ? <>
            <label><span>{recoveryQuestions?.first}</span><input type="text" name="answer_1" value={recoveryForm.answer_1} onChange={updateRecoveryField} /></label>
            <label><span>{recoveryQuestions?.second}</span><input type="text" name="answer_2" value={recoveryForm.answer_2} onChange={updateRecoveryField} /></label>
          </> : null}
          {recoveryError && <div className="form-error">{recoveryError}</div>}
          <button className="primary-button" type="submit">Continue</button>
        </form> : <form className="login-form" onSubmit={handleRecoverySubmit}>
          <input type="password" name="password" value={recoveryForm.password} onChange={updateRecoveryField} placeholder="New password" />
          <input type="password" name="password_confirmation" value={recoveryForm.password_confirmation} onChange={updateRecoveryField} placeholder="Confirm new password" />
          {recoveryError && <div className="form-error">{recoveryError}</div>}
          {recoveryMessage && <div className="form-success">{recoveryMessage}</div>}
          <button className="primary-button" type="submit"><KeyRound size={16} /> Reset password</button>
        </form>}
      </section>
    </div>,
    document.body,
  )
}
