import test from 'node:test'
import assert from 'node:assert/strict'
import { assertJsonApiResponse } from '../src/api/responseContract.js'

test('unexpected success bodies fail visibly instead of becoming empty database lists', () => {
  for (const data of ['<html>Login</html>', null, 'Database error']) {
    assert.throws(() => assertJsonApiResponse({ status: 200, data, config: {} }), error => error.code === 'INVALID_API_RESPONSE')
  }
  const empty = { status: 200, data: { data: [] }, config: {} }
  assert.equal(assertJsonApiResponse(empty), empty)
})

test('downloads and successful no-content writes retain their existing behavior', () => {
  for (const response of [
    { status: 200, data: 'file bytes', config: { responseType: 'blob' } },
    { status: 204, data: '', config: { method: 'delete' } },
    { status: 200, data: '', config: { method: 'head' } },
  ]) assert.equal(assertJsonApiResponse(response), response)
})
