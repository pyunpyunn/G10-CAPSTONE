export function personnelDestination(id) {
  return { to: '/rescuers', state: { selectedProfileId: String(id) } }
}

export function searchResultDestination(result) {
  if (result.type === 'responders') {
    const id = result.responder_id || new URLSearchParams(String(result.href || '').split('?')[1] || '').get('id')
    if (id != null && id !== '') return personnelDestination(id)
  }
  return { to: result.href }
}
