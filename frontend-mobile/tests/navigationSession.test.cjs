const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
function fixture(api) {
  const writes = [];
  const mocks = {
    'expo-location': {}, 'expo-task-manager': { defineTask() {} },
    'expo-network': { async getNetworkStateAsync() { return { isConnected: true }; } },
    'react-native': { AppState: {} }, '@/api/client': { api },
    './assignmentNavigationSnapshot': { assignmentNavigationSnapshot(value) { return value; } },
    './navigationStore': {
      async getPendingOutboxEvents() { return [{ type: 'household.status', client_event_id: 'event-1', payload: {} }]; },
      async acknowledgeOutbox(value) { writes.push(value); },
      async getActivePack() { return null; },
      async saveBootstrap(value) { writes.push(value); },
    },
  };
  const context = { exports: {}, AbortController, require(name) { if (!(name in mocks)) throw new Error(name); return mocks[name]; } };
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../utils/navigationRuntime.ts'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText, context);
  return { runtime: context.exports, writes };
}
test('logout aborts an in-flight sync before the next account can submit its events', async () => {
  let started;
  const requestStarted = new Promise((resolve) => { started = resolve; });
  const { runtime, writes } = fixture({ post(url, body, { signal }) {
    started();
    return new Promise((resolve, reject) => signal.addEventListener('abort', () => reject(new Error('canceled'))));
  } });
  const syncing = runtime.syncNavigation();
  await requestStarted;
  await runtime.suspendNavigationSync();
  await syncing;
  await runtime.syncNavigation();
  assert.equal(writes.length, 0);
});
test('a navigation refresh started before logout cannot write into the next accounts database', async () => {
  let resolveOverview;
  const overview = new Promise((resolve) => { resolveOverview = resolve; });
  const { runtime, writes } = fixture({ async get(url) {
    if (url === '/rescuer/overview') return overview;
    return { data: { data: {} } };
  } });
  const refreshing = runtime.refreshNavigation();
  await runtime.suspendNavigationSync();
  resolveOverview({ data: { data: { assignments: [{ assignment_id: 12 }] } } });
  await refreshing;
  assert.equal(writes.length, 0);
});
