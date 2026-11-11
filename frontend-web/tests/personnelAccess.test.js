import test from 'node:test'
import assert from 'node:assert/strict'
import { hasWebAccess, canOpenWebPage, webHomeForUser } from '../src/utils/personnelAccess.js'

for (const role_key of ['tl', 'tm']) {
  test(`${role_key} can enter the field workspace but cannot open HQ pages`, () => {
    const user = { is_active: true, role: { role_key }, permissions: { web_modules: ['field_assignments', 'profile', 'notifications'] } }
    assert.equal(hasWebAccess(user), true)
    assert.equal(webHomeForUser(user), '/my-assignments')
    for (const path of ['/my-assignments', '/profile', '/notifications']) assert.equal(canOpenWebPage(user, path), true)
    for (const path of ['/dashboard', '/mapping', '/rescuers?manage=roles', '/rescuers/teams', '/dispatch', '/resources-requests', '/situation']) assert.equal(canOpenWebPage(user, path), false)
    user.is_active = false
    assert.equal(hasWebAccess(user), false)
    assert.equal(canOpenWebPage(user, '/my-assignments'), false)
    assert.equal(webHomeForUser(user), '/login')
  })
}
