import api from './client'

export async function loginUser(form) {
  const response = await api.post('/auth/login', {
    login: form.login,
    password: form.password,
    device_name: 'resqperation-web',
  })

  return response.data
}

export async function getRecoveryQuestions(login) {
  const response = await api.get('/auth/password-recovery/questions', { params: { login } })
  return response.data.data
}

export async function resetPassword(payload) {
  const response = await api.post('/auth/password-recovery/reset', payload)
  return response.data
}

export async function saveRecoveryQuestions(payload) {
  const response = await api.put('/auth/security-questions', payload)
  return response.data
}

export async function getCurrentUser() {
  const response = await api.get('/auth/me')
  return response.data.data
}

export async function logoutUser() {
  await api.post('/auth/logout')
}
