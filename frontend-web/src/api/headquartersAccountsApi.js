import api from './client'

export async function getHeadquartersAccounts() {
  const response = await api.get('/headquarters-accounts')
  return response.data.data
}

export async function saveHeadquartersAccount(payload) {
  const response = await api.post('/headquarters-accounts', payload)
  return response.data
}
