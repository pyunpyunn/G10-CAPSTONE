import test from 'node:test'
import assert from 'node:assert/strict'
import { createRefreshScheduler } from '../src/utils/refreshScheduler.js'
import { topicsForPath } from '../src/utils/realtimeTopics.js'
import { readFileSync } from 'node:fs'
import vm from 'node:vm'

test('a report burst refreshes immediately then once at the trailing edge', async () => {
  let refreshes = 0
  const scheduler = createRefreshScheduler(() => refreshes++, 30)
  for (let i = 0; i < 100; i++) scheduler.request()
  assert.equal(refreshes, 1)
  await new Promise((resolve) => setTimeout(resolve, 60))
  assert.equal(refreshes, 2)
  scheduler.cancel()
  scheduler.request()
  assert.equal(refreshes, 2)
})

test('unmounted views do not receive a pending refresh', async () => {
  let refreshes = 0
  const scheduler = createRefreshScheduler(() => refreshes++, 30)
  scheduler.request()
  scheduler.request()
  scheduler.cancel()
  await new Promise((resolve) => setTimeout(resolve, 60))
  assert.equal(refreshes, 1)
})

test('all requested pages subscribe to their domains, with privileged topics restricted', () => {
  for (const path of ['/dashboard', '/households', '/mapping', '/dispatch', '/rescue-management', '/field-reports']) {
    const topics = topicsForPath(path)
    assert.ok(topics.includes('households'))
    assert.ok(!topics.includes('accounts'))
    assert.ok(!topics.includes('inquiries'))
  }
  assert.deepEqual(topicsForPath('/super-admin', true), ['inquiries', 'accounts'])
  assert.deepEqual(topicsForPath('/super-admin', false), [])
})

test('connected sockets still reconcile missed changes without reloading the page', () => {
  const timers = new Map(), events = [], channels = []
  let timerId = 0
  const source = readFileSync(new URL('../src/api/realtimeNotifications.js', import.meta.url), 'utf8')
    .replace(/^import .*\n/gm, '').replace(/export /g, '').replace(/import\.meta\.env/g, 'env')
  class Echo {
    connector = { pusher: { connection: { state: 'connected', bind() {}, unbind() {} } } }
    private() {
      const channel = { listen() { return channel }, subscribed(fn) { channels.push(fn); return channel }, error() { return channel } }
      return channel
    }
    disconnect() {}
  }
  const context = { Echo, Pusher: {}, api: {}, env: { VITE_REVERB_APP_KEY: 'test' },
    document: { hidden: false }, Event: class { constructor(type) { this.type = type } },
    CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail } },
    window: { location: { protocol: 'http:', hostname: 'localhost' }, dispatchEvent: event => events.push(event),
      setTimeout(fn, delay) { timers.set(++timerId, { fn, delay }); return timerId }, clearTimeout: id => timers.delete(id) } }
  vm.createContext(context)
  vm.runInContext(source + '\nthis.connect = connectRealtimeNotifications; this.connected = isRealtimeConnected;', context)
  const disconnect = context.connect('user-1', ['households', 'dispatch'])
  channels.forEach(fn => fn())
  assert.equal(context.connected(), true)
  assert.equal(timers.size, 1)
  const timer = [...timers.values()][0]
  assert.ok(timer.delay <= 18000)
  events.length = 0
  timer.fn()
  assert.ok(events.some(event => event.type === 'operations:changed' && event.detail.topics.includes('households')))
  disconnect()
  assert.equal(context.connected(), false)
})
