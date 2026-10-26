const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
function fixture({ offline = false, slowLogout = false, conflict = false, restoreStatus = 200, restoreRole = 'household_resident', loginRole = 'rescuer', ownerRequired = false } = {}) {
  const calls = [], storage = new Map(), payloads = [], views = new Map();
  let binds = 0;
  const mocks = {
    '@/state/apiStore': { saveViewState(key, value) { views.set(key, value); } },
    './client': {
      api: {
        defaults: { baseURL: 'https://example.test/api/v1' },
        async get(url) {
          calls.push(url);
          if (offline) throw new Error('network unavailable');
          if (restoreStatus === 401) throw { response: { status: 401 } };
          return { data: { data: { role: { role_key: restoreRole }, owner_selection_required: ownerRequired } } };
        },
        async post(url, payload) {
        calls.push(url);
        payloads.push({ url, payload });
        if (offline && url.includes('/device-owner/')) throw new Error('network unavailable');
        if (url === '/household/device-owner/select' || url === '/household/device-owner/reset') {
          const pending = url.endsWith('/reset');
          return { data: { token: pending ? 'pending-owner-token' : 'selected-owner-token', owner_options: pending ? { household_id: 'HH-1', members: [] } : undefined, user: { user_id: pending ? 'household-account' : 'member-account', household_id: 'HH-1', member_id: pending ? undefined : payload.member_id, owner_selection_required: pending, role: { role_key: 'household_resident' } } } };
        }
        if (url === '/auth/logout' && offline) throw new Error('offline');
        if (url === '/mobile/login') {
          if (conflict && binds++ === 0) throw { response: { status: 409, data: { message: 'This device is already registered to another account.' } } };
          return { data: { token: 'device-token' } };
        }
        return { data: { token: 'temporary', owner_options: ownerRequired ? { household_id: 'HH-1', members: [{ member_id: 'HM-1-1' }] } : undefined, user: { user_id: 123, household_id: loginRole === 'household_resident' ? 'HH-1' : undefined, owner_selection_required: ownerRequired, role: { role_key: loginRole } } } };
      } },
      async clearToken() { calls.push('clear-token'); storage.delete('resqperation_mobile_token'); }, async saveToken(token) { calls.push(['save-token', token]); storage.set('resqperation_mobile_token', token); },
    },
    '@/utils/pushNotifications': { async logoutPushIdentity() { calls.push('push-logout'); if (offline) throw new Error('offline'); } },
    '@/utils/mobileDeviceRegistration': { async getMobileDeviceUuid() { return 'legacy-device'; } },
    '@/utils/secureStorage': {
      async getStoredItem(key) { return storage.get(key); },
      async setStoredItem(key, value) { storage.set(key, value); },
      async deleteStoredItem(key) { calls.push(['delete-storage', key]); storage.delete(key); },
    },
    'expo-crypto': { randomUUID() { return 'new-device'; } },
    'axios': { default: { post(url, payload, config) {
      calls.push(['remote-logout', url, config?.headers?.Authorization]);
      if (slowLogout) return new Promise(() => {});
      return Promise.resolve();
    } } },
    'react-native': { Platform: { OS: 'android' } },
    '@/utils/navigationStore': { async selectNavigationAccount(id) { calls.push(['select-account', id]); } },
    '@/utils/rescuerLiveLocation': { async stopRescuerTracking() { calls.push('stop-live-gps'); } },
    '@/utils/navigationRuntime': { async endNavigationTrip() { calls.push('end-trip'); }, async suspendNavigationSync() { calls.push('suspend-sync'); } },
  };
  const context = { exports: {}, require(name) { if (!(name in mocks)) throw new Error(`Unexpected import ${name}`); return mocks[name]; } };
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../api/auth.ts'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText, context);
  return { auth: context.exports, calls, storage, payloads, views };
}
test('logout clears the local session when offline and makes a small best-effort server request', async () => {
  const { auth, calls, storage } = fixture({ offline: true });
  storage.set('resqperation_mobile_token', 'current-token');
  storage.set('resqperation_mobile_role', 'rescuer');
  storage.set('resq_household_owner_required', '1');
  await auth.logoutMobile();
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(storage.has('resqperation_mobile_token'), false);
  assert.equal(storage.has('resqperation_mobile_role'), false);
  assert.equal(storage.has('resq_household_owner_required'), false);
  assert.ok(calls.some((call) => Array.isArray(call) && call[0] === 'remote-logout' &&
    call[1] === 'https://example.test/api/v1/auth/logout' && call[2] === 'Bearer current-token'));
});

test('logout does not wait for a slow server logout request', async () => {
  const { auth, calls, storage } = fixture({ slowLogout: true });
  storage.set('resqperation_mobile_token', 'current-token');
  await Promise.race([
    auth.logoutMobile(),
    new Promise((_, reject) => setTimeout(() => reject(new Error('logout waited for the server')), 100)),
  ]);
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(storage.has('resqperation_mobile_token'), false);
  assert.ok(calls.some((call) => Array.isArray(call) && call[0] === 'remote-logout'));
});
test('rescuer login normalizes numeric account IDs and saves the device token', async () => {
  const { auth, calls, payloads } = fixture();
  await auth.loginMobile('rescuer', 'password');
  assert.equal(payloads[0].payload.login, 'rescuer');
  assert.ok(calls.some((item) => Array.isArray(item) && item[0] === 'select-account' && item[1] === '123'));
  assert.ok(calls.some((item) => Array.isArray(item) && item[0] === 'save-token' && item[1] === 'device-token'));
});
test('another account can sign in on a shared phone using a separate device registration', async () => {
  const { auth, calls, storage } = fixture({ conflict: true });
  await auth.loginMobile('rescuer', 'password');
  assert.equal(calls.filter((item) => item === '/mobile/login').length, 2);
  assert.equal(storage.get('resq_device_account_123'), 'rescuer-new-device');
  assert.equal(storage.get('resq_rescuer_device_uuid'), 'rescuer-new-device');
});

