import { reportSections, operatorInputFields } from './situationSections'

export function emptyGenerateForm(summary) {
  return {
    ...Object.fromEntries(operatorInputFields.map(([key]) => [key, summary?.operator_inputs?.[key] || ''])),
    supersedes_sit_rep_id: '',
    sitrep_type: summary?.report?.report_type || 'progress',
    approved_by: summary?.report?.approved_by || '',
    report_number: summary?.report?.report_number || '',
    period_start: toDateTimeInput(summary?.report?.period_start),
    period_end: toDateTimeInput(summary?.report?.period_end),
    prepared_by: summary?.report?.prepared_by || '',
    reviewed_by: summary?.report?.reviewed_by || '',
    actions_text: summary?.actions_text || '',
    included_sections: (summary?.included_sections || []).filter(key => reportSections.some(([number]) => number === key)),
  }
}

export function buildGeneratePayload(eventId, form) {
  return {
    ...Object.fromEntries(operatorInputFields.map(([key]) => [key, emptyToNull(form[key])])),
    supersedes_sit_rep_id: form.supersedes_sit_rep_id || null,
    sitrep_type: form.sitrep_type,
    approved_by: emptyToNull(form.approved_by),
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

export function situationReportTitle(summary, form = {}) {
  const period = form.period_end || summary?.report?.period_end || 'Not recorded'
  return `BDRRMC Situation Report No. ${form.report_number || summary?.report?.report_number || 'Not recorded'} on the ${summary?.event?.name || 'Not recorded'} as of ${period}`
}
