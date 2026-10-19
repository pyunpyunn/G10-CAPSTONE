const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const ts = require('typescript');

function load(notify) {
  const exports = {};
  const source = fs.readFileSync(path.join(__dirname, '../utils/foregroundNotification.ts'), 'utf8');
  const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
  vm.runInNewContext(code, { exports, require: () => ({ notifyDisasterBroadcast: notify }) });
  return exports.handleForegroundNotification;
}

test('foreground disaster push displays natively and refreshes the dashboard', () => {
  const calls = [];
  load(() => calls.push('refresh'))({ getNotification: () => ({
    display: () => calls.push('display'), additionalData: { type: 'disaster_broadcast' },
  }) });
  assert.deepEqual(calls, ['display', 'refresh']);
});

test('rescue and other push types still display without disaster refresh', () => {
  const calls = [];
  load(() => calls.push('refresh'))({ getNotification: () => ({ display: () => calls.push('display') }) });
  assert.deepEqual(calls, ['display']);
});

test('a failed dashboard listener cannot prevent native display', () => {
  let displayed = false;
  assert.throws(() => load(() => { throw new Error('listener failed'); })({ getNotification: () => ({
    display: () => { displayed = true; }, additionalData: { type: 'disaster_broadcast' },
  }) }), /listener failed/);
  assert.equal(displayed, true);
});
