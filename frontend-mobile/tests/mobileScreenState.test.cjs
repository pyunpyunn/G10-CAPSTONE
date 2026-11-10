const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');

function load(file, mocks) {
  const output = {};
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '..', file), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
  }).outputText, { exports: output, require(name) {
    if (name === 'react/jsx-runtime') return require(name);
    if (!(name in mocks)) throw Error('Unexpected dependency: ' + name);
    return mocks[name];
  } });
  return output;
}

test('visited mobile tabs stay mounted and unvisited tabs are not loaded', () => {
  let visited;
  const { MobileTabPanels } = load('components/MobileTabPanels.tsx', {
    react: {
      useState(initial) { visited ??= initial; return [visited, next => { visited = typeof next === 'function' ? next(visited) : next; }]; },
      useEffect(effect) { effect(); },
    },
    'react-native': { View: 'View' },
  });
  const render = activeKey => MobileTabPanels({ activeKey, renderPanel: key => key }).props.children;
  assert.deepEqual(Array.from(render('home'), node => node.key), ['home']);
  let panels = render('map');
  assert.deepEqual(Array.from(panels, node => node.key), ['home', 'map']);
  assert.equal(panels[0].props.style.display, 'none');
  panels = render('home');
  assert.deepEqual(Array.from(panels, node => node.key), ['home', 'map']);
  assert.equal(panels[1].props.style.display, 'none');
  assert.equal(panels[1].props.accessibilityElementsHidden, true);
});

test('connectivity changes show offline mode and the last sync time without fetching data', () => {
  let network = { isConnected: true, isInternetReachable: true };
  const { OfflineBanner } = load('components/OfflineBanner.tsx', {
    'react-native': { Text: 'Text', View: 'View' },
    'expo-network': { useNetworkState: () => network },
  });
  const props = { offline: false, lastSyncedAt: 1700000000000, loading: false };
  assert.equal(OfflineBanner(props), null);
  network = { isConnected: false, isInternetReachable: false };
  const banner = OfflineBanner(props);
  assert.equal(banner.props.children[0].props.children, 'Offline mode');
  assert.match(banner.props.children[1].props.children, /Last synced:/);
  network = { isConnected: true, isInternetReachable: true };
  assert.equal(OfflineBanner(props), null);
});
