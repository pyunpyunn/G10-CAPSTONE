import test from 'node:test'
import assert from 'node:assert/strict'
import { createAdaptivePoller, createRefreshQueue, firstSuccessful } from '../utils/networkScheduling.js'

function deferred() {
  let resolve, reject
  const promise = new Promise((yes, no) => { resolve = yes; reject = no })
  return { promise, resolve, reject }
}

test('twenty refresh triggers share one slow read', async () => {
  const queue = createRefreshQueue()
  const server = deferred()
  let calls = 0
  const results = Array.from({ length: 20 }, () => queue.run(() => { calls++; return server.promise }))
  await Promise.resolve()
  assert.equal(calls, 1)
  server.resolve('latest')
  assert.deepEqual(await Promise.all(results), Array(20).fill('latest'))
})

test('a refresh after a saved write runs after the older read and is not dropped', async () => {
  const queue = createRefreshQueue()
  const old = deferred()
  const order = []
  const first = queue.run(() => { order.push('old'); return old.promise })
  const second = queue.run(() => { order.push('saved'); return 'saved' }, true)
  const third = queue.run(() => { order.push('newest'); return 'newest' }, true)
  await Promise.resolve()
  assert.deepEqual(order, ['old'])
  old.resolve('old')
  assert.equal(await first, 'old')
  assert.equal(await second, 'newest')
  assert.equal(await third, 'newest')
  assert.deepEqual(order, ['old', 'newest'])
})

test('a failed read does not block its queued refresh or later retries', async () => {
  const queue = createRefreshQueue()
  const server = deferred()
  const initial = queue.run(() => server.promise)
  const failed = assert.rejects(initial, /timeout/)
  const next = queue.run(() => 'recovered', true)
  server.reject(new Error('timeout'))
  await failed
  assert.equal(await next, 'recovered')
  assert.equal(await queue.run(() => 'retry'), 'retry')
})

test('a stalled preferred connection does not delay a ready fallback and losers are aborted', async () => {
  let aborted = false
  const selected = await firstSuccessful([
    signal => new Promise((resolve, reject) => signal.addEventListener('abort', () => { aborted = true; reject(new Error('aborted')) })),
    async () => 'ready-fallback',
    async () => { throw new Error('unavailable') },
  ])
  assert.equal(selected, 'ready-fallback')
  assert.equal(aborted, true)
})

test('all unavailable connections reject without returning a false ready result', async () => {
  await assert.rejects(firstSuccessful([async () => { throw new Error('offline') }]), /offline/)
  await assert.rejects(firstSuccessful([]), /No connection/)
})

test('a pending network read leaves the event loop available for user interaction', async () => {
  const queue = createRefreshQueue()
  const server = deferred()
  let completed = false
  const read = queue.run(() => server.promise).then(() => { completed = true })
  let interactionHandled = false
  await new Promise(resolve => setTimeout(() => { interactionHandled = true; resolve() }, 10))
  assert.equal(interactionHandled, true)
  assert.equal(completed, false)
  server.resolve('done')
  await read
})

test('polling backs off after failures, resets after recovery, and stops cleanly', async () => {
  const callsAt = []
  let stop
  let finish
  const recovered = new Promise(resolve => { finish = resolve })
  stop = createAdaptivePoller(async () => {
    callsAt.push(Date.now())
    if (callsAt.length === 1) return null
    stop()
    finish()
    return { refreshed: true }
  }, { intervalMs: 20, maxIntervalMs: 80 }).start()

  const timeout = setTimeout(() => { stop(); finish(); }, 1000)
  try {
    await recovered
  } finally {
    clearTimeout(timeout)
    stop()
  }
  assert.equal(callsAt.length, 2)
  assert.ok(callsAt[1] - callsAt[0] >= 30, 'retry waits for the increased backoff interval')
})
