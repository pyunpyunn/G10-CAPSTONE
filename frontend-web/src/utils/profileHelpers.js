export function profileFormFromIdentity(identity = {}) {
  return {
    first_name: identity.first_name || '',
    last_name: identity.last_name || '',
    email: identity.email === 'No email recorded' ? '' : identity.email || '',
    contact_number: identity.contact_number === 'No mobile recorded' ? '' : identity.contact_number || '',
    assigned_center_id: identity.assigned_station === 'Command desk' ? '' : identity.assigned_station || '',
  }
}

export function passwordForm() {
  return {
    current_password: '',
    password: '',
    password_confirmation: '',
  }
}

export function profileErrorMessage(error, fallback = 'Unable to save profile changes.') {
  const data = error?.response?.data

  if (data?.errors) {
    const firstError = Object.values(data.errors)[0]
    return Array.isArray(firstError) ? firstError[0] : fallback
  }

  return data?.message || fallback
}
