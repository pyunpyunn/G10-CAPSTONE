const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');
const ts = require('typescript');

function load(file, imports) {
  const exports = {};
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '..', file), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
  }).outputText, { exports, require: (name) => {
    if (name in imports) return imports[name];
    throw new Error(`Unexpected import ${name}`);
  }});
  return exports;
}

test('online pending reports do not show the offline sync banner', () => {
  let disconnected = false;
  const { OfflineBanner } = load('components/OfflineBanner.tsx', {
    'react/jsx-runtime': require('react/jsx-runtime'),
    'react-native': { Pressable: 'Pressable', Text: 'Text', View: 'View' },
    'expo-network': { useNetworkState: () => ({ isConnected: !disconnected, isInternetReachable: !disconnected }) },
  });
  const props = { offline: false, lastSyncedAt: 1, loading: false, pendingStatuses: 1 };
  assert.equal(OfflineBanner(props), null);
  disconnected = true;
  assert.equal(OfflineBanner(props).props.children[0].props.children, 'Status not synced');
  disconnected = false;
  assert.equal(OfflineBanner({ ...props, failedStatuses: 1 }).props.children[0].props.children, 'Status needs attention');
});

test('a report queued during an active sync is sent immediately by its own sync call', async () => {
  const storage = new Map();
  let uuid = 0;
  let release;
  const blocked = new Promise(resolve => { release = resolve; });
  let started;
  const firstStarted = new Promise(resolve => { started = resolve; });
  const sent = [];
  const outbox = load('utils/householdStatusOutbox.ts', {
    'expo-crypto': { randomUUID: () => String(++uuid) },
    'expo-network': { getNetworkStateAsync: async () => ({ isConnected: true, isInternetReachable: true }) },
    './mobileSnapshotStorage': { default: { getItemSync: key => storage.get(key), setItemSync: (key, value) => storage.set(key, value) } },
    './mobileSnapshot': { mobileSnapshotScope: async () => 'household-1' },
    '@/api/household': { saveHouseholdStatus: async payload => {
      sent.push(payload.status_key);
      if (sent.length === 1) { started(); await blocked; }
    } },
  });
  await outbox.enqueueHouseholdStatusReport({ kind: 'household', expectedEventId: 'event-1', payload: { status_key: 'safe' } });
  const first = outbox.syncPendingHouseholdStatuses();
  await firstStarted;
  const id = await outbox.enqueueHouseholdStatusReport({ kind: 'household', expectedEventId: 'event-1', payload: { status_key: 'unsafe' } });
  const second = outbox.syncPendingHouseholdStatuses();
  release();
  await first;
  assert.ok((await second).syncedIds.includes(id));
  assert.deepEqual(sent, ['safe', 'unsafe']);
  assert.equal((await outbox.getHouseholdStatusOutboxCounts()).pending, 0);
});
