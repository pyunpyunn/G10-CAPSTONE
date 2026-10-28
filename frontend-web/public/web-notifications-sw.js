/* global self, clients, caches, Response */
const allowedTypes = new Set(['Broadcast', 'Dispatch status', 'Dispatch log', 'Approval request', 'PAGASA advisory sync', 'PHIVOLCS advisory sync'])
let queue = Promise.resolve()

self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', (event) => event.waitUntil((async () => {
  // Dismiss queued alerts from the previous, faulty delivery format.
  for (const notice of await self.registration.getNotifications()) if (notice.data?.schema !== 2) notice.close()
  await clients.claim()
})()))
self.addEventListener('push', (event) => {
  queue = queue.catch(() => {}).then(async () => {
    let notice
    try { notice = event.data?.json() } catch { return }
    if (!notice || notice.schema !== 2 || !allowedTypes.has(notice.type)
      || !/^(audit|broadcast|request|advisory)-/.test(notice.id || '') || !notice.occurred_at) return
    const seen = await caches.open('resqperation-web-notices-v2')
    const receipt = new URL(`notification-seen/${encodeURIComponent(notice.id)}`, self.registration.scope).href
    if (await seen.match(receipt)) return
    const url = new URL(notice.url || 'notifications', self.registration.scope)
    if (url.origin !== self.location.origin) return
    // Keep notification links inside the standalone web deployment.
    const base = new URL(self.registration.scope).pathname
    const path = `${base}${url.pathname.replace(/^\//, '')}${url.search}`
    await self.registration.showNotification(notice.title || 'RESQPERATION', {
      body: notice.body || '', tag: notice.id, renotify: false,
      data: { url: path, schema: 2, id: notice.id },
    })
    await seen.put(receipt, new Response('seen'))
    const receipts = await seen.keys()
    for (const old of receipts.slice(0, Math.max(0, receipts.length - 500))) await seen.delete(old)
    for (const client of await clients.matchAll({ type: 'window', includeUncontrolled: true })) client.postMessage({ type: 'notifications:changed' })
  })
  event.waitUntil(queue)
})
self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  event.waitUntil((async () => {
    const url = new URL(event.notification.data?.url || self.registration.scope, self.location.origin)
    if (url.origin !== self.location.origin) return
    const windows = await clients.matchAll({ type: 'window', includeUncontrolled: true })
    for (const client of windows) {
      if (new URL(client.url).origin === url.origin) { await client.navigate(url.href); return client.focus() }
    }
    return clients.openWindow(url.href)
  })())
})
