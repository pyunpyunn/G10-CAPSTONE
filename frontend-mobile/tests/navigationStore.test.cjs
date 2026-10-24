const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const vm = require('node:vm');
const crypto = require('node:crypto');
const { DatabaseSync } = require('node:sqlite');
const ts = require('typescript');
const tick = () => new Promise(setImmediate);

function storeFixture(t, { failInitialization = false } = {}) {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'resq-navigation-test-'));
  const stored = new Map();
  const connections = new Set();
  let opens = 0;
  function connection(name = 'resq_navigation.db') {
    const native = new DatabaseSync(path.join(directory, name));
    connections.add(native);
    return {
      async execAsync(sql) { await tick(); native.exec(sql); },
      async runAsync(sql, ...params) { await tick(); return native.prepare(sql).run(...params); },
      async getFirstAsync(sql, ...params) { await tick(); return native.prepare(sql).get(...params); },
      async getAllAsync(sql, ...params) { await tick(); return native.prepare(sql).all(...params); },
      async closeAsync() { native.close(); connections.delete(native); },
      async withTransactionAsync(task) {
        try { native.exec('BEGIN'); await task(); native.exec('COMMIT'); }
        catch (error) { if (native.isTransaction) native.exec('ROLLBACK'); throw error; }
      },
      async withExclusiveTransactionAsync(task) {
        const transaction = connection(name);
        try { await transaction.withTransactionAsync(() => task(transaction)); }
        finally { await transaction.closeAsync(); }
      },
    };
  }
  const mocks = {
    'react-native': { Platform: { OS: 'android' } },
    './secureStorage': { async getStoredItem(key) { return stored.get(key) ?? null; }, async setStoredItem(key, value) { stored.set(key, value); } },
    'expo-crypto': { randomUUID: crypto.randomUUID },
    'expo-sqlite': { async openDatabaseAsync(name) {
      opens++;
      const db = connection(name);
      if (failInitialization && opens === 1) {
        db.execAsync = async () => { throw new Error('database is locked'); };
      }
      return db;
    } },
  };
  const context = { exports: {}, require(name) {
    if (!(name in mocks)) throw new Error(`Unexpected import: ${name}`);
    return mocks[name];
  } };
  const source = fs.readFileSync(path.join(__dirname, '../utils/navigationStore.ts'), 'utf8');
  vm.runInNewContext(ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText, context);
  t.after(() => {
    for (const native of connections) native.close();
    fs.rmSync(directory, { recursive: true, force: true });
  });
  return { store: context.exports, opens: () => opens };
}

function snapshot(cursor) {
  return { full_snapshot: true, cursor, team: { team_id: 7 },
    households: [{ household_id: 'HH-A' }],
    dispatches: [{ assignment_id: 12, household_id: 'HH-A', status: 'accepted' }],
    routes: [{ team_id: 7, household_id: 'HH-A', profile: 'auto', status: 'ready' }],
  };
}

test('overlapping route refresh, GPS recording and sync retain the route and trip events', async (t) => {
  const { store } = storeFixture(t);
  await store.saveBootstrap(snapshot('initial'));
  const trip = await store.createLocalTrip(12, 'auto', '2026-10-23T00:00:00Z');
  const [start] = await store.getPendingOutboxEvents();
  const point = { latitude: 10.29, longitude: 123.88, accuracy: 5, speed: 5, heading: 0, recordedAt: '2026-10-23T00:00:01Z' };
  const results = await Promise.all([
    store.saveBootstrap(snapshot('first')),
    store.saveBootstrap(snapshot('second')),
    store.addLocalTripPoint(trip.localTripId, point),
    store.addLocalTripPoint(trip.localTripId, { ...point, latitude: 10.2902, recordedAt: '2026-10-23T00:00:06Z' }),
    store.acknowledgeOutbox([{ client_event_id: start.client_event_id, status: 'processed', result: { trip_id: 42 } }]),
    store.getLocalNavigationSnapshot(),
  ]);
  assert.equal(results[2].accepted, true);
  assert.equal(results[3].accepted, true);
  assert.equal(await store.getBootstrapCursor(), 'second');
  const cached = await store.getLocalNavigationSnapshot();
  assert.equal(cached.routes.length, 1);
  assert.equal(cached.dispatches[0].assignment_id, 12);
  const updated = await store.getLocalTrip(trip.localTripId);
  assert.equal(updated.serverTripId, 42);
  assert.ok(updated.distanceM > 20);
  assert.equal((await store.getLocalTripPoints(trip.localTripId)).length, 2);
  assert.equal((await store.getPendingOutboxEvents()).filter((event) => event.type === 'trip.points').length, 2);
});

test('a failed bootstrap rolls back without erasing cached routes or blocking later writes', async (t) => {
  const { store } = storeFixture(t);
  await store.saveBootstrap(snapshot('good'));
  const broken = snapshot('bad');
  broken.routes[0].profile = null;
  await assert.rejects(store.saveBootstrap(broken), /NOT NULL/);
  assert.equal(await store.getBootstrapCursor(), 'good');
  assert.equal((await store.getLocalNavigationSnapshot()).routes.length, 1);
  await store.saveBootstrap(snapshot('recovered'));
  assert.equal(await store.getBootstrapCursor(), 'recovered');
});

test('a failed database initialization can be retried without restarting or clearing app data', async (t) => {
  const fixture = storeFixture(t, { failInitialization: true });
  await assert.rejects(fixture.store.getLocalNavigationSnapshot(), /database is locked/);
  await fixture.store.saveBootstrap(snapshot('recovered'));
  assert.equal(await fixture.store.getBootstrapCursor(), 'recovered');
  assert.equal(fixture.opens(), 2);
});

test('numeric account identity and switching accounts preserve and isolate pending trip events', async (t) => {
  const { store } = storeFixture(t);
  await store.navigationMetadata('owner', '123');
  const trip = await store.createLocalTrip(12, 'auto', '2026-10-23T00:00:00Z');
  await store.selectNavigationAccount(123);
  assert.equal((await store.activeLocalTrip()).localTripId, trip.localTripId);
  await store.selectNavigationAccount('456');
  assert.equal(await store.activeLocalTrip(), null);
  assert.equal(await store.pendingEventCount(), 0);
  await store.selectNavigationAccount('123');
  assert.equal((await store.activeLocalTrip()).localTripId, trip.localTripId);
  assert.equal(await store.pendingEventCount(), 1);
  await store.selectNavigationAccount('789');
  assert.equal(await store.pendingEventCount(), 0);
  await store.selectNavigationAccount('456');
  assert.equal(await store.pendingEventCount(), 0);
});
