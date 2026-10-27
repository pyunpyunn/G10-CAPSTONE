import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import vm from 'node:vm'
import ts from 'typescript'
import { copyWithStructuralSharing } from '@reduxjs/toolkit/query'
import { createRefreshQueue } from '../utils/networkScheduling.js'

// Exercise the hook's async state transitions without mounting native views.
function fixture(read, snapshot = null, persisted = null) {
  const slots = []
  let cursor = 0
  let saved
  const mocks = {
    react: {
      useRef(value) { const i = cursor++; return slots[i] ||= { current: value } },
      useState(value) {
        const i = cursor++
        if (!(i in slots)) slots[i] = typeof value === 'function' ? value() : value
        return [slots[i], next => { slots[i] = typeof next === 'function' ? next(slots[i]) : next }]
      },
      useCallback(callback) { return callback },
    },
    '@reduxjs/toolkit/query': { copyWithStructuralSharing },
    '@/state/apiStore': { getViewState: () => snapshot, saveViewState: (key, value) => { saved = { key, value } } },
    '@/utils/networkScheduling': { createRefreshQueue },
    '@/utils/mobileSnapshot': {
      mobileSnapshotScope: async () => 'session',
      readMobileSnapshot: () => persisted,
      writeMobileSnapshot: (_scope, _key, value) => { persisted = value },
    },
  }
  const output = {}
  const source = ts.transpileModule(fs.readFileSync(new URL('../hooks/useMobileOverview.ts', import.meta.url), 'utf8'),
    { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText
  vm.runInNewContext(source, { exports: output, require: name => {
    assert.ok(mocks[name], `Unexpected dependency ${name}`)
    return mocks[name]
  } })
  return { render() { cursor = 0; return output.useMobileOverview('household', read) }, saved: () => saved }
}

function deferred() {
  let resolve
  const promise = new Promise(done => { resolve = done })
  return { promise, resolve }
}

test('a cold offline start restores the entire persisted overview without expiring it', async () => {
  const value = { profile: { user: { name: 'Resident' } }, members: [{ id: 1 }], trusted: { households: [{ id: 2 }] } }
  const app = fixture(async () => { throw new Error('server unavailable') }, null, { value, savedAt: 123 })
  await app.render().loadOverview()
  const view = app.render()
  assert.equal(view.overview, value)
  assert.equal(view.loading, false)
  assert.equal(view.offline, true)
  assert.equal(view.lastSyncedAt, 123)
})

test('server recovery replaces saved data and clears offline mode', async () => {
  let online = false
  const app = fixture(async () => {
    if (!online) throw new Error('offline')
    return { status: 'updated' }
  }, null, { value: { status: 'old' }, savedAt: 123 })
  await app.render().loadOverview()
  online = true
  await app.render().loadOverview(true)
  const view = app.render()
  assert.equal(view.overview.status, 'updated')
  assert.equal(view.offline, false)
  assert.equal(view.error, '')
  assert.ok(view.lastSyncedAt > 123)
})

test('a warm screen shows its session snapshot immediately without a full loading screen', () => {
  const app = fixture(async () => ({}), { value: { status: 'safe' }, savedAt: 123 })
  const view = app.render()
  assert.equal(view.overview.status, 'safe')
  assert.equal(view.loading, false)
})

test('a slow failed refresh retains visible data and exposes the failure', async () => {
  const app = fixture(async () => { throw new Error('server timeout') }, { value: { status: 'safe' }, savedAt: 123 })
  await app.render().loadOverview(true, true)
  const view = app.render()
  assert.equal(view.overview.status, 'safe')
  assert.equal(view.loading, false)
  assert.equal(view.error, 'server timeout')
})

test('unchanged server data preserves existing object references', async () => {
  const previous = { members: [{ member_id: 'HM-8560-1' }] }
  const app = fixture(async () => ({ members: [{ member_id: 'HM-8560-1' }] }), { value: previous, savedAt: 123 })
  await app.render().loadOverview(true, true)
  assert.equal(app.render().overview, previous)
})

test('an old slow read cannot overwrite a confirmed write before its queued refresh', async () => {
  const old = deferred(), fresh = deferred()
  let calls = 0
  const app = fixture(() => ++calls === 1 ? old.promise : fresh.promise)
  const view = app.render()
  const first = view.loadOverview()
  await Promise.resolve()
  view.setOverview({ status: 'confirmed-saved' })
  const second = view.loadOverview(true, true, true)
  old.resolve({ status: 'obsolete' })
  await first
  assert.equal(app.render().overview.status, 'confirmed-saved')
  assert.equal(app.saved(), undefined)
  fresh.resolve({ status: 'confirmed-saved' })
  await second
  assert.equal(app.render().overview.status, 'confirmed-saved')
  assert.equal(app.saved().value.status, 'confirmed-saved')
  assert.equal(calls, 2)
})
