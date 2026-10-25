import api from './client'
import { readSessionCache, removeSessionCache, removeSessionCachePrefix, writeSessionCache } from './sessionCache'

const pageCacheTtl = 24 * 60 * 60 * 1000

if (typeof window !== 'undefined') {
  window.addEventListener('personnel-access:changed', () => {
    removeSessionCachePrefix('rescuer-list:')
    removeSessionCache('rescue-team-config')
  })
  window.addEventListener('rescue-criteria:changed', () => removeSessionCache('rescue-team-config'))
}

function shareInFlight(key, request) {
  // Redux transport scopes deduplication to the current account and server.
  return request()
}

export async function getRescuers(params = {}) {
  const paramsKey = getRosterCacheKey(params)
  const response = await shareInFlight(`rescuers:${paramsKey}`, () => api.get('/rescuers', { params }))
  const data = response.data.data
  writeSessionCache(paramsKey, {
    rescuers: {
      ...data.rescuers,
      data: data.rescuers.data.map(({ responder_id, full_name, title, is_active }) => ({
        responder_id,
        full_name,
        title,
        is_active,
      })),
    },
    team_options: data.team_options,
  })
  return data
}

export function getCachedRescuers(params = {}) {
  return readSessionCache(getRosterCacheKey(params), pageCacheTtl)
}

export async function getRescuer(responderId) {
  const response = await shareInFlight(`rescuer:${responderId}`, () => api.get(`/rescuers/${responderId}`))
  return response.data.data
}

export async function createRescuer(payload) {
  const response = await api.post('/rescuers', payload)
  return { ...response.data.data, message: response.data.message }
}

export async function updateRescuer(responderId, payload) {
  const response = await api.patch(`/rescuers/${responderId}`, payload)
  return { ...response.data.data, message: response.data.message }
}

export async function deactivateRescuer(responderId) {
  const response = await api.post(`/rescuers/${responderId}/deactivate`)
  return response.data.data
}

export async function getRescueTeamConfig() {
  const response = await shareInFlight('rescue-team-config', () => api.get('/rescuers/team-config'))
  const data = response.data.data
  writeSessionCache('rescue-team-config', data)
  return data
}

export function getCachedRescueTeamConfig() {
  return readSessionCache('rescue-team-config', pageCacheTtl)
}

export async function createRescueTeam(payload) {
  const response = await api.post('/rescuers/team-config', payload)
  const data = response.data.data
  writeSessionCache('rescue-team-config', data)
  return data
}

export async function updateRescueTeam(teamId, payload) {
  const response = await api.patch(`/rescuers/team-config/${teamId}`, payload)
  const data = response.data.data
  writeSessionCache('rescue-team-config', data)
  return data
}

export async function deleteRescueTeam(teamId) {
  const response = await api.delete(`/rescuers/team-config/${teamId}`)
  const data = response.data.data
  writeSessionCache('rescue-team-config', data)
  return data
}

export async function deleteRescuer(responderId) {
  const response = await api.delete(`/rescuers/${responderId}`)
  return response.data.data
}

export async function getPersonnelRoles() { return (await shareInFlight('personnel-roles', () => api.get('/rescuers/roles'))).data.data }
export async function savePersonnelRole(payload) { return (await (payload.role_id ? api.patch(`/rescuers/roles/${payload.role_id}`,payload) : api.post('/rescuers/roles',payload))).data.data }
export async function deletePersonnelRole(roleId,replacementRoleId) { return (await api.delete(`/rescuers/roles/${roleId}`,{data:{replacement_role_id:replacementRoleId || null}})).data.data }

function getRosterCacheKey(params) {
  const paramsKey = JSON.stringify(Object.entries(params).sort(([left], [right]) => left.localeCompare(right)))
  return `rescuer-list:${paramsKey}`
}

export async function getPersonnelProfile(responderId, signal) {
  return (await api.get(`/personnel-profiles/${encodeURIComponent(responderId)}`, { signal })).data.data.profile
}
