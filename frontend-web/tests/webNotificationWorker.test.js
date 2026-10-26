import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import vm from 'node:vm'

test('only persisted allowed events display once; old-format and unrelated pushes are rejected', async () => {
  const listeners = {}, displayed = [], receipts = new Map()
  const cache = { match: async key => receipts.get(key), put: async (key, value) => receipts.set(key, value), keys: async () => [...receipts.keys()], delete: async key => receipts.delete(key) }
  vm.runInNewContext(readFileSync(new URL('../public/web-notifications-sw.js', import.meta.url), 'utf8'), {
    self: { addEventListener: (name, fn) => { listeners[name] = fn }, skipWaiting() {}, location: { origin: 'https://example.test' },
      registration: { scope: 'https://example.test/', getNotifications: async () => [], showNotification: async (title, options) => displayed.push({ title, ...options }) } },
    clients: { claim: async () => {}, matchAll: async () => [] },
    caches: { open: async () => cache }, URL, Response,
  })
  async function push(notice) {
    let done
    listeners.push({ data: { json: () => notice }, waitUntil: promise => { done = promise } })
    await done
  }
  const types = ['Broadcast', 'Dispatch status', 'Dispatch log', 'Approval request', 'PAGASA advisory sync', 'PHIVOLCS advisory sync']
  for (let i = 0; i < types.length; i++) {
    const notice = { schema: 2, id: `audit-${i}`, type: types[i], title: types[i], occurred_at: '2026-10-21 12:00:00', url: '/notifications' }
    await push(notice)
    await push(notice)
  }
  await push({ id: 'audit-old', type: 'Dispatch status', title: 'Old format' })
  await push({ schema: 2, id: 'audit-no-date', type: 'Dispatch status' })
  await push({ schema: 2, id: 'fake-example', type: 'Dispatch status', occurred_at: '2026-10-21 12:00:00' })
  await push({ schema: 2, id: 'audit-unknown', type: 'Household status', occurred_at: '2026-10-21 12:00:00' })
  assert.equal(displayed.length, 6)
  assert.ok(displayed.every(notice => notice.renotify === false))
})
