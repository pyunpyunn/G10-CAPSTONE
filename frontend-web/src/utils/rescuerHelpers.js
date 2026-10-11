export function emptyRescuerForm(defaults = {}) {
  return {
    account_id: defaults.account_id || '',
    responder_code: '',
    account_status: defaults.account_status || '',
    first_name: '',
    middle_initial: '',
    last_name: '',
    email: '',
    password: '',
    contact_number: '',
    emergency_contact_name: '',
    emergency_contact_number: '',
    date_of_birth: '',
    gender: '',
    blood_type: defaults.blood_type || '',
    address: '',
    team_id: defaults.team_id || '',
    team_code: defaults.team_code || '',
    team_name: defaults.team_name || '',
    team_type: defaults.team_type || '',
    title: defaults.title || '',
    duty_status: defaults.duty_status || '',
    skills: '',
    training_notes: '',
    certification_reference: '',
    equipment_notes: '',
  }
}

export function formFromRescuer(rescuer) {
  const nameParts = rescuer

  return {
    account_id: rescuer.account_id || '',
    responder_code: rescuer.responder_code || '',
    account_status: rescuer.account_status || '',
    account_status_display: rescuer.account_status_display,
    full_name: rescuer.full_name,
    team_display_name: rescuer.team_name,
    duty_status_display: rescuer.duty_status,
    first_name: nameParts.first_name || '',
    middle_initial: nameParts.middle_initial || '',
    last_name: nameParts.last_name || '',
    email: rescuer.email || '',
    password: '',
    contact_number: rescuer.contact_number || '',
    emergency_contact_name: rescuer.emergency_contact_name || '',
    emergency_contact_number: rescuer.emergency_contact_number || '',
    date_of_birth: rescuer.date_of_birth || '',
    gender: rescuer.gender || '',
    blood_type: rescuer.blood_type || '',
    address: rescuer.address || '',
    team_id: rescuer.team_id || '',
    team_code: rescuer.team_code || '',
    team_name: rescuer.assigned_team_name || '',
    team_type: rescuer.team_type || '',
    title: rescuer.title || '',
    duty_status: rescuer.duty_status?.key || '',
    skills: rescuer.skills || '',
    training_notes: rescuer.training_notes || '',
    certification_reference: rescuer.certification_reference || '',
    equipment_notes: rescuer.equipment_notes || '',
  }
}

export function buildRescuerPayload(form, mode) {
  const payload = {
    responder_code: form.responder_code,
    account_status: form.account_status,
    first_name: form.first_name,
    middle_initial: form.middle_initial,
    last_name: form.last_name,
    email: form.email,
    contact_number: form.contact_number,
    emergency_contact_name: form.emergency_contact_name,
    emergency_contact_number: form.emergency_contact_number,
    date_of_birth: form.date_of_birth,
    gender: form.gender,
    blood_type: form.blood_type,
    address: form.address,
    team_id: form.team_id || null,
    team_name: form.team_name,
    team_code: form.team_code,
    team_type: form.team_type,
    title: form.title,
    duty_status: form.duty_status,
    skills: form.skills,
    training_notes: form.training_notes,
    certification_reference: form.certification_reference,
    equipment_notes: form.equipment_notes,
  }

  if (mode === 'create') {
    payload.account_id = form.account_id
  }

  if (form.password) {
    payload.password = form.password
  }

  return payload
}

export function rescuerErrorMessage(error, fallback = 'Unable to save rescuer account. Please check the form.') {
  if (!error?.response) {
    return 'Cannot connect to the backend right now.'
  }

  const data = error.response.data

  if (data?.errors) {
    const firstError = Object.values(data.errors)[0]
    return Array.isArray(firstError) ? firstError[0] : fallback
  }

  return data?.message || fallback
}

export function accountIdForTeam(accountIdOptions = [], teamName = '', fallback = '') {
  const selected = accountIdOptions.find((item) => item.team_name === teamName)
  return selected?.account_id || fallback
}

export function teamByName(teamOptions = [], teamName = '') {
  return teamOptions.find((team) => team.team_name === teamName) || null
}

export function firstTeam(teamOptions = []) {
  return teamOptions[0] || null
}

export function fullNameFromForm(form) {
  return [form.first_name, formatMiddleInitial(form.middle_initial), form.last_name]
    .filter(Boolean)
    .join(' ')
}

function formatMiddleInitial(value = '') {
  const clean = String(value).trim().replace('.', '').toUpperCase()
  return clean ? `${clean.slice(0, 1)}.` : ''
}

export function exportRosterRows(rescuers) {
  const headers = ['Account ID', 'Responder', 'Team', 'Role', 'Contact', 'Duty Status', 'Skills']
  const rows = rescuers.map((rescuer) => [
    rescuer.account_id,
    rescuer.full_name,
    rescuer.team_name,
    rescuer.title,
    rescuer.contact_number,
    rescuer.duty_status?.label,
    rescuer.skills,
  ])

  return [headers, ...rows]
}
