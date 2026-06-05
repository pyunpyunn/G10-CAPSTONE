import api from './client'

export async function getProfile() {
  const response = await api.get('/profile')
  return response.data.data
}

export async function updateProfile(payload) {
  const response = await api.patch('/profile', payload)
  return response.data.data
}

export async function changePassword(payload) {
  const response = await api.patch('/profile/password', payload)
  return response.data
}
