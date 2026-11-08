import test from 'node:test'
import assert from 'node:assert/strict'

test('successful household sync refreshes mounted household and dispatch pages', async () => {
  const previousWindow = globalThis.window
  const previousLocalStorage = globalThis.localStorage
  const browser = new EventTarget()
  browser.location = { hostname: 'localhost', href: 'http://localhost:5175/' }
  globalThis.window = browser
  globalThis.localStorage = { getItem: () => null }

  try {
    const { default: api } = await import('../src/api/client.js')
    const previousAdapter = api.defaults.adapter
    let update
    browser.addEventListener('operations:changed', event => { update = event })
    api.defaults.adapter = async config => ({
      config,
      data: { data: { total: 1 } },
      status: 200,
      statusText: 'OK',
      headers: {},
    })

    try {
      await api.post('/households/directory/sync', {})
    } finally {
      api.defaults.adapter = previousAdapter
    }

    assert.deepEqual(update?.detail?.topics, ['households', 'dispatch'])
  } finally {
    if (previousWindow === undefined) delete globalThis.window
    else globalThis.window = previousWindow
    if (previousLocalStorage === undefined) delete globalThis.localStorage
    else globalThis.localStorage = previousLocalStorage
  }
})
