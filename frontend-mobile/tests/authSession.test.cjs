const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
function fixture({ offline = false, conflict = false } = {}) {
  const calls = [], storage = new Map();
  let binds = 0;
  const mocks = {
    './client': {
      api: { defaults: { baseURL: 'https://example.test/api/v1' }, async post(url, payload) {
        calls.push(url);
        if (url === '/auth/logout' && offline) throw new Error('offline');
        if (url === '/mobile/login') {
          if (conflict && binds++ === 0) throw { response: { status: 409, data: { message: 'This device is already registered to another account.' } } };
          return { data: { token: 'device-token' } };
        }
        return { data: { token: 'temporary', user: { user_id: 123, role: { role_key: 'rescuer' } } } };
      } },
      async clearToken() { calls.push('clear-token'); }, async saveToken(token) { calls.push(['save-token', token]); },
    },
    '@/utils/pushNotifications': { async logoutPushIdentity() { calls.push('push-logout'); if (offline) throw new Error('offline'); } },
    '@/utils/mobileDeviceRegistration': { async getMobileDeviceUuid() { return 'legacy-device'; } },
    '@/utils/secureStorage': { async getStoredItem(key) { return storage.get(key); }, async setStoredItem(key, value) { storage.set(key, value); } },
    'expo-crypto': { randomUUID() { return 'new-device'; } },
    'axios': { async post() { calls.push('revoke-temporary-token'); } },
    'react-native': { Platform: { OS: 'android' } },
    '@/utils/navigationStore': { async selectNavigationAccount(id) { calls.push(['select-account', id]); } },
    '@/utils/rescuerLiveLocation': { async stopRescuerTracking() { calls.push('stop-live-gps'); } },
    '@/utils/navigationRuntime': { async endNavigationTrip() { calls.push('end-trip'); }, async suspendNavigationSync() { calls.push('suspend-sync'); } },
  };
  const context = { exports: {}, require(name) { if (!(name in mocks)) throw new Error(`Unexpected import ${name}`); return mocks[name]; } };
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../api/auth.ts'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText, context);
  return { auth: context.exports, calls, storage };
}
test('logout suspends sync, stops recording, and clears credentials even when offline', async () => {
  const { auth, calls } = fixture({ offline: true });
  await auth.logoutMobile();
  assert.deepEqual(calls, ['stop-live-gps', 'suspend-sync', 'end-trip', '/auth/logout', 'push-logout', 'clear-token']);
});
test('rescuer login normalizes numeric account IDs and saves the device token', async () => {
  const { auth, calls } = fixture();
  await auth.loginMobile('rescuer', 'password');
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
