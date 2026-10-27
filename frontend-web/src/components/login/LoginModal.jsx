import { useEffect, useRef, useState } from 'react'
import { Eye, EyeOff, LogIn, X } from 'lucide-react'
import { createPortal } from 'react-dom'
import {
  getRememberedLogin,
  getRememberedPassword,
  lookupHouseholdRecoveryContact,
  resetHouseholdPassword,
  saveRememberedPassword,
  sendHouseholdRecoveryCode,
  showAuthError,
  validateHouseholdRecoveryContact,
} from '../../api/authApi'
import ResQperationLogo from '../layout/ResQperationLogo'

const RECOVERY_RESEND_COOLDOWN_SECONDS = 5 * 60

export default function LoginModal({ onClose, onLogin }) {
  const [recovering, setRecovering] = useState(false)
  const [rememberPassword, setRememberPassword] = useState(() => Boolean(getRememberedLogin()))
  const [form, setForm] = useState({ login: getRememberedLogin(), password: '' })
  const [contactNumber, setContactNumber] = useState('')
  const [maskedContact, setMaskedContact] = useState('')
  const [validatedContact, setValidatedContact] = useState('')
  const [code, setCode] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [busy, setBusy] = useState(false)
  const [recoveryError, setRecoveryError] = useState('')
  const [recoveryNotice, setRecoveryNotice] = useState('')
  const [codeSent, setCodeSent] = useState(false)
  const [sendCooldownSeconds, setSendCooldownSeconds] = useState(0)
  const [showPassword, setShowPassword] = useState(false)
  const dialog = useRef(null)
  const currentForm = useRef(form)
  const checkVersion = useRef(0)
  const sendInFlight = useRef(false)

  useEffect(() => {
    if (sendCooldownSeconds <= 0) return

    const timer = setTimeout(() => {
      setSendCooldownSeconds(seconds => Math.max(0, seconds - 1))
    }, 1000)
    return () => clearTimeout(timer)
  }, [sendCooldownSeconds])

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
    if (!saved) {
      showAuthError('No saved password was returned. Choose the saved account from your browser password manager, or enter your password and accept the save prompt after login.')
      return
    }
    currentForm.current = { ...currentForm.current, ...saved }
    setForm(currentForm.current)
  }

  function close() {
    if (!busy) {
      checkVersion.current += 1
      onClose()
    }
  }

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
    if (recovering && event.target.name === 'login') {
      setMaskedContact('')
      setValidatedContact('')
      setCodeSent(false)
      setRecoveryError('')
      setRecoveryNotice('')
    }
  }

  function errorMessage(failure) {
    const data = failure?.response?.data
    return Object.values(data?.errors || {}).flat()[0] || data?.message || failure.message || 'Unable to continue. Please try again.'
  }

  async function lookupAccount() {
    const login = currentForm.current.login.trim()
    if (!login) {
      setMaskedContact('')
      return
    }
    setBusy(true)
    setRecoveryError('')
    setRecoveryNotice('')
    setMaskedContact('')
    setValidatedContact('')
    setCodeSent(false)
    try {
      const response = await lookupHouseholdRecoveryContact(login)
      setMaskedContact(response.masked_contact_number)
    } catch (failure) {
      setRecoveryError(errorMessage(failure))
    } finally {
      setBusy(false)
    }
  }

  function updateContact(event) {
    setContactNumber(event.target.value)
    setValidatedContact('')
    setCodeSent(false)
    setRecoveryError('')
    setRecoveryNotice('')
  }

  async function validateContact() {
    const login = currentForm.current.login.trim()
    const digits = contactNumber.replace(/\D/g, '')
    if (!login) {
      setRecoveryError('Enter your account ID first.')
      return
    }
    if (!((digits.length === 11 && digits.startsWith('09'))
      || (digits.length === 10 && digits.startsWith('9'))
      || (digits.length === 12 && digits.startsWith('63')))) {
      setValidatedContact('')
      setRecoveryError('Enter a valid Philippine mobile number.')
      return
    }

    setBusy(true)
    setRecoveryError('')
    setRecoveryNotice('')
    setValidatedContact('')
    setCodeSent(false)
    try {
      await validateHouseholdRecoveryContact(login, contactNumber)
      setValidatedContact(contactNumber.trim())
    } catch (failure) {
      setRecoveryError(errorMessage(failure))
    } finally {
      setBusy(false)
    }
  }

  async function requestCode() {
    if (sendInFlight.current || sendCooldownSeconds > 0) return
    if (!validatedContact || validatedContact !== contactNumber.trim()) {
      setRecoveryError('Verify your registered phone number before requesting a code.')
      return
    }
    sendInFlight.current = true
    setSendCooldownSeconds(RECOVERY_RESEND_COOLDOWN_SECONDS)
    setBusy(true)
    setRecoveryError('')
    setRecoveryNotice('')
    try {
      const response = await sendHouseholdRecoveryCode(currentForm.current.login.trim(), validatedContact)
      if (response.cooldown) {
        setSendCooldownSeconds(Math.max(
          RECOVERY_RESEND_COOLDOWN_SECONDS,
          Number(response.cooldown_seconds) || 0
        ))
        setRecoveryNotice('Please wait before requesting another verification code.')
        return
      }
      setCodeSent(true)
      setSendCooldownSeconds(RECOVERY_RESEND_COOLDOWN_SECONDS)
      const deliveryMessage = response.delivery_channel === 'onesignal_sms'
        ? 'OneSignal accepted your SMS code request.'
        : 'The SMS fallback provider accepted your code request.'
      setRecoveryNotice(`${deliveryMessage} Delivery can take a few minutes. Check your SMS inbox and wait before requesting another code; a new request replaces this code.`)
    } catch (failure) {
      if (failure?.response?.status === 429) {
        setSendCooldownSeconds(RECOVERY_RESEND_COOLDOWN_SECONDS)
        setRecoveryNotice('Please wait before requesting another verification code.')
      } else {
        setSendCooldownSeconds(RECOVERY_RESEND_COOLDOWN_SECONDS)
        setRecoveryError(errorMessage(failure))
      }
    } finally {
      sendInFlight.current = false
      setBusy(false)
    }
  }

  function showPasswordChanged(householdId) {
    const previousFocus = document.activeElement
    const popup = document.createElement('dialog')
    popup.className = 'auth-error-popup'
    popup.setAttribute('role', 'alertdialog')
    popup.setAttribute('aria-labelledby', 'password-changed-title')
    const title = document.createElement('h2')
    title.id = 'password-changed-title'
    title.textContent = 'Password changed'
    const detail = document.createElement('p')
    detail.className = 'auth-error-message'
    detail.textContent = `Password for ${householdId} is changed!`
    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'primary-button'
    button.textContent = 'Close'
    function dismiss() {
      popup.close()
      popup.remove()
      if (previousFocus?.isConnected) previousFocus.focus()
    }
    button.addEventListener('click', dismiss)
    popup.addEventListener('cancel', event => { event.preventDefault(); dismiss() })
    popup.append(title, detail, button)
    document.body.append(popup)
    popup.showModal()
    button.focus()
  }

  async function submit(event) {
    event.preventDefault()
    checkVersion.current += 1
    setBusy(true)
    setRecoveryError('')
    try {
      if (recovering) {
        if (!/^\d{6}$/.test(code)) throw new Error('Enter the 6-digit code from your notification or SMS.')
        if (newPassword.length < 8 || newPassword.length > 128) throw new Error('Your new password must contain 8 to 128 characters.')
        if (newPassword !== confirmation) throw new Error('The new passwords do not match.')
        const response = await resetHouseholdPassword({
          login: currentForm.current.login.trim(),
          contact_number: validatedContact,
          code,
          password: newPassword,
          password_confirmation: confirmation,
        })
        setRecovering(false)
        setRecoveryNotice('')
        showPasswordChanged(response.account_identifier || response.household_id)
        return
      }

      if (!form.login.trim()) throw new Error('Enter your account ID.')
      if (!form.password) throw new Error('Enter your password.')
      await onLogin({ login: form.login.trim(), password: form.password, rememberPassword })
    } catch (failure) {
      if (recovering) setRecoveryError(errorMessage(failure))
      else showAuthError(errorMessage(failure))
    } finally {
      setBusy(false)
    }
  }

  function switchToRecovery() {
    checkVersion.current += 1
    setRecovering(true)
    setRecoveryError('')
    setRecoveryNotice('')
    setMaskedContact('')
    setContactNumber('')
    setValidatedContact('')
    setCode('')
    setNewPassword('')
    setConfirmation('')
    setCodeSent(false)
    if (currentForm.current.login.trim()) void lookupAccount()
  }

  function recoveryCooldownLabel() {
    const minutes = Math.floor(sendCooldownSeconds / 60)
    const seconds = String(sendCooldownSeconds % 60).padStart(2, '0')
    return `${minutes}:${seconds}`
  }

  function passwordField() {
    return <div className="login-password-field">
      <input name="password" value={form.password} onChange={update} type={showPassword ? 'text' : 'password'} placeholder="Password" aria-label="Password" required maxLength={255} disabled={busy} autoComplete="current-password" />
      <button className="login-password-toggle" type="button" aria-label={showPassword ? 'Hide password' : 'Show password'} title={showPassword ? 'Hide password' : 'Show password'} onClick={() => setShowPassword(value => !value)}>
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
        <h2 id="loginTitle">{recovering ? 'Forgot password' : 'Sign in to RESQPERATION'}</h2>
        {recovering && <p>Verify the phone number registered to your system account to receive a password reset code.</p>}
        <form className="login-form" onSubmit={submit} autoComplete="on" noValidate>
          <input
            type="text"
            name="login"
            value={form.login}
            onChange={update}
            onBlur={recovering ? () => { void lookupAccount() } : undefined}
            placeholder="Account ID"
            aria-label="Account ID"
            autoComplete="username"
            required
            disabled={busy}
          />
          {!recovering && <>
            {passwordField()}
            <div className="login-remember-row">
              <label className="login-remember"><input type="checkbox" checked={rememberPassword} disabled={busy} onChange={event => { setRememberPassword(event.target.checked); if (!event.target.checked) void saveRememberedPassword('', '', false) }} /> Remember password</label>
              {rememberPassword && <button type="button" className="login-saved-password" disabled={busy} onClick={fillSavedPassword}>Fill saved password</button>}
            </div>
          </>}
          {recovering && <>
            <label className="login-recovery-hint" htmlFor="recovery-contact">
              {maskedContact ? `Enter your phone number ${maskedContact}` : 'Enter your account ID to find your registered phone number.'}
            </label>
            <input
              id="recovery-contact"
              type="tel"
              value={contactNumber}
              onChange={updateContact}
              onBlur={() => { if (contactNumber.trim()) void validateContact() }}
              placeholder="09XX XXX XXXX"
              aria-label="Registered phone number"
              autoComplete="tel"
              disabled={!maskedContact || busy}
              required
            />
            {validatedContact && !codeSent && <button className="login-recovery-button" type="button" onClick={() => { void requestCode() }} disabled={busy || sendCooldownSeconds > 0}>
              {busy ? 'Please wait...' : sendCooldownSeconds > 0 ? `Wait ${recoveryCooldownLabel()}` : 'Send recovery code'}
            </button>}
            {codeSent && <>
              <button className="login-recovery-resend" type="button" onClick={() => { void requestCode() }} disabled={busy || sendCooldownSeconds > 0}>
                {sendCooldownSeconds > 0 ? `Resend in ${recoveryCooldownLabel()}` : 'Get code'}
              </button>
              <input
                type="text"
                inputMode="numeric"
                autoComplete="one-time-code"
                value={code}
                onChange={event => setCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
                placeholder="6-digit verification code"
                aria-label="Verification code"
                maxLength={6}
                disabled={busy}
                required
              />
              <input
                type="password"
                autoComplete="new-password"
                value={newPassword}
                onChange={event => setNewPassword(event.target.value)}
                placeholder="New password"
                aria-label="New password"
                minLength={8}
                maxLength={128}
                disabled={busy}
                required
              />
              <input
                type="password"
                autoComplete="new-password"
                value={confirmation}
                onChange={event => setConfirmation(event.target.value)}
                placeholder="Confirm new password"
                aria-label="Confirm new password"
                minLength={8}
                maxLength={128}
                disabled={busy}
                required
              />
              <div className="login-actions">
                <button className="primary-button" type="submit" disabled={busy}>{busy ? 'Please wait...' : 'Change password'}</button>
              </div>
            </>}
            {recoveryError && <div className="login-note login-recovery-error" role="alert">{recoveryError}</div>}
            {recoveryNotice && <div className="login-note" role="status">{recoveryNotice}</div>}
          </>}
          {!recovering && <div className="login-actions">
            <button className="primary-button" type="submit" disabled={busy}>{busy ? 'Please wait...' : <><LogIn size={16} /> Login</>}</button>
            <button className="login-mode-toggle" type="button" disabled={busy} onClick={switchToRecovery}>Forgot password?</button>
          </div>}
        </form>
      </section>
    </div>, document.body,
  )
}
