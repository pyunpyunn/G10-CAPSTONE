import api from './client'

export async function loginUser(form) {
  const response = await api.post('/auth/login', {
    login: form.login,
    password: form.password,
    device_name: 'resqperation-web',
  })

  return response.data
}

export async function getCurrentUser() {
  const response = await api.get('/auth/me')
  return response.data.data
}

export async function logoutUser() {
  await api.post('/auth/logout')
}
