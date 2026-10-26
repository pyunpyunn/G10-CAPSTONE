import { useEffect, useRef, useState } from 'react'
import { Eye, EyeOff, KeyRound, LogIn, X } from 'lucide-react'
import { createPortal } from 'react-dom'
import { changePasswordWithOldPassword, verifyPasswordChange, showAuthError, getRememberedLogin, getRememberedPassword, saveRememberedPassword } from '../../api/authApi'
import ResQperationLogo from '../layout/ResQperationLogo'

export default function LoginModal({ onClose, onLogin }) {
  const [changing, setChanging] = useState(false)
  const [rememberPassword, setRememberPassword] = useState(() => Boolean(getRememberedLogin()))
  const [form, setForm] = useState({ login: getRememberedLogin(), password: '', newPassword: '', confirmation: '' })
  const [busy, setBusy] = useState(false)
  const [notice, setNotice] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const dialog = useRef(null)
  const currentForm = useRef(form)
  const checkVersion = useRef(0)
  useEffect(() => {
    const previous = document.activeElement
    dialog.current?.querySelector('input')?.focus()
    const controller = new AbortController()
    const version = checkVersion.current
    getRememberedPassword(controller.signal).then(saved => {
      if (!saved || controller.signal.aborted || version !== checkVersion.current) return
      const next = { ...currentForm.current, ...saved }
      currentForm.current = next
      setForm(next)
    })
    return () => { controller.abort(); previous?.focus() }
  }, [])
  async function fillSavedPassword() {
    const version = checkVersion.current
    const saved = await getRememberedPassword(undefined, 'required')
    if (version !== checkVersion.current) return
    if (!saved) { showAuthError('No saved password was returned. Choose the saved account from your browser password manager, or enter your password and accept the save prompt after login.'); return }
    currentForm.current = { ...currentForm.current, ...saved }
    setForm(currentForm.current)
  }
  function close() { if (!busy) { checkVersion.current += 1; onClose() } }
  function trap(event) {
    if (event.key === 'Escape') { close(); return }
    if (event.key !== 'Tab') return
    const items = [...dialog.current.querySelectorAll('button:not(:disabled), input:not(:disabled)')]
    const first = items[0], last = items.at(-1)
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
  }
  function update(event) {
    const next = { ...currentForm.current, [event.target.name]: event.target.value }
    currentForm.current = next
    checkVersion.current += 1
    setForm(next)
  }
  function errorMessage(failure) {
    const data = failure?.response?.data
    return Object.values(data?.errors || {}).flat()[0] || data?.message || failure.message || 'Unable to continue. Please try again.'
  }
  async function checkField(includePassword = false) {
    const snapshot = currentForm.current
    if (!changing || !snapshot.login.trim() || (includePassword && !snapshot.password)) return
    const version = checkVersion.current
    try { await verifyPasswordChange(snapshot.login, includePassword ? snapshot.password : undefined) }
    catch (failure) { if (version === checkVersion.current) showAuthError(errorMessage(failure)) }
  }
  function switchMode() {
    checkVersion.current += 1
    setChanging(value => !value); setNotice(''); setShowPassword(false)
    currentForm.current = { ...currentForm.current, password: '', newPassword: '', confirmation: '' }
    setForm(currentForm.current)
  }
  async function submit(event) {
    event.preventDefault(); checkVersion.current += 1; setBusy(true); setNotice('')
    try {
      if (!form.login.trim()) throw new Error('Enter your account ID.')
      if (!form.password) throw new Error(changing ? 'Enter your old password.' : 'Enter your password.')
      if (!changing) await onLogin({ login: form.login.trim(), password: form.password, rememberPassword })
      else {
        await verifyPasswordChange(form.login, form.password)
        if (form.newPassword.length < 8 || form.newPassword.length > 128) throw new Error('New password must contain 8 to 128 characters.')
        if (form.newPassword !== form.confirmation) throw new Error('New password and confirmation do not match.')
        if (form.password === form.newPassword) throw new Error('Choose a different new password.')
        await changePasswordWithOldPassword({ login: form.login.trim(), current_password: form.password, password: form.newPassword, password_confirmation: form.confirmation })
        if (rememberPassword && getRememberedLogin() === form.login.trim()) await saveRememberedPassword(form.login, form.newPassword, true)
        setChanging(false); setShowPassword(false)
        currentForm.current = { ...currentForm.current, password: '', newPassword: '', confirmation: '' }
        setForm(currentForm.current)
        setNotice('Password changed. Sign in with your new password.')
      }
    } catch (failure) {
      showAuthError(errorMessage(failure))
    } finally { setBusy(false) }
  }
  function passwordField(name, label, minimum) {
    return <div className="login-password-field" key={name}>
      <input name={name} value={form[name]} onChange={update} onBlur={name === 'password' ? () => checkField(true) : undefined} type={showPassword ? 'text' : 'password'} placeholder={label} aria-label={label} required minLength={minimum} maxLength={name === 'password' ? 255 : 128} disabled={busy} autoComplete={name === 'password' ? 'current-password' : 'new-password'} />
      <button className="login-password-toggle" type="button" aria-label={showPassword ? 'Hide passwords' : 'Show passwords'} title={showPassword ? 'Hide passwords' : 'Show passwords'} onClick={() => setShowPassword(value => !value)}>
        {showPassword ? <EyeOff size={17} /> : <Eye size={17} />}
      </button>
    </div>
  }
  return createPortal(
    <div className="login-modal open" aria-hidden="false">
      <section ref={dialog} onKeyDown={trap} className="login-card" aria-labelledby="loginTitle" role="dialog" aria-modal="true">
        <div className="login-top">
          <span className="login-brand-logo" aria-hidden="true"><ResQperationLogo /></span>
          <button className="modal-close" type="button" aria-label="Close login" onClick={close} disabled={busy}><X size={16} /></button>
        </div>
        <h2 id="loginTitle">{changing ? 'Change password' : 'Sign in to RESQPERATION'}</h2>
        {changing && <p>Enter your account ID and old password, then choose a different password of at least 8 characters.</p>}
        <form className="login-form" onSubmit={submit} autoComplete="on" noValidate>
          <input type="text" name="login" value={form.login} onChange={update} onBlur={() => checkField()} placeholder="Account ID" aria-label="Account ID" autoComplete="username" required disabled={busy} />
          {passwordField('password', changing ? 'Old password' : 'Password')}
          {!changing && <div className="login-remember-row"><label className="login-remember"><input type="checkbox" checked={rememberPassword} disabled={busy} onChange={event => { setRememberPassword(event.target.checked); if (!event.target.checked) void saveRememberedPassword('', '', false) }} /> Remember password</label>{rememberPassword && <button type="button" className="login-saved-password" disabled={busy} onClick={fillSavedPassword}>Fill saved password</button>}</div>}
          {changing && <>{passwordField('newPassword', 'New password', 8)}{passwordField('confirmation', 'Confirm new password', 8)}</>}
          {changing && <div className="login-note login-password-help">All devices will be signed out after the password change. Forgot your old password? Contact HQ/Admin.</div>}
          {notice && <div className="login-note" role="status">{notice}</div>}
          <div className="login-actions">
            <button className="primary-button" type="submit" disabled={busy}>{changing ? <KeyRound size={16} /> : <LogIn size={16} />}{busy ? 'Please wait...' : changing ? 'Update password' : 'Login'}</button>
            <button className="login-mode-toggle" type="button" disabled={busy} onClick={switchMode}>{changing ? 'Back to sign in' : 'Change password'}</button>
          </div>
        </form>
      </section>
    </div>, document.body,
  )
}
