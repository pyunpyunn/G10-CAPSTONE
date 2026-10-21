const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
const source = fs.readFileSync(path.join(__dirname, '../utils/assignedNavigationRoute.ts'), 'utf8');
const context = { exports: {} };
vm.runInNewContext(ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText, context);
const { selectNavigationAssignment, selectAssignedRoute } = context.exports;
const closed = { assignment_id: 1, status: 'completed', household_id: 'a' };
const active = { assignment_id: 2, status: 'dispatched', household_ids: ['b'] };
const other = { assignment_id: 3, status: 'accepted', household_id: 'c' };
const routes = [
  { household_id: 'a', profile: 'auto', status: 'ready' },
  { household_id: 'b', profile: 'auto', status: 'failed' },
  { household_id: 'b', profile: 'pedestrian', status: 'ready' },
  { household_id: 'b', profile: 'auto', status: 'ready' },
];
test('opening navigation directly selects an active dispatch and its ready vehicle route', () => {
  const selected = selectNavigationAssignment([closed, active, other]);
  assert.equal(selected, active);
  assert.equal(selectAssignedRoute(routes, selected, 'auto'), routes[3]);
});
test('explicit dispatch or resumed trip takes precedence over first active assignment', () => {
  assert.equal(selectNavigationAssignment([active, other], '3'), other);
});
test('missing assignment never falls back to another household route', () => {
  assert.equal(selectNavigationAssignment([active], '99'), undefined);
  assert.equal(selectAssignedRoute(routes, undefined, 'auto'), null);
  assert.equal(selectAssignedRoute(routes, other, 'auto'), null);
});
test('walking profile selects only its matching ready route', () => {
  assert.equal(selectAssignedRoute(routes, active, 'pedestrian'), routes[2]);
});
test('no active dispatch returns no assignment', () => {
  assert.equal(selectNavigationAssignment([closed, { ...other, status: 'cancelled' }]), undefined);
});
