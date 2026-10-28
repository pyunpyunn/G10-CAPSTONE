const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');

function fixture() {
  const secure = new Map();
  const snapshots = new Map();
  const storage = {
    getItemSync: (key) => snapshots.get(key) ?? null,
    setItemSync: (key, value) => snapshots.set(key, value),
    removeItemSync: (key) => snapshots.delete(key),
  };
  const mocks = {
    './mobileSnapshotStorage': { __esModule: true, default: storage },
    'expo-crypto': {
      CryptoDigestAlgorithm: { SHA256: 'SHA-256' },
      async digestStringAsync(_algorithm, value) { return `sha256:${value}`; },
    },
    './secureStorage': {
      async getStoredItem(key) { return secure.get(key) ?? null; },
      async setStoredItem(key, value) { secure.set(key, value); },
      async deleteStoredItem(key) { secure.delete(key); },
    },
    '@/state/apiStore': { invalidateApiState() {} },
  };
  const output = {};
  const source = ts.transpileModule(fs.readFileSync(path.join(__dirname, '../utils/mobileSnapshot.ts'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText;
  vm.runInNewContext(source, {
    exports: output,
    require(name) {
      assert.ok(mocks[name], `Unexpected dependency ${name}`);
      return mocks[name];
    },
  });
  return { api: output, secure, snapshots };
}

test('overview snapshots keep the same account scope when its auth token rotates', async () => {
  const { api, secure } = fixture();
  secure.set('resqperation_mobile_token', 'token-one');
  await api.setMobileSnapshotIdentity('rescuer', 'user-1', undefined, 'https://api.example/api/v1');
  const firstScope = await api.mobileSnapshotScope();

  secure.set('resqperation_mobile_token', 'token-two');
  assert.equal(await api.mobileSnapshotScope(), firstScope);
});

test('successful session restoration migrates a matching legacy token snapshot', async () => {
  const { api, secure } = fixture();
  secure.set('resqperation_mobile_token', 'existing-token');
  api.writeMobileSnapshot('sha256:existing-token', 'rescuer', { value: { assignments: [] }, savedAt: 123 });

  await api.setMobileSnapshotIdentity('rescuer', 'user-1', undefined, 'https://api.example/api/v1', true);
  const scope = await api.mobileSnapshotScope();

  assert.equal(
    JSON.stringify(api.readMobileSnapshot(scope, 'rescuer')),
    JSON.stringify({ value: { assignments: [] }, savedAt: 123 }),
  );
});
