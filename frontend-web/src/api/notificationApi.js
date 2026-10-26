import api from './client'
import { getToken } from './token'
import { createRefreshScheduler } from '../utils/refreshScheduler'

const subscriptions = new Map()

// Share requests, combine socket bursts, and serialize refreshes to prevent stale responses.
export function subscribeNotifications(params, onData, onError = () => {}) {
  const key = JSON.stringify([getToken(), params.status || 'all', params.page || 1])
  let subscription = subscriptions.get(key)
  if (!subscription) {
    subscription = { params, listeners: new Set(), controller: null, pending: false }
    subscription.refresh = async () => {
      if (document.hidden) return
      if (subscription.controller) { subscription.pending = true; return }
      subscription.pending = false
      const controller = new AbortController()
      subscription.controller = controller
      try {
        const data = await getNotifications(subscription.params, { signal: controller.signal })
        if (!controller.signal.aborted) {
          subscription.listeners.forEach((listener) => listener.onData(data))
        }
      } catch (error) {
        if (!controller.signal.aborted) subscription.listeners.forEach((listener) => listener.onError(error))
      } finally {
        subscription.controller = null
        if (subscription.pending && subscription.listeners.size) subscription.scheduler.request()
      }
    }
    subscription.scheduler = createRefreshScheduler(subscription.refresh)
    subscription.changed = (event) => {
      // Mark pending immediately so a pre-change response never restores deleted items.
      if (subscription.controller) subscription.pending = true
      if (event?.detail?.mutation) subscription.controller?.abort()
      subscription.scheduler.request()
    }
    subscription.wake = () => { if (!document.hidden) subscription.changed() }
    window.addEventListener('focus', subscription.wake)
    window.addEventListener('online', subscription.wake)
    document.addEventListener('visibilitychange', subscription.wake)
    window.addEventListener('notifications:changed', subscription.changed)
    subscriptions.set(key, subscription)
  }
  const listener = { onData, onError }
  subscription.listeners.add(listener)
  subscription.scheduler.request()
  return () => {
    subscription.listeners.delete(listener)
    if (subscription.listeners.size) return
    subscription.controller?.abort()
    subscription.scheduler.cancel()
    window.removeEventListener('focus', subscription.wake)
    window.removeEventListener('online', subscription.wake)
    document.removeEventListener('visibilitychange', subscription.wake)
    window.removeEventListener('notifications:changed', subscription.changed)
    subscriptions.delete(key)
  }
}
function notificationViewChanged() {
  window.dispatchEvent(new CustomEvent('notifications:changed', { detail: { mutation: true } }))
}

export async function getNotifications(params = {}, options = {}) {
  const response = await api.get('/notifications', { ...options, params })
  return response.data.data
}

export async function markNotificationsRead(notificationIds = []) {
  const response = await api.post('/notifications/mark-read', {
    notification_ids: notificationIds,
  })
  notificationViewChanged()
  return response.data
}

export async function deleteSelectedNotifications(notificationIds = []) {
  const response = await api.post('/notifications/delete-selected', {
    notification_ids: notificationIds,
  })
  notificationViewChanged()
  return response.data
}

export async function clearNotifications() {
  const response = await api.post('/notifications/clear-all')
  notificationViewChanged()
  return response.data
}
