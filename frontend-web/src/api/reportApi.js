import api from './client'

export async function downloadBackendReport(payload) {
  let { data } = await api.post('/reports/generate', payload, {timeout:payload.report_type === 'situation' ? 240000 : api.defaults.timeout})
  const statusUrl = data.status_url
  const deadline = Date.now() + 10 * 60 * 1000
  while (data.status === 'pending') {
    if (Date.now() > deadline) throw new Error('The report is still processing. Please try again later.')
    await new Promise((resolve) => setTimeout(resolve, 1500))
    const response = await api.get(statusUrl, { cache: false })
    data = response.data
  }
  if (data.status !== 'success') throw new Error(data.message || 'Report generation failed.')
  const link = document.createElement('a')
  link.href = data.download_url
  link.download = data.file_name
  link.rel = 'noopener'
  document.body.appendChild(link)
  link.click()
  link.remove()
  return data
}
