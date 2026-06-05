export function emptyGenerateForm(summary) {
  return {
    report_number: summary?.report?.report_number || '',
    period_start: toDateTimeInput(new Date()),
    period_end: toDateTimeInput(new Date()),
    prepared_by: summary?.report?.prepared_by || 'HQ/Admin Desk',
    reviewed_by: summary?.report?.reviewed_by || 'Incident Commander',
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
    report_status: 'generated',
  }
}

export function situationErrorMessage(error, fallback = 'Unable to save the SitRep. Please check the form and try again.') {
  const data = error?.response?.data

  if (data?.errors) {
    const firstError = Object.values(data.errors)[0]
    return Array.isArray(firstError) ? firstError[0] : 'Please check the SitRep form.'
  }

  return data?.message || fallback
}

export function downloadSituationCsv(summary) {
  if (!summary) {
    return
  }

  const rows = [
    ['Section', 'Field', 'Value'],
    ['Event', 'Name', summary.event.name],
    ['Event', 'Type', summary.event.type],
    ['Event', 'Declared', summary.event.declared_at],
    ['Event', 'Finished', summary.event.finished_at],
    ['Households', 'Total', summary.household.total],
    ['Households', 'Reported', summary.household.reported],
    ['Households', 'Safe total', summary.household.safe_total],
    ['Households', 'Evacuated', summary.household.evacuated],
    ['Households', 'Unsafe', summary.household.unsafe],
    ['Households', 'Unchecked', summary.household.unchecked],
    ['Casualties', 'Deaths', summary.casualties.deaths],
    ['Casualties', 'Missing', summary.casualties.missing],
    ['Casualties', 'Injured', summary.casualties.injured],
    ['Resources', 'Needs validation', summary.resources.needs_validation],
    ['Resources', 'Forwarded', summary.resources.forwarded],
  ]

  const csv = rows.map((row) => row.map(csvCell).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = `${summary.report.report_number || 'sitrep-draft'}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

export function percentLabel(value) {
  return `${value || 0}%`
}

export function displayValue(value, fallback = '-') {
  return value || fallback
}

function csvCell(value) {
  return `"${String(value ?? '').replaceAll('"', '""')}"`
}

function emptyToNull(value) {
  const text = String(value ?? '').trim()
  return text === '' ? null : text
}

function toDateTimeInput(date) {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
  return local.toISOString().slice(0, 16)
}
