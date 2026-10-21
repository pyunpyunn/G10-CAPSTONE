const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const ts = require('typescript');
const exportsObject = {};
const code = ts.transpileModule(fs.readFileSync(path.join(__dirname, '../utils/householdQr.ts'), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
vm.runInNewContext(code, { exports: exportsObject });
const { householdQrValue } = exportsObject;

test('different households encode different actual IDs, regardless of a legacy QR value', () => {
  const first = householdQrValue({ household_id: 'HH-001', value: 'RESQPERATION-HOUSEHOLD' }, 'HH-001');
  const second = householdQrValue({ household_id: 'HH-002', value: 'RESQPERATION-HOUSEHOLD' }, 'HH-002');
  assert.equal(first, 'HH-001');
  assert.equal(second, 'HH-002');
  assert.notEqual(first, second);
});
test('multiple devices in the same household encode the same household ID', () => {
  assert.equal(householdQrValue({ household_id: 'HH-001' }, 'HH-001'), householdQrValue({ household_id: 'HH-001' }, 'HH-001'));
  assert.equal(householdQrValue({ household_id: 20240001 }, '20240001'), '20240001');
});
test('missing or mismatched household records never produce a scannable fallback', () => {
  for (const [qr, id] of [[null, 'HH-001'], [{}, 'HH-001'], [{household_id:'HH-002'}, 'HH-001'], [{household_id:''}, ''], [{household_id:'HH-001'}, null]]) {
    assert.equal(householdQrValue(qr, id), null);
  }
});
