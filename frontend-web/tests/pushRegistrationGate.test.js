import test from 'node:test'
import assert from 'node:assert/strict'
import { createPushRegistrationGate } from '../src/utils/pushRegistrationGate.js'

function storage() {
  const values = new Map()
  return { getItem: key => values.get(key), setItem: (key, value) => values.set(key, value), removeItem: key => values.delete(key) }
}

test('mount and focus bursts share one registration, including subsequent focus events', async () => {
  let calls = 0
  const saved = storage()
  const gate = createPushRegistrationGate({ register: async () => { calls++ }, storage: saved })
  await Promise.all(Array.from({ length: 100 }, () => gate.run('session-1')))
  for (let i = 0; i < 100; i++) await gate.run('session-1')
  assert.equal(calls, 1)
  const secondTab = createPushRegistrationGate({ register: async () => { calls++ }, storage: saved })
  await secondTab.run('session-1')
  assert.equal(calls, 1)
})

test('429 Retry-After blocks repeated attempts until the cooldown expires', async () => {
  let calls = 0, time = 1000
  const gate = createPushRegistrationGate({ now: () => time, register: async () => {
    if (++calls === 1) throw Object.assign(new Error('HTTP 429'), { response: { status: 429, headers: { 'retry-after': '120' } } })
  } })
  await assert.rejects(gate.run('session-1'), /temporarily busy/)
  for (let i = 0; i < 30; i++) await assert.rejects(gate.run('session-1', { force: true }), /temporarily busy/)
  assert.equal(calls, 1)
  time += 121000
  await gate.run('session-1')
  assert.equal(calls, 2)
})

test('new sessions and a deliberate logout can register again', async () => {
  let calls = 0
  const gate = createPushRegistrationGate({ register: async () => { calls++ }, storage: storage() })
  await gate.run('session-1')
  await gate.run('session-2')
  gate.forget('session-1')
  await gate.run('session-1')
  assert.equal(calls, 3)
})
