import test from 'node:test'
import assert from 'node:assert/strict'
import { store, resetApiState, invalidateApiState } from '../src/state/apiStore.js'
import { operationsApi } from '../src/state/operationsApi.js'

globalThis.window = { location: { hostname: 'localhost' }, addEventListener() {}, removeEventListener() {}, dispatchEvent() {} }
globalThis.localStorage = { getItem: () => null }
const { default: api } = await import('../src/api/client.js')
const wait = () => new Promise(resolve => setTimeout(resolve, 15))
test.afterEach(() => resetApiState())

test('resource writes invalidate subscribed request and archive queries', async () => {
  const calls = new Map()
  api.defaults.adapter = async config => {
    calls.set(config.url, (calls.get(config.url) || 0) + 1)
    return { config, status: 200, headers: {}, data: { data: { requests: [], records: [] } } }
  }
  const requests = store.dispatch(operationsApi.endpoints.resourceRequests.initiate({ page: 1 }))
  const archive = store.dispatch(operationsApi.endpoints.archiveRecords.initiate({ category: 'resource-requests', page: 1 }))
  await Promise.all([requests.unwrap(), archive.unwrap()])
  invalidateApiState(['/resource-requests'])
  await wait(); await wait()
  assert.equal(calls.get('/resource-requests'), 2)
  assert.equal(calls.get('/archive/resource-requests'), 2)
  requests.unsubscribe(); archive.unsubscribe()
})

test('an invalid API envelope is reported instead of appearing as an empty database', async () => {
  api.defaults.adapter = async config => ({ config, status: 200, headers: {}, data: '<html>not API data</html>' })
  const request = store.dispatch(operationsApi.endpoints.resourceRequests.initiate({ page: 1 }))
  await assert.rejects(request.unwrap(), error => error.status === 'INVALID_RESPONSE')
  request.unsubscribe()
})

test('RTK subscriptions share one request and return visits immediately reuse cached operations data', async () => {
  let calls = 0
  api.defaults.adapter = async config => {
    calls++; await wait()
    return { config, status: 200, headers: {}, data: { data: { count: calls } } }
  }
  const args = { view: 'overview' }
  const one = store.dispatch(operationsApi.endpoints.workspace.initiate(args))
  const two = store.dispatch(operationsApi.endpoints.workspace.initiate(args))
  await Promise.all([one.unwrap(), two.unwrap()])
  assert.equal(calls, 1)
  one.unsubscribe(); two.unsubscribe()
  const again = store.dispatch(operationsApi.endpoints.workspace.initiate(args))
  assert.equal(operationsApi.endpoints.workspace.select(args)(store.getState()).data.count, 1)
  await again.unwrap()
  assert.equal(calls, 1)
  invalidateApiState(['/map/'])
  await wait()
  assert.equal(calls, 1, 'GPS/map invalidation must not refetch the operations overview')
  invalidateApiState(['/dispatch'])
  assert.equal(operationsApi.endpoints.workspace.select(args)(store.getState()).data.count, 1, 'retain the visible data during refresh')
  await wait(); await wait()
  assert.equal(calls, 2)
  assert.equal(operationsApi.endpoints.workspace.select(args)(store.getState()).data.count, 2)
  again.unsubscribe()
})

test('operations filters have separate entries and logout removes all cached responses', async () => {
  api.defaults.adapter = async config => ({ config, status: 200, headers: {}, data: { data: { filter: config.params.status } } })
  const active = store.dispatch(operationsApi.endpoints.workspace.initiate({ view: 'management', status: 'active' }))
  const completed = store.dispatch(operationsApi.endpoints.workspace.initiate({ view: 'management', status: 'completed' }))
  assert.equal((await active.unwrap()).filter, 'active')
  assert.equal((await completed.unwrap()).filter, 'completed')
  resetApiState()
  assert.deepEqual(store.getState().operationsApi.queries, {})
  active.unsubscribe(); completed.unsubscribe()
})
