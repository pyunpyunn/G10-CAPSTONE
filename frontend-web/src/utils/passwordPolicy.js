export const PASSWORD_REQUIREMENTS = [
  {
    id: 'length',
    label: 'At least 8 characters',
    test: (password) => (password || '').length >= 8,
  },
  {
    id: 'uppercase',
    label: 'At least 1 uppercase letter (A-Z)',
    test: (password) => /[A-Z]/.test(password || ''),
  },
  {
    id: 'number',
    label: 'At least 1 number (0-9)',
    test: (password) => /[0-9]/.test(password || ''),
  },
  {
    id: 'symbol',
    label: 'At least 1 special character / symbol (!@#$%...)',
    test: (password) => /[^A-Za-z0-9\s]/.test(password || ''),
  },
]

export function checkPasswordRequirements(password) {
  return PASSWORD_REQUIREMENTS.map((req) => ({
    id: req.id,
    label: req.label,
    met: req.test(password),
  }))
}

export function isPasswordValid(password) {
  const pwd = password || ''
  if (pwd.length < 8 || pwd.length > 128) return false
  return PASSWORD_REQUIREMENTS.every((req) => req.test(pwd))
}

export function passwordPolicyError(password) {
  const pwd = password || ''
  if (pwd.length > 128) return 'Password is too long.'
  if (pwd.length < 8 || !/[A-Z]/.test(pwd) || !/[0-9]/.test(pwd) || !/[^A-Za-z0-9\s]/.test(pwd)) {
    return 'Password must be at least 8 characters and contain an uppercase letter, a number, and a symbol.'
  }

  return null
}
