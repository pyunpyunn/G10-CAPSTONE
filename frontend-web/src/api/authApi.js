import api from './client'

export async function loginUser(form) {
  const response = await api.post('/auth/login', {
    login: form.login,
    password: form.password,
    device_name: 'resqperation-web',
  })

  return response.data
}

export async function getCurrentUser() {
  const response = await api.get('/auth/me')
  return response.data.data
}

export async function logoutUser() {
  await api.post('/auth/logout')
}

export async function changePasswordWithOldPassword(payload) {
  return (await api.post('/auth/password-recovery/reset', payload)).data
}

export async function verifyPasswordChange(login, currentPassword) {
  const payload = { login: login.trim() }
  if (currentPassword !== undefined) payload.current_password = currentPassword
  return (await api.post('/auth/password-recovery/verify', payload)).data
}

// Errors stay in a separate popup; textContent keeps server messages as plain text.
export function showAuthError(message) {
  const existing = document.getElementById('auth-error-popup')
  if (existing) {
    existing.querySelector('.auth-error-message').textContent = message
    return
  }

  const previousFocus = document.activeElement
  const popup = document.createElement('dialog')
  popup.id = 'auth-error-popup'
  popup.className = 'auth-error-popup'
  popup.setAttribute('role', 'alertdialog')
  popup.setAttribute('aria-labelledby', 'auth-error-title')
  popup.setAttribute('aria-describedby', 'auth-error-message')

  const icon = document.createElement('span')
  icon.className = 'auth-error-icon'
  icon.setAttribute('aria-hidden', 'true')
  icon.textContent = '!'

  const title = document.createElement('h2')
  title.id = 'auth-error-title'
  title.textContent = 'Unable to continue'

  const detail = document.createElement('p')
  detail.id = 'auth-error-message'
  detail.className = 'auth-error-message'
  detail.textContent = message

  const button = document.createElement('button')
  button.type = 'button'
  button.className = 'primary-button'
  button.textContent = 'OK, got it'
  button.autofocus = true

  function dismiss() {
    popup.close()
    popup.remove()
    if (previousFocus?.isConnected && !previousFocus.disabled) previousFocus.focus()
  }
  button.addEventListener('click', dismiss)
  popup.addEventListener('cancel', event => { event.preventDefault(); dismiss() })
  popup.addEventListener('keydown', event => {
    if (event.key === 'Tab') { event.preventDefault(); button.focus() }
  })
  popup.append(icon, title, detail, button)
  document.body.append(popup)
  popup.showModal()
  button.focus()
}

const rememberedLoginKey = 'resqperation_remembered_login'

export function getRememberedLogin() {
  try { return localStorage.getItem(rememberedLoginKey) || '' } catch { return '' }
}

export async function saveRememberedPassword(login, password, remember) {
  // Persist only the account ID here. The browser owns the password vault.
  try {
    if (!remember) {
      localStorage.removeItem(rememberedLoginKey)
      await navigator.credentials?.preventSilentAccess?.()
      return
    }
    localStorage.setItem(rememberedLoginKey, login.trim())
    if (window.isSecureContext && window.PasswordCredential && navigator.credentials?.store) {
      await navigator.credentials.store(new window.PasswordCredential({ id: login.trim(), password }))
    }
  } catch {
    // Browser privacy settings may refuse storage; standard password autofill remains available.
  }
}

export async function getRememberedPassword(signal, mediation = 'silent') {
  const login = getRememberedLogin()
  if (!login || !window.isSecureContext || !window.PasswordCredential || !navigator.credentials?.get) return null
  try {
    const credential = await navigator.credentials.get({ password: true, mediation, signal })
    return credential?.type === 'password' && credential.id === login
      ? { login: credential.id, password: credential.password } : null
  } catch { return null }
}
