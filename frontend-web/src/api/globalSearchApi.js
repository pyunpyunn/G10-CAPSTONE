import api from './client'

export async function searchGlobalRecords(query, types) {
  const response = await api.get('/global-search', {
    params: { q: query, types },
  })

  return response.data.data.results || []
}