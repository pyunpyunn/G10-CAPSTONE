const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const ts = require('typescript');

function dashboard(overview, historyOpen = false) {
  const exports = {};
  let stateIndex = 0;
  const source = fs.readFileSync(path.join(__dirname, '../components/household/HouseholdDashboardScreen.tsx'), 'utf8');
  const code = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
  }).outputText;
  vm.runInNewContext(code, {
    exports,
    require(name) {
      if (name === 'react') return { useEffect() {}, useState: () => [stateIndex++ === 1 ? historyOpen : null, () => {}] };
      if (name === 'react/jsx-runtime') return require(name);
      if (name === 'react-native') return {
        Modal: 'Modal', View: 'View', Text: 'Text', ScrollView: 'ScrollView', Pressable: 'Pressable',
        StyleSheet: { create: (styles) => styles },
      };
      if (name === '@expo/vector-icons/Ionicons') return 'Icon';
      if (name === '@/constants/resqTheme') return { palette: {}, radius: {}, shadow: {}, spacing: {} };
      if (name === '@/utils/time') return { formatPhilippineDateTime: (value) => value };
      if (name === './HouseholdUI') return {
        HouseholdBadge: 'Badge', HouseholdButton: 'Button', HouseholdEmpty: 'Empty',
        HouseholdSection: 'Section', statusColor: () => '#000',
      };
      throw new Error(`Unexpected import ${name}`);
    },
  });
  return exports.HouseholdDashboardScreen({ overview });
}

function text(node) {
  if (Array.isArray(node)) return node.map(text).join(' ');
  if (typeof node === 'string') return node;
  if (node?.type === 'Modal' && !node.props.visible) return '';
  return node?.props ? text(node.props.children) : '';
}

test('household dashboard renders the server broadcast title, message and date', () => {
  const screen = dashboard({ recent_alerts: [{
    broadcast_id: 4, title: 'Signal No. 4', message: 'Please evacuate to nearest site',
    sent_at: 'Oct 20, 2026 12:39 AM',
  }] });
  const content = text(screen);
  assert.match(content, /Signal No. 4/);
  assert.match(content, /Please evacuate to nearest site/);
  assert.match(content, /Oct 20, 2026 12:39 AM/);
});

test('older API responses without recent_alerts still render safely', () => {
  assert.doesNotThrow(() => dashboard({}));
});

test('only the latest alert appears on the dashboard and earlier broadcasts appear in the open modal', () => {
  const overview = { recent_alerts: [
    { broadcast_id: 6, title: 'Newest broadcast', message: 'Current instruction' },
    { broadcast_id: 5, title: 'Earlier broadcast', message: 'Previous instruction' },
  ] };
  assert.match(text(dashboard(overview)), /Newest broadcast/);
  assert.doesNotMatch(text(dashboard(overview)), /Earlier broadcast/);
  assert.match(text(dashboard(overview, true)), /Earlier broadcast/);
});

test('broadcast buttons render the exact four API labels in selected order', () => {
  const content = text(dashboard({ active_event: { name: 'Typhoon' }, status_options: [
    { key: 'test', label: 'Custom broadcast status' }, { key: 'evacuated', label: 'At shelter' },
    { key: 'needs_help', label: 'Rescue requested' }, { key: 'safe', label: 'We are safe' },
  ] }));
  assert.match(content, /Custom broadcast status[\s\S]*At shelter[\s\S]*Rescue requested[\s\S]*We are safe/);
});