test('startup restores an authenticated household session from its saved token', async () => {
  const { auth, calls, storage } = fixture();
  storage.set('resqperation_mobile_token', 'valid-token');
  assert.equal(await auth.restoreMobileSession(), 'household_resident');
  assert.deepEqual(calls, ['/auth/me']);
  assert.equal(storage.get('resqperation_mobile_role'), 'household_resident');
});

test('startup keeps the cached mobile session when the server is temporarily unreachable', async () => {
  const { auth, calls, storage } = fixture({ offline: true });
  storage.set('resqperation_mobile_token', 'valid-token');
  storage.set('resqperation_mobile_role', 'rescuer');
  assert.equal(await auth.restoreMobileSession(), 'rescuer');
  assert.deepEqual(calls, ['/auth/me']);
  assert.equal(storage.get('resqperation_mobile_token'), 'valid-token');
});

test('startup clears an expired server session instead of restoring it', async () => {
  const { auth, calls, storage } = fixture({ restoreStatus: 401 });
  storage.set('resqperation_mobile_token', 'expired-token');
  storage.set('resqperation_mobile_role', 'household_resident');
  assert.equal(await auth.restoreMobileSession(), null);
  assert.deepEqual(calls, ['/auth/me', 'clear-token', ['delete-storage', 'resqperation_mobile_role'], ['delete-storage', 'resq_household_owner_required']]);
});


test('household login sends the shared ID and stable phone ID, and caches the owner picker', async () => {
  const { auth, storage, payloads, views } = fixture({ loginRole: 'household_resident', ownerRequired: true });
  const user = await auth.loginMobile('HH-1', 'household-password');
  assert.equal(user.owner_selection_required, true);
  assert.equal(payloads[0].payload.login, 'HH-1');
  assert.equal(payloads[0].payload.device_uuid, 'legacy-device');
  assert.equal(await auth.householdOwnerSelectionRequired(), true);
  assert.equal(storage.get('resqperation_mobile_token'), 'temporary');
  assert.equal(views.get('mobile:household-owner').household_id, 'HH-1');
});

test('choosing an owner saves the selected session and stops requiring the picker', async () => {
  const { auth, storage, calls, payloads } = fixture();
  storage.set('resq_household_owner_required', '1');
  const user = await auth.selectHouseholdDeviceOwner('HM-1-2');
  assert.equal(payloads.at(-1).url, '/household/device-owner/select');
  assert.equal(payloads.at(-1).payload.member_id, 'HM-1-2');
  assert.equal(user.member_id, 'HM-1-2');
  assert.ok(calls.some((item) => Array.isArray(item) && item[0] === 'select-account' && item[1] === 'member-account'));
  assert.equal(storage.get('resqperation_mobile_token'), 'selected-owner-token');
  assert.equal(storage.get('resq_household_owner_required'), '0');
  assert.equal(storage.get('resqperation_mobile_role'), 'household_resident');
});

test('a lost owner-selection response keeps the pending session for a safe retry', async () => {
  const { auth, storage, calls } = fixture({ offline: true });
  storage.set('resqperation_mobile_token', 'pending-session');
  storage.set('resq_household_owner_required', '1');
  await assert.rejects(auth.selectHouseholdDeviceOwner('HM-1-2'), /network unavailable/);
  assert.equal(storage.get('resqperation_mobile_token'), 'pending-session');
  assert.equal(await auth.householdOwnerSelectionRequired(), true);
  assert.equal(calls.includes('clear-token'), false);
});

test('reset owner stores the restricted picker session without signing the household out', async () => {
  const { auth, storage } = fixture();
  await auth.resetHouseholdDeviceOwner();
  assert.equal(storage.get('resqperation_mobile_token'), 'pending-owner-token');
  assert.equal(await auth.householdOwnerSelectionRequired(), true);
  assert.equal(storage.get('resqperation_mobile_role'), 'household_resident');
});

test('restoring a pending session returns to owner selection and keeps it during an outage', async () => {
  const online = fixture({ ownerRequired: true });
  online.storage.set('resqperation_mobile_token', 'pending');
  assert.equal(await online.auth.restoreMobileSession(), 'household_resident');
  assert.equal(await online.auth.householdOwnerSelectionRequired(), true);
  const offline = fixture({ offline: true });
  offline.storage.set('resqperation_mobile_token', 'pending');
  offline.storage.set('resqperation_mobile_role', 'household_resident');
  offline.storage.set('resq_household_owner_required', '1');
  assert.equal(await offline.auth.restoreMobileSession(), 'household_resident');
  assert.equal(await offline.auth.householdOwnerSelectionRequired(), true);
});
