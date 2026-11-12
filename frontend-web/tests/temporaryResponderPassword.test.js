import test from 'node:test'
import assert from 'node:assert/strict'
import { buildRescuerPayload, temporaryResponderPassword } from '../src/utils/rescuerHelpers.js'

test('temporary responder passwords use uppercase first-name consonants and the generated year', () => {
  assert.equal(temporaryResponderPassword('Venzina', 2026), 'VNZN@2026!')
  assert.equal(temporaryResponderPassword('Aei', 2026), '@2026!')
})

test('edit payload includes a generated replacement password only when one is set', () => {
  const form = { first_name: 'Venzina', password: 'VNZN@2026!' }
  assert.equal(buildRescuerPayload(form, 'edit').password, 'VNZN@2026!')
  assert.equal('password' in buildRescuerPayload({ ...form, password: '' }, 'edit'), false)
})
