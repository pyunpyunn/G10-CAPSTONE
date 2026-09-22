import {
  downloadStructuredExcel,
  downloadSitrepPdf,
} from './exportFileHelpers'

export function emptyGenerateForm(summary) {
  return {
    report_number: summary?.report?.report_number || '',
    period_start: toDateTimeInput(new Date()),
    period_end: toDateTimeInput(new Date()),
    prepared_by: summary?.report?.prepared_by || 'HQ/Admin Desk',
    reviewed_by: summary?.report?.reviewed_by || 'Incident Commander',
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

  return data?.message || fallback
}

export function downloadSituationExcel(summary, includedSections = [], actionsText = '') {
  if (!summary) {
    return
  }

  downloadStructuredExcel(
    `${summary.report.report_number || 'sitrep-draft'}.xls`,
    situationExcelSections(summary, includedSections, actionsText),
  )
}

export async function downloadSituationPdf(summary, includedSections = [], actionsText = '') {
  if (!summary) {
    return
  }

  await downloadSitrepPdf(
    `${summary.report.report_number || 'sitrep-draft'}.pdf`,
    summary,
    includedSections,
    actionsText,
  )
}

function situationExcelSections(summary, includedSections, actionsText) {
  return [
    {
      number: 'REPORT',
      title: 'Report Details',
      rows: [
        ['Disaster name', summary.event.name],
        ['Disaster type', summary.event.type],
        ['Date declared', summary.event.declared_at],
        ['Date finished', summary.event.finished_at],
      ],
    },
    ...includedSections.map((section) => ({
      number: section,
      title: sectionTitle(section),
      rows: sectionRows(section, summary, actionsText),
    })),
  ]
}

function sectionTitle(section) {
  return {
    I: 'Situation Overview',
    II: 'Affected Population',
    III: 'Casualties and Immediate Needs',
    IV: 'Evacuation Centers',
    V: 'Rescue Operations Timeline',
    VI: 'Initial Damage Assessment',
    VII: 'Resources Deployed and Requests',
    VIII: 'Actions Taken and Recommendations',
  }[section] || 'SitRep Section'
}

export function percentLabel(value) {
  return `${value || 0}%`
}

export function displayValue(value, fallback = '-') {
  return value || fallback
}

function sectionRows(section, summary, actionsText) {
  const household = summary.household || {}
  const casualties = summary.casualties || {}

  switch (section) {
    case 'I':
      return [
        ['[I] Condition', summary.weather?.condition],
        ['[I] Wind', summary.weather?.wind],
        ['[I] Rainfall', summary.weather?.rainfall],
        ['[I] Temperature', summary.weather?.temperature],
        ['[I] Source', summary.weather?.source],
      ]
    case 'II':
      return [
        ['[II] Total households affected', household.total],
        ['[II] Safe total', household.safe_total],
        ['[II] Evacuated', household.evacuated],
        ['[II] Unsafe / at risk', household.unsafe],
        ['[II] Unchecked', household.unchecked],
      ]
    case 'III':
      return [
        ['[III] Deaths', casualties.deaths],
        ['[III] Missing', casualties.missing],
        ['[III] Injured', casualties.injured],
        ['[III] Rescued', casualties.rescued],
      ]
    case 'IV':
      return (summary.evacuation || []).flatMap((row) => [
        [`[IV] ${row.name} type`, row.type],
        [`[IV] ${row.name} status`, row.status],
        [`[IV] ${row.name} capacity`, row.capacity_status],
      ])
    case 'V':
      return [
        ...(summary.dispatch?.timeline || []).map((row) => [`[V] ${row.title || 'Timeline'} - ${row.actor || 'Activity'}`, row.detail || row.status]),
        ...(summary.dispatch?.rows || []).map((row) => [`[V] ${row.team || 'Team'} - ${row.area || 'Area'}`, row.outcomes]),
      ]
    case 'VI':
      return [
        ['[VI] Partially damaged houses', summary.damage?.partial],
        ['[VI] Totally damaged houses', summary.damage?.total],
      ]
    case 'VII':
      return (summary.resources?.rows || []).map((row) => [
        `[VII] ${row.item || 'Resource request'}`,
        `${row.quantity || ''} - ${row.source || ''} - ${row.status || ''}`,
      ])
    case 'VIII':
      return [['[VIII] Actions taken and recommendations', actionsText]]
    default:
      return []
  }
}

function emptyToNull(value) {
  const text = String(value ?? '').trim()
  return text === '' ? null : text
}

function toDateTimeInput(date) {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
  return local.toISOString().slice(0, 16)
}
