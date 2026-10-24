const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const ts = require('typescript');
const flush = () => new Promise(setImmediate);

function load({ granted = true, pending = false, currentFix } = {}) {
  let stateListener, fixListener, completeWatch;
  let watches = 0, removed = 0;
  const appState = { currentState: 'active', addEventListener: (_, fn) => {
    stateListener = fn; return { remove() { stateListener = null; } };
  } };
  const location = {
    Accuracy: { Balanced: 3, High: 4 },
    ...(currentFix ? { getCurrentPositionAsync: async () => currentFix } : {}),
    requestForegroundPermissionsAsync: async () => ({ status: granted ? 'granted' : 'denied' }),
    watchPositionAsync: async (options, listener) => {
      watches++; fixListener = listener;
      assert.equal(options.timeInterval, 5000);
      assert.equal(options.distanceInterval, 5);
      assert.equal(options.accuracy, 3);
      const subscription = { remove() { removed++; } };
      if (pending) return new Promise((resolve) => { completeWatch = () => resolve(subscription); });
      return subscription;
    },
  };
  const exports = {};
  const source = fs.readFileSync(path.join(__dirname, '../utils/foregroundMapGps.ts'), 'utf8');
  vm.runInNewContext(ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText,
    { exports, Number, require: (name) => name === 'react-native' ? { AppState: appState } : location });
  return { start: exports.watchForegroundMapGps, counts: () => ({ watches, removed }),
    state(value) { appState.currentState = value; stateListener?.(value); },
    fix(coords, timestamp) { fixListener({ coords, timestamp }); }, resolve: () => completeWatch() };
}

test('GPS stops in the background, resumes once and cleans up on close', async () => {
  const gps = load();
  const stop = gps.start(() => {}, () => {});
  await flush();
  assert.deepEqual(gps.counts(), { watches: 1, removed: 0 });
  gps.state('background');
  assert.deepEqual(gps.counts(), { watches: 1, removed: 1 });
  gps.state('active'); await flush();
  assert.deepEqual(gps.counts(), { watches: 2, removed: 1 });
  stop(); gps.state('active'); await flush();
  assert.deepEqual(gps.counts(), { watches: 2, removed: 2 });
});

test('denied permission preserves the map without starting GPS', async () => {
  const gps = load({ granted: false }), errors = [];
  const stop = gps.start(() => assert.fail('No location expected'), (error) => errors.push(error));
  await flush();
  assert.equal(gps.counts().watches, 0);
  assert.match(errors[0], /Allow location/);
  stop();
});

test('late GPS subscription is removed when the screen closes while starting', async () => {
  const gps = load({ pending: true });
  const stop = gps.start(() => assert.fail('No late update expected'), () => {});
  await flush(); stop(); gps.resolve(); await flush();
  assert.equal(gps.counts().removed, 1);
});

test('invalid and inaccurate fixes are ignored', async () => {
  const gps = load(), points = [];
  const stop = gps.start((point) => points.push(point), () => {});
  await flush();
  gps.fix({ latitude: 10.29, longitude: 123.88, accuracy: 100 });
  gps.fix({ latitude: NaN, longitude: 123.88, accuracy: 10 });
  gps.fix({ latitude: 100, longitude: 123.88, accuracy: 10 });
  gps.fix({ latitude: 10.29, longitude: 123.88, accuracy: 10 });
  assert.equal(points.length, 1);
  assert.equal(points[0].accuracy_m, 10);
  stop();
});

test('opening a map requests a fresh fix and rejects stale watch locations', async () => {
 const received = [];
 const gps = load({ currentFix: { timestamp: Date.now(), coords: { latitude: 10.29, longitude: 123.88, accuracy: 5 } } });
 const stop = gps.start(point => received.push(point), () => {});
 await flush();
 assert.equal(received.length,1);
 assert.equal(received[0].latitude,10.29);
 gps.fix({ latitude: 11, longitude: 124, accuracy: 5 },Date.now()-60000);
 assert.equal(received.length,1);
 stop();
});
