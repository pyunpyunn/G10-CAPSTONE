import api from './client'
import { getToken } from './token'
import { createPushRegistrationGate } from '../utils/pushRegistrationGate'

export const WEB_NOTICE_TYPES = ['Broadcast', 'Dispatch status', 'Dispatch log', 'Approval request', 'PAGASA advisory sync', 'PHIVOLCS advisory sync']

export function browserPushState() {
  if (!window.isSecureContext) return 'insecure'
  if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) return 'unsupported'
  return Notification.permission
}

function decodeKey(value) {
  const raw = atob(value.replace(/-/g, '+').replace(/_/g, '/'))
  return Uint8Array.from(raw, (character) => character.charCodeAt(0))
}

const sessionKey = () => getToken()?.split('|')[0] || null
const registrations = createPushRegistrationGate({
  storage: { getItem: key => localStorage.getItem(key), setItem: (key, value) => localStorage.setItem(key, value), removeItem: key => localStorage.removeItem(key) },
  register: async key => {
    const token = getToken()
    if (sessionKey() !== key) return
    const { data } = await api.get('/notifications/push/config')
    if (!data.data.public_key) throw new Error('Browser notification delivery is not configured on the server.')
    const base = import.meta.env.BASE_URL
    await navigator.serviceWorker.register(`${base}web-notifications-sw.js`, { scope: base, updateViaCache: 'none' })
    const registration = await navigator.serviceWorker.ready
    let subscription = await registration.pushManager.getSubscription()
    if (!subscription) subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: decodeKey(data.data.public_key) })
    if (getToken() !== token) return
    await api.post('/notifications/push/subscription', subscription.toJSON())
  },
})

export async function enableBrowserPush({ requestPermission = true } = {}) {
  const state = browserPushState()
  if (['insecure', 'unsupported'].includes(state)) throw new Error('Open the secure web address in a browser that supports notifications.')
  // This must stay before any asynchronous setup to retain the browser's user gesture.
  const permission = state === 'default' && requestPermission ? await Notification.requestPermission() : Notification.permission
  if (permission !== 'granted') return permission
  return registrations.run(sessionKey(), { force: requestPermission })
}

export async function disableBrowserPush() {
  registrations.forget(sessionKey())
  if (!('serviceWorker' in navigator)) return
  const registration = await navigator.serviceWorker.getRegistration(import.meta.env.BASE_URL)
  const subscription = await registration?.pushManager.getSubscription()
  if (subscription) {
    await api.delete('/notifications/push/subscription', { data: { endpoint: subscription.endpoint } })
    await subscription.unsubscribe()
  }
  for (const notification of await registration?.getNotifications() || []) notification.close()
}
