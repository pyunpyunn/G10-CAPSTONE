export const requestChips = [
  { key: 'all', label: 'All', params: {} },
  { key: 'needs_validation', label: 'Pending', params: { status: 'needs_validation' } },
  { key: 'verified', label: 'Approved', params: { status: 'verified' } },
  { key: 'forwarded', label: 'In Progress', params: { status: 'forwarded' } },
  { key: 'fulfilled', label: 'Completed', params: { status: 'fulfilled' } },
  { key: 'personnel', label: 'Personnel', params: { category: 'personnel' } },
  { key: 'resource', label: 'Resource', params: { category: 'resource' } },
]

export function emptyResourceRequestForm(payload = {}) {
  const firstUrgency = payload.options?.urgencies?.find((item) => item.key === 'medium') || payload.options?.urgencies?.[0]

  return {
    request_source: 'hq_desk',
    source_reference: payload.active_event?.event_id || '',
    request_category: 'resource',
    resource_type: '',
    item_name: '',
    quantity: 1,
    unit: '',
    requested_by: '',
    evacuation_center_id: '',
    urgency_id: firstUrgency?.urgency_id || '',
    description: '',
    validation_status: 'needs_validation',
    validation_notes: '',
    missing_information: '',
    duplicate_request_id: '',
    tracking_reference: '',
  }
}

export function formFromResourceRequest(request) {
  return {
    request_source: request.request_source?.key || 'shared_db',
    source_reference: request.source_reference || '',
    request_category: request.request_category?.key || 'resource',
    resource_type: request.need?.type || '',
    item_name: request.need?.item_name || '',
    quantity: request.need?.quantity || 1,
    unit: request.need?.unit || '',
    requested_by: request.requested_by || '',
    evacuation_center_id: request.area?.evacuation_center_id || '',
    urgency_id: '',
    description: request.description || '',
    validation_status: request.validation?.key || 'needs_validation',
    validation_notes: request.validation_notes || '',
    missing_information: '',
    duplicate_request_id: '',
    tracking_reference: request.tracking_reference || '',
  }
}

export function buildCreatePayload(form) {
  return {
    request_source: form.request_source,
    source_reference: emptyToNull(form.source_reference),
    request_category: form.request_category,
    resource_type: form.resource_type,
    item_name: emptyToNull(form.item_name),
    quantity: Number(form.quantity) || 1,
    unit: emptyToNull(form.unit),
    requested_by: form.requested_by,
    evacuation_center_id: emptyToNull(form.evacuation_center_id),
    urgency_id: form.urgency_id ? Number(form.urgency_id) : null,
    description: emptyToNull(form.description),
  }
}

export function buildValidationPayload(form) {
  return {
    validation_status: form.validation_status,
    validation_notes: emptyToNull(form.validation_notes),
    missing_information: emptyToNull(form.missing_information),
    duplicate_request_id: emptyToNull(form.duplicate_request_id),
  }
}

export function buildForwardPayload(form) {
  return {
    validation_notes: emptyToNull(form.validation_notes),
    tracking_reference: emptyToNull(form.tracking_reference),
  }
}

export function buildReturnPayload(form) {
  return {
    validation_notes: form.validation_notes || 'Returned for missing information or duplicate check.',
    missing_information: emptyToNull(form.missing_information),
    duplicate_request_id: emptyToNull(form.duplicate_request_id),
  }
}

export function filterParams(search, purok, activeChip, page = 1, extra = {}) {
  const chip = requestChips.find((item) => item.key === activeChip)

  return {
    search: search.trim(),
    purok,
    page,
    per_page: 6,
    ...(chip?.params || {}),
    ...extra,
  }
}

export function defaultResourceSummaryRows(summary = {}) {
  return [
    {
      key: 'needs_validation',
      label: 'Needs validation',
      status_id: summary.status_ids?.needs_validation ?? null,
      count: summary.needs_validation || 0,
    },
    {
      key: 'verified',
      label: 'Verified',
      status_id: summary.status_ids?.verified ?? null,
      count: summary.verified || 0,
    },
    {
      key: 'forwarded',
      label: 'Forwarded today',
      status_id: summary.status_ids?.forwarded ?? null,
      count: summary.forwarded_today || 0,
    },
    {
      key: 'returned',
      label: 'Returned',
      status_id: summary.status_ids?.returned ?? null,
      count: summary.returned || 0,
    },
  ]
}

export function resourceRequestErrorMessage(error, fallback = 'Unable to save the request. Please check the form and try again.') {
  const data = error?.response?.data

  if (data?.errors) {
    const firstError = Object.values(data.errors)[0]
    return Array.isArray(firstError) ? firstError[0] : 'Please check the request form.'
  }

  return data?.message || fallback
}

export function displayText(value, fallback = '-') {
  return value || fallback
}

function emptyToNull(value) {
  const text = String(value ?? '').trim()
  return text === '' ? null : text
}
