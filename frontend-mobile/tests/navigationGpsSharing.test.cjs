const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');

function fixture() {
  let subscriber, trip = null, extraGps = 0;
  const points = [];
  const mocks = {
    'expo-location': { async requestForegroundPermissionsAsync() { return { granted: true }; },
      async requestBackgroundPermissionsAsync() { return { granted: true }; }, async hasStartedLocationUpdatesAsync() { return false; },
      async startLocationUpdatesAsync() { extraGps++; } },
    'expo-task-manager': { defineTask() {} }, 'expo-network': { async getNetworkStateAsync() { return { isConnected: false }; } },
    'react-native': { AppState: {} }, '@/api/client': { api: {} }, './assignmentNavigationSnapshot': {},
    './rescuerLiveLocation': { subscribeRescuerPosition(listener) { subscriber = listener; }, hasActiveRescuerTracking: () => true,
      async configureRescuerTracking() { extraGps++; } },
    './navigationStore': { async activeLocalTrip() { return trip; },
      async createLocalTrip(dispatchId) { trip = { localTripId: 'local-1', dispatchId }; return trip; },
      async addLocalTripPoint(id, point) { points.push([id, point]); }, async completeLocalTrip() { trip = null; } },
  };
  const context = { exports: {}, AbortController, require(name) { if (!(name in mocks)) throw Error(name); return mocks[name]; } };
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../utils/navigationRuntime.ts'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText, context);
  return { ...context.exports, points, extraGps: () => extraGps,
    emit: () => subscriber({ timestamp: Date.now(), coords: { latitude: 10.29, longitude: 123.88, accuracy: 5 } }) };
}
test('trip recording reuses HQ GPS, records background fixes and stops recording at trip end', async () => {
  const runtime = fixture();
  await runtime.startNavigationTrip(71, 'auto');
  await runtime.emit();
  assert.equal(runtime.extraGps(), 0, 'no second GPS watch or background service');
  assert.equal(runtime.points.length, 1);
  await runtime.endNavigationTrip({ sync: false });
  await runtime.emit();
  assert.equal(runtime.points.length, 1);
});
test('logout prevents shared GPS from recording into the next session', async () => {
  const runtime = fixture();
  await runtime.startNavigationTrip(71, 'auto');
  await runtime.suspendNavigationSync();
  await runtime.emit();
  assert.equal(runtime.points.length, 0);
});
