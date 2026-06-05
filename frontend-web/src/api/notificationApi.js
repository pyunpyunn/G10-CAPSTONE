import api from './client'

export async function getNotifications(params = {}) {
  const response = await api.get('/notifications', { params })
  return response.data.data
}

export async function markNotificationsRead(notificationIds = []) {
  const response = await api.post('/notifications/mark-read', {
    notification_ids: notificationIds,
  })

  return response.data
}

export async function deleteSelectedNotifications(notificationIds = []) {
  const response = await api.post('/notifications/delete-selected', {
    notification_ids: notificationIds,
  })

  return response.data
}

export async function clearNotifications() {
  const response = await api.post('/notifications/clear-all')
  return response.data
}
