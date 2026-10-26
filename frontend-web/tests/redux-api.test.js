import test from 'node:test'
import assert from 'node:assert/strict'
import axios from 'axios'
import { installReduxTransport, invalidateApiState, resetApiState, store } from '../src/state/apiStore.js'

function client(load) {
  resetApiState()
  const api = axios.create({ baseURL: 'https://api.resqperation.test/api/v1', adapter: load })
  installReduxTransport(api)
  return api
}
function response(config, data = { value: 1 }) {
  return { config, data, status: 200, statusText: 'OK', headers: {} }
}

test('ten concurrent screen reads use one request and Redux serves subsequent reads', async () => {
  let calls = 0
  const api = client(async config => { calls++; await new Promise(resolve => setTimeout(resolve, 5)); return response(config) })
  const results = await Promise.all(Array.from({ length: 10 }, () => api.get('/households', { params: { page: 1 } })))
  assert.equal(calls, 1)
  results[0].data.value = 99
  assert.equal((await api.get('/households', { params: { page: 1 } })).data.value, 1)
  assert.equal(Object.keys(store.getState().serverData.queries).length, 1)
  assert.equal(calls, 1)
})

test('parameter order is canonical but filters and pages have separate cache entries', async () => {
  let calls = 0
  const api = client(async config => { calls++; return response(config) })
  await api.get('/households', { params: { page: 1, status: 'unsafe' } })
  await api.get('/households', { params: { status: 'unsafe', page: 1 } })
  await api.get('/households', { params: { page: 2, status: 'unsafe' } })
  assert.equal(calls, 2)
})

test('writes and live invalidation force a fresh read, without caching old in-flight results', async () => {
  let finish
  let calls = 0
  const api = client(config => { calls++; return calls === 1 ? new Promise(resolve => { finish = () => resolve(response(config)) }) : Promise.resolve(response(config)) })
  const first = api.get('/households')
  invalidateApiState()
  finish()
  await first
  assert.equal(Object.keys(store.getState().serverData.queries).length, 0)
  await api.get('/households')
  await api.post('/rescuer/field-reports', { notes: 'rescued' })
  await api.get('/households')
  assert.equal(calls, 4)
})

test('changing account or API server never reuses another session response or stores its token', async () => {
  let calls = 0
  const api = client(async config => { calls++; return response(config) })
  await api.get('/profile', { headers: { Authorization: 'Bearer private-one' } })
  await api.get('/profile', { headers: { Authorization: 'Bearer private-two' } })
  await api.get('/profile', { baseURL: 'https://other.resqperation.test/api/v1', headers: { Authorization: 'Bearer private-two' } })
  assert.equal(calls, 3)
  assert.equal(JSON.stringify(store.getState()).includes('private-'), false)
})

test('a late response from the previous account cannot reach a new sessions screen or view cache', async () => {
  let finish
  let calls = 0
  const api = client(config => {
    calls++
    return calls === 1 ? new Promise(resolve => { finish = () => resolve(response(config, { account: 'old' })) })
      : Promise.resolve(response(config, { account: 'new' }))
  })
  const first = api.get('/profile', { headers: { Authorization: 'Bearer old-session' } })
  const rejected = assert.rejects(first, error => axios.isCancel(error))
  const next = await api.get('/profile', { headers: { Authorization: 'Bearer new-session' } })
  finish()
  await rejected
  assert.equal(next.data.account, 'new')
  assert.equal(JSON.stringify(store.getState()).includes('"old"'), false)
})

test('authentication, navigation polling, failures and binary downloads bypass reusable cache', async () => {
  let calls = 0
  const api = client(async config => { calls++; if (config.url === '/failure') throw new Error('network down'); return response(config) })
  for (const path of ['/auth/me', '/map/dispatch-routes']) { await api.get(path); await api.get(path) }
  await assert.rejects(api.get('/failure'))
  await assert.rejects(api.get('/failure'))
  await api.get('/download', { responseType: 'blob' }); await api.get('/download', { responseType: 'blob' })
  assert.equal(calls, 8)
})

test('cancelling one screen does not abort a duplicate read needed by another screen', async () => {
  let finish
  const api = client(config => new Promise(resolve => { finish = () => resolve(response(config)) }))
  const controller = new AbortController()
  const first = api.get('/households', { signal: controller.signal })
  const second = api.get('/households')
  controller.abort()
  finish()
  await assert.rejects(first, error => axios.isCancel(error))
  assert.equal((await second).data.value, 1)
})

test('GPS writes invalidate map reads while retaining unrelated cached directory data', async () => {
  let calls = 0
  const api = client(async config => { calls++; return response(config) })
  await api.get('/households'); await api.get('/map/overview')
  await api.post('/rescuer/assignments/12/location', { latitude: 10, longitude: 123 })
  await api.get('/households'); await api.get('/map/overview')
  assert.equal(calls, 4)
})
