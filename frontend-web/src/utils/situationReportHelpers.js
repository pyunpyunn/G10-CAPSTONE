export function emptyGenerateForm(summary) {
  return {
    report_number: summary?.report?.report_number || '',
    period_start: toDateTimeInput(summary?.report?.period_start),
    period_end: toDateTimeInput(summary?.report?.period_end),
    prepared_by: summary?.report?.prepared_by || '',
    reviewed_by: summary?.report?.reviewed_by || '',
    actions_text: summary?.actions_text || '',
    included_sections: summary?.included_sections || [],
  }
}

export function buildGeneratePayload(eventId, form) {
  return {
    event_id: eventId,
    report_number: emptyToNull(form.report_number),
    period_start: emptyToNull(form.period_start),
    period_end: emptyToNull(form.period_end),
    prepared_by: emptyToNull(form.prepared_by),
    reviewed_by: emptyToNull(form.reviewed_by),
    actions_text: emptyToNull(form.actions_text),
    included_sections: form.included_sections || [],
    report_status: 'generated',
  }
}

export function situationErrorMessage(error, fallback = 'Unable to save the SitRep. Please check the form and try again.') {
  const data = error?.response?.data

  if (data?.errors) {
    const firstError = Object.values(data.errors)[0]
    return Array.isArray(firstError) ? firstError[0] : 'Please check the SitRep form.'
  }

  return data?.message || error?.message || fallback
}

export function percentLabel(value) {
  return value == null ? '-' : `${value}%`
}

export function displayValue(value, fallback = '-') {
  return value === null || value === undefined || value === '' ? fallback : value
}

function emptyToNull(value) {
  const text = String(value ?? '').trim()
  return text === '' ? null : text
}

function toDateTimeInput(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
  return local.toISOString().slice(0, 16)
}
