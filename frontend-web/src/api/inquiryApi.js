import api from './client'

export async function submitLandingInquiry(payload) {
  const response = await api.post('/inquiries', payload)
  return response.data.data
}

export async function getInquiries(params = {}) {
  const response = await api.get('/inquiries', { params })
  return response.data.data
}

export async function updateInquiryStatus(inquiryId, status) {
  const response = await api.patch(`/inquiries/${inquiryId}`, { status })
  return response.data.data
}
