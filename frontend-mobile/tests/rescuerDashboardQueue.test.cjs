const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');

function render(status = 'dispatched', next = false) {
  const exports = {};
  const source = fs.readFileSync(path.join(__dirname, '../components/rescuer/RescuerDashboardScreen.tsx'), 'utf8');
  vm.runInNewContext(ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX } }).outputText, {
    exports,
    require(name) {
      if (name === 'react/jsx-runtime') return require(name);
      if (name === 'react') return { useEffect() {}, useState: value => [value, () => {}] };
      if (name === 'react-native') return { FlatList: 'FlatList', Modal: 'Modal', Pressable: 'Pressable', Text: 'Text', View: 'View', StyleSheet: { create: x => x } };
      if (name === '@expo/vector-icons/Ionicons') return 'Icon';
      if (name === '@/constants/resqTheme') return { palette: {}, radius: {}, shadow: {}, spacing: {} };
      if (name === '@/utils/time') return { formatPhilippineDateTime: x => x };
      if (name === '@/api/rescuer') return { getRescuerDispatchHistory() { throw Error('History should load only when opened'); } };
      if (name === './RescuerUI') return Object.fromEntries(['ActionButton','EmptyState','SectionHeader','StatTile','StatusBadge'].map(x => [x,x]));
      throw Error('Unexpected import '+name);
    },
  });
  return exports.RescuerDashboardScreen({ overview: { assignments: [{ assignment_id: 1, household_id: next ? 'HH-2' : 'HH-1', status_key: status,
    route_notes: { assigned_household_count: 2, completed_household_count: next ? 1 : 0,
      assigned_households: [{household_id:'HH-1',household_name:'First test household'}, {household_id:'HH-2',household_name:'Next test household'}],
      pending_household_ids: next ? ['HH-2'] : ['HH-1','HH-2'] } }] }, onStatusChange() {}, onOpenMap() {}, onOpenReport() {} });
}
function nodes(node) {
  if (Array.isArray(node)) return node.flatMap(nodes);
  if (node?.type === 'Modal' && !node.props.visible) return [];
  if (typeof node?.type === 'function') return nodes(node.type(node.props));
  return node && typeof node === 'object' && node.props ? [node, ...nodes(node.props.children)] : [];
}

test('a household can be completed without a detailed field report and the queue remains visible', () => {
  const all = nodes(render('on_scene'));
  const complete = all.find(x => x.type === 'Pressable' && x.props.accessibilityLabel === 'Complete household');
  assert.ok(complete);
  assert.notEqual(complete.props.disabled, true);
  assert.ok(all.some(x => x.type === 'Pressable' && x.props.accessibilityLabel === 'Field report'));
  assert.ok(all.some(x => x.type === 'SectionHeader' && x.props.title === 'Household queue'));
  assert.ok(all.some(x => x.type === 'StatusBadge' && x.props.label === 'Waiting'));
});

test('the next household displays fresh acceptance and completed/current queue badges', () => {
  const all = nodes(render('dispatched', true));
  assert.ok(all.some(x => x.type === 'ActionButton' && x.props.label === 'Accept'));
  assert.ok(!all.some(x => x.type === 'ActionButton' && x.props.label === 'En route'));
  assert.ok(all.some(x => x.type === 'StatusBadge' && x.props.label === 'Completed'));
  assert.ok(all.some(x => x.type === 'StatusBadge' && x.props.label === 'Current'));
});

test('accepted and en-route tasks expose the appropriate next action', () => {
  assert.ok(nodes(render('accepted')).some(x => x.type === 'ActionButton' && x.props.label === 'En route'));
  assert.ok(nodes(render('en_route')).some(x => x.type === 'ActionButton' && x.props.label === 'Arrived'));
});

test('history is behind the queue icon and duplicate quick actions are removed', () => {
 const all = nodes(render('on_scene'));
 const queue = all.find(x => x.type === 'SectionHeader' && x.props.title === 'Household queue');
 assert.equal(queue.props.action.props.accessibilityLabel, 'Dispatch history');
 assert.equal(all.filter(x => x.type === 'Pressable' && x.props.accessibilityLabel === 'Map').length, 1);
 assert.equal(all.filter(x => x.type === 'Pressable' && x.props.accessibilityLabel === 'Field report').length, 1);
});
