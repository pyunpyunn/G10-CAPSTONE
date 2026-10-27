const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
function fixture(saved, fix) {
  let requests = 0, expire, cleared = false;
  const context = { exports: {}, setTimeout(fn) { expire = fn; return 1; }, clearTimeout() { cleared = true; }, require(name) {
    if (name === './savedGps') return { getSavedGps: async () => saved, rememberGps: async () => {} };
    if (name === 'expo-location') return { Accuracy: { High: 4 }, getCurrentPositionAsync() { requests++; return fix ?? new Promise(() => {}); } };
    throw Error(name);
  } };
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../utils/rescueActionGps.ts'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
  }).outputText, context);
  return { run: context.exports.rescueActionGps, requests: () => requests, expire: () => expire(), cleared: () => cleared };
}
test('dispatch action reuses recent saved GPS instead of waiting for a new fix', async () => {
  const gps = fixture({ latitude: 10.29, longitude: 123.88, timestamp: Date.now() });
  assert.equal((await gps.run()).latitude, 10.29);
  assert.equal(gps.requests(), 0);
});
test('GPS acquisition that never resolves times out rather than leaving an action pending indefinitely', async () => {
  const gps = fixture(null);
  const result = gps.run(); await new Promise(setImmediate);
  gps.expire();
  await assert.rejects(result, /taking too long/);
  assert.equal(gps.cleared(), true);
});
test('stale fixes cannot be used to accept or start a dispatch', async () => {
  const gps = fixture(null, Promise.resolve({ timestamp: Date.now() - 60000, coords: { latitude: 10.29, longitude: 123.88, accuracy: 5 } }));
  await assert.rejects(gps.run(), /recent, accurate/);
});
