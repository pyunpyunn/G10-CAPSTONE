const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
const code = ts.transpileModule(fs.readFileSync(path.join(__dirname, '../utils/savedGps.ts'), 'utf8'), {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText;
function fixture(storage = new Map(), osFix = null) {
  let cacheReads = 0;
  const context = { exports: {}, require(name) {
    if (name === 'expo-location') return { async getLastKnownPositionAsync() { cacheReads++; if (osFix instanceof Error) throw osFix; return osFix; } };
    if (name === './secureStorage') return { async getStoredItem(key) { return storage.get(key); }, async setStoredItem(key, value) { storage.set(key, value); } };
    throw Error(`Unexpected GPS operation: ${name}`);
  } };
  vm.runInNewContext(code, context);
  return { ...context.exports, storage, cacheReads: () => cacheReads };
}
const fix = (latitude, timestamp = Date.now(), accuracy = 5) => ({ timestamp, coords: { latitude, longitude: 123.88, accuracy } });

test('saved GPS survives app restart and remains usable with GPS unavailable, without acquiring a new fix', async () => {
  const first = fixture();
  const timestamp = Date.now() - 86400000;
  await first.rememberGps(fix(10.29, timestamp));
  const restarted = fixture(first.storage, new Error('GPS disabled'));
  const saved = await restarted.getSavedGps();
  assert.equal(saved.latitude, 10.29);
  assert.equal(saved.timestamp, timestamp);
  assert.equal(restarted.cacheReads(), 0, 'saved location is returned without waiting for Android GPS');
});

test('inaccurate, invalid and older fixes never replace the last usable GPS', async () => {
  const gps = fixture();
  const timestamp = Date.now();
  await gps.rememberGps(fix(10.29, timestamp));
  for (const value of [fix(10.3, timestamp + 1, 200), fix(91), fix(10.4, timestamp - 1000)]) await gps.rememberGps(value);
  assert.equal((await gps.getSavedGps()).latitude, 10.29);
});

test('a saved-position subscriber receives new GPS without reopening the map', async () => {
  const gps = fixture();
  const positions = [];
  const stop = gps.subscribeSavedGps(point => positions.push(point));
  await gps.rememberGps(fix(10.29));
  stop();
  await gps.rememberGps(fix(10.3, Date.now() + 1));
  assert.equal(positions.length, 1);
  assert.equal((await gps.getSavedGps()).latitude, 10.3);
});

test('a corrupt cache falls back to the OS saved GPS', async () => {
  const gps = fixture(new Map([['resq_last_device_gps', 'broken JSON']]), fix(10.29));
  assert.equal((await gps.getSavedGps()).latitude, 10.29);
});
