import test from 'node:test'
import assert from 'node:assert/strict'
import { canOpenWebPage, canApproveRequests, webHomeForUser } from '../src/utils/personnelAccess.js'

test('operations admin can open every web module with legacy session permissions', () => {
  const user = { is_active: true, role: { role_key: 'operations_admin' }, permissions: { web_modules: ['personnel_access'], can_approve_requests: false } }
  for (const path of ['/dashboard', '/mapping', '/weather', '/households', '/broadcast', '/dispatch', '/rescue-management', '/field-reports', '/request-types', '/external-requests', '/resources-requests', '/situation', '/archive', '/rescuers', '/profile', '/notifications', '/super-admin']) assert.equal(canOpenWebPage(user, path), true, path)
  assert.equal(canApproveRequests(user), true)
  assert.equal(webHomeForUser(user), '/dashboard')
  user.is_active = false
  assert.equal(canOpenWebPage(user, '/dashboard'), false)
  assert.equal(canApproveRequests(user), false)
})

test('web roles can open read-only profiles without account management', () => {
  for (const module of ['dashboard', 'geospatial_analytics', 'response_operations', 'resources_requests', 'reports_documentation', 'profile', 'notifications', 'personnel_access']) {
    const user = { is_active: true, permissions: { web_modules: [module] } }
    assert.equal(canOpenWebPage(user, '/rescuers/view?id=91'), true)
    if (module !== 'personnel_access') {
      for (const path of ['/rescuers', '/rescuers/edit?id=91', '/rescuers/new', '/rescuers/teams']) assert.equal(canOpenWebPage(user, path), false)
    }
  }
})

test('inactive and mobile-only accounts cannot open web personnel profiles', () => {
  assert.equal(canOpenWebPage({ is_active: false, permissions: { web_modules: ['dashboard'] } }, '/rescuers/view'), false)
  assert.equal(canOpenWebPage({ is_active: true, permissions: { web_modules: [] } }, '/rescuers/view'), false)
})
