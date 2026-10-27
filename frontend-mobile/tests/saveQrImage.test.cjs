const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
function fixture({ platform = 'android', version = 33, granted = true, failure = false } = {}) {
  const calls = [];
  const mocks = {
    'react-native': { Platform: { OS: platform, Version: version } },
    'expo-file-system/legacy': { cacheDirectory: 'file:///cache/', EncodingType: { Base64: 'base64' },
      async writeAsStringAsync(uri, data, options) { calls.push(['write', uri, data, options.encoding]); },
      async deleteAsync(uri) { calls.push(['delete', uri]); } },
    'expo-media-library': { async requestPermissionsAsync(writeOnly) { calls.push(['permission', writeOnly]); return { granted }; },
      async saveToLibraryAsync(uri) { calls.push(['gallery', uri]); if (failure) throw Error('Gallery unavailable'); } },
  };
  const context = { exports: {}, require(name) { if (!(name in mocks)) throw Error(name); return mocks[name]; } };
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../utils/saveQrImage.ts'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
  }).outputText, context);
  return { ...context.exports, calls };
}

test('Android QR download writes directly to gallery and removes its temporary PNG without a picker', async () => {
  const qr = fixture();
  assert.match(await qr.saveQrImage('aGVsbG8=', 'qr-household.png'), /gallery/);
  assert.deepEqual(qr.calls.map(call => call[0]), ['write', 'gallery', 'delete']);
  assert.equal(qr.calls[1][1], 'file:///cache/qr-household.png');
});
test('older Android requests write-only permission before saving', async () => {
  const qr = fixture({ version: 28 });
  await qr.saveQrImage('data', 'qr.png');
  assert.deepEqual(qr.calls[0], ['permission', true]);
});
test('denied photo permission does not write or claim a saved image', async () => {
  const qr = fixture({ platform: 'ios', granted: false });
  await assert.rejects(qr.saveQrImage('data', 'qr.png'), /Allow photo saving/);
  assert.deepEqual(qr.calls.map(call => call[0]), ['permission']);
});
test('gallery failure is reported and the temporary image is removed', async () => {
  const qr = fixture({ failure: true });
  await assert.rejects(qr.saveQrImage('data', 'qr.png'), /Gallery unavailable/);
  assert.equal(qr.calls.at(-1)[0], 'delete');
});
