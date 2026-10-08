import test from 'node:test'
import assert from 'node:assert/strict'
import { createRefreshScheduler } from '../src/utils/refreshScheduler.js'
import { topicsForPath } from '../src/utils/realtimeTopics.js'

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
