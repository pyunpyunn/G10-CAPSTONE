import api from './client'

export async function getProfile() {
  const response = await api.get('/profile')
  return response.data.data
}

export async function updateProfile(payload) {
  const response = await api.patch('/profile', payload)
  return response.data.data
}

export async function uploadProfilePhoto(file) {
  const formData = new FormData()
  formData.append('photo', file)
  const response = await api.post('/profile/photo', formData)
  return response.data.data
}

export async function removeProfilePhoto() {
  const response = await api.delete('/profile/photo')
  return response.data.data
}

export function profilePhotoUrl(path) {
  if (!path) return ''

  const baseUrl = api.defaults.baseURL || '/api/v1'
  const origin = new URL(baseUrl, window.location.origin).origin
  const safePath = String(path).split('/').map(encodeURIComponent).join('/')
  return `${origin}/storage/${safePath}`
}

export async function changePassword(payload) {
  const response = await api.patch('/profile/password', payload)
  return response.data
}
