const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const ts = require('typescript');
function setup(options = {}) {
  let id = null, token = null, permission = options.permission ?? true, change, foreground;
  const saved = [];
  const actions = [];
  const OneSignal = {
    initialize() {}, login() {}, logout() {},
    Notifications: { addEventListener() {}, getPermissionAsync: async () => permission,
      canRequestPermission: async () => options.canAsk ?? true,
      requestPermission: async () => { actions.push('request'); return permission = true; } },
    User: { addTags() {}, getExternalId: async () => 'resident-1', getOnesignalId: async () => 'user-1',
      pushSubscription: { addEventListener: (_, fn) => { change = fn; }, optIn() { actions.push('optIn'); }, optOut() {},
        getIdAsync: async () => id, getTokenAsync: async () => token, getOptedInAsync: async () => true } },
  };
  const source = fs.readFileSync(path.join(__dirname, '../utils/pushNotifications.ts'), 'utf8');
  const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
  const exports = {};
  vm.runInNewContext(code, { exports, process: { env: {} }, setTimeout: fn => { fn(); }, setInterval() {},
    require(name) {
      if (name === 'expo-constants') return { default: { executionEnvironment: 'standalone', expoConfig: { extra: { oneSignalAppId: 'app-1' } } } };
      if (name === 'react-native') return { Platform: { OS: 'android' }, Linking: { openSettings: async () => { actions.push('settings'); } }, AppState: { currentState: 'active', addEventListener: (_, fn) => { foreground = fn; } } };
      if (name === 'react-native-onesignal') return { OneSignal };
      if (name === '@/api/client') return { api: { get: async () => ({ data: { user: { user_id: 'resident-1' } } }) } };
      if (name === '@/api/device') return { savePushRegistration: async data => saved.push(data) };
      if (name === '@/utils/mobileReadCache') return { notifyDisasterBroadcast() {} };
      if (name === './foregroundNotification') return { handleForegroundNotification() {} };
      if (name === 'expo-router') return { router: { push() {} } };
      throw Error(name);
    },
  });
  return { exports, saved, actions, update: (nextId, nextToken) => { id = nextId; token = nextToken; },
    change: () => change(), foreground: () => foreground('active'), permission: value => { permission = value; } };
}
const flush = () => new Promise(resolve => setImmediate(resolve));
test('notification notice requests permission and opts in when Android can prompt', async () => {
  const state = setup({ permission: false });
  assert.equal(await state.exports.readNotificationPermission(), false);
  assert.equal(await state.exports.enableNotifications(), true);
  assert.deepEqual(state.actions, ['request', 'optIn']);
  assert.equal(await state.exports.readNotificationPermission(), true);
});

test('notification notice opens settings after permission prompts are exhausted', async () => {
  const state = setup({ permission: false, canAsk: false });
  assert.equal(await state.exports.enableNotifications(), false);
  assert.deepEqual(state.actions, ['settings']);
});
test('late and rotated subscriptions update the authenticated device registration', async () => {
  const state = setup();
  const registration = await state.exports.getPushRegistration('device-1');
  assert.equal(registration.permissionStatus, 'undetermined');
  state.update('new-subscription', 'fcm-token'); state.change(); await flush();
  assert.equal(state.saved[0].player_id, 'new-subscription');
  assert.equal(state.saved[0].device_uuid, 'device-1');
  assert.equal(state.saved[0].notification_permission_status, 'granted');
  state.update('rotated-subscription', 'new-token'); state.foreground(); await flush();
  assert.equal(state.saved[1].player_id, 'rotated-subscription');
  state.permission(false); state.change(); await flush();
  assert.equal(state.saved[2].notification_permission_status, 'denied');
  await state.exports.logoutPushIdentity(); state.change(); await flush();
  assert.equal(state.saved.length, 3);
});
