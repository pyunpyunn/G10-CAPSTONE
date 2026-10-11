import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import api from './client'

let realtimeConnected = false
export function isRealtimeConnected() { return realtimeConnected }

export function connectRealtimeNotifications(userId, topics = []) {
  let fallbackTimer
  let fallbackDelay = 5000
  let disposed = false
  const operationsRefresh = (changedTopics = topics) => {
    window.dispatchEvent(new CustomEvent('operations:changed', { detail: { topics: changedTopics } }))
  }
  const reconcile = () => {
    window.dispatchEvent(new Event('notifications:changed'))
    operationsRefresh()
  }
  const scheduleFallback = () => {
    window.clearTimeout(fallbackTimer)
    if (disposed || realtimeConnected) return
    fallbackTimer = window.setTimeout(() => {
      if (!document.hidden) reconcile()
      fallbackDelay = Math.min(60000, fallbackDelay * 2)
      scheduleFallback()
    }, fallbackDelay * (0.8 + Math.random() * 0.4))
  }
  realtimeConnected = false
  scheduleFallback()
  const key = import.meta.env.VITE_REVERB_APP_KEY
  if (!key || !userId) return () => { disposed = true; window.clearTimeout(fallbackTimer) }

  const secure = (import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '')) === 'https'
  const echo = new Echo({
    broadcaster: 'reverb', Pusher, key,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8090),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
    forceTLS: secure, enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => ({
      authorize: (socketId, callback) => {
        api.post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
          .then(({ data }) => callback(null, data)).catch((error) => callback(error, null))
      },
    }),
  })
  const connection = echo.connector.pusher.connection
  const subscribed = new Set()
  const channelNames = ['notifications.admin', `users.${userId}`, ...topics.map((topic) => `operations.${topic}`)]
  const updateConnection = () => {
    if (connection.state !== 'connected') subscribed.clear()
    realtimeConnected = connection.state === 'connected' && subscribed.size === channelNames.length
    window.dispatchEvent(new CustomEvent('notifications:connection', { detail: realtimeConnected }))
    if (realtimeConnected) { window.clearTimeout(fallbackTimer); fallbackDelay = 5000 }
    else scheduleFallback()
  }
  const seen = new Set()
  const receiveOperations = (event) => {
    if (seen.has(event.batch_id)) return
    seen.add(event.batch_id)
    if (seen.size > 200) seen.delete(seen.values().next().value)
    operationsRefresh(event.topics)
  }
  connection.bind('state_change', updateConnection)
  for (const name of channelNames) {
    const channel = echo.private(name)
    if (name.startsWith('operations.')) channel.listen('.operations.changed', receiveOperations)
    else channel.listen('.notifications.changed', (event) => {
      window.dispatchEvent(new Event('notifications:changed'))
      // Compatibility with the synchronous mode during rolling deployments.
      if (!event.batch_id) operationsRefresh()
    })
    channel.subscribed(() => {
      subscribed.add(name)
      updateConnection()
      if (realtimeConnected) reconcile()
    }).error(() => { subscribed.delete(name); updateConnection() })
  }
  return () => {
    disposed = true
    window.clearTimeout(fallbackTimer)
    connection.unbind('state_change', updateConnection)
    echo.disconnect()
    realtimeConnected = false
    window.dispatchEvent(new CustomEvent('notifications:connection', { detail: false }))
  }
}
