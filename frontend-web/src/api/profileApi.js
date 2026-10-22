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

export async function uploadProfilePhoto(file) {
  const formData = new FormData()
  formData.append('photo', file)
  const response = await api.post('/profile/photo', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return response.data.data
}

export async function removeProfilePhoto() {
  const response = await api.delete('/profile/photo')
  return response.data.data
}
