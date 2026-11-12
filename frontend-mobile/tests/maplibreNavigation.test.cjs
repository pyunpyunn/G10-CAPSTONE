const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const ts = require('typescript');

function load(file, imports) {
  const exports = {};
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '..', file), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
  }).outputText, { exports, Date, process: { env: {} }, AbortController, require(name) {
    if (name in imports) return imports[name];
    throw new Error(`Unexpected import ${name}`);
  }});
  return exports;
}
function nodes(node) {
  if (Array.isArray(node)) return node.flatMap(nodes);
  return node?.props ? [node, ...nodes(node.props.children)] : [];
}

test('dispatch navigation renders its route and moves the GPS marker without native-ref mutation', async () => {
  const states = [], refs = [];
  let stateIndex = 0, refIndex = 0, effects = [], emit;
  const route = { assignment_id: 7, household_id: 'HH-1', status: 'ready', profile: 'auto',
    coordinates: [{ longitude: 123.88, latitude: 10.28 }, { longitude: 123.89, latitude: 10.29 }],
    distance_m: 1500, duration_s: 180, steps: [] };
  const geometry = load('utils/routeGeometry.ts', {});
  const selection = load('utils/assignedNavigationRoute.ts', {});
  const { default: Screen } = load('components/rescuer/OfflineNavigationScreen.maplibre.tsx', {
    react: {
      memo: fn => fn, useMemo: fn => fn(),
      useState(value) { const i = stateIndex++; if (!(i in states)) states[i] = value; return [states[i], value => { states[i] = typeof value === 'function' ? value(states[i]) : value; }]; },
      useRef(value) { const i = refIndex++; return refs[i] ||= { current: value }; },
      useEffect(fn) { effects.push(fn); },
    },
    'react/jsx-runtime': require('react/jsx-runtime'),
    'react-native': { Alert: { alert() {} }, Modal: 'Modal', Pressable: 'Pressable', ScrollView: 'ScrollView', Text: 'Text', View: 'View', StyleSheet: { create: x => x } },
    '@maplibre/maplibre-react-native': { Map: 'Map', Camera: 'Camera', Marker: 'Marker', GeoJSONSource: 'GeoJSONSource', Layer: 'Layer' },
    'expo-router': { Stack: { Screen: 'StackScreen' }, useLocalSearchParams: () => ({ dispatchId: '7' }) },
    '@/utils/navigationStore': {
      getLocalNavigationSnapshot: async () => ({ dispatches: [{ assignment_id: 7, status: 'accepted', household_id: 'HH-1' }], routes: [route], households: [], evacuation_centers: [], status_options: [] }),
      getActivePack: async () => null, activeLocalTrip: async () => null,
    },
    'expo-crypto': {}, '@/utils/navigationRuntime': {},
    '@/utils/navigationPacks': { navigationPackStatus: async () => null },
    '@/utils/routeGeometry': geometry, '@/api/client': { api: {} },
    '@/constants/resqTheme': { palette: {}, radius: {}, spacing: {} },
    '@/utils/rescuerLiveLocation': { hasActiveRescuerTracking: () => true, subscribeRescuerPosition: fn => { emit = fn; return () => {}; } },
    '@/utils/assignedNavigationRoute': selection, '@/hooks/useAppActive': { useAppActive: () => true },
  });
  function render() { stateIndex = 0; refIndex = 0; effects = []; return Screen(); }
  let tree = render();
  for (const effect of effects) effect();
  await new Promise(setImmediate);
  tree = render();
  const camera = nodes(tree).find(node => node.type === 'Camera');
  camera.props.ref.current = { fitBounds() {}, jumpTo() {} };
  nodes(tree).find(node => node.type === 'Map').props.onDidFinishLoadingMap();
  for (const longitude of [123.881, 123.882]) {
    emit({ timestamp: Date.now() + longitude, coords: { longitude, latitude: 10.28 + longitude - 123.88, accuracy: 5, heading: 0 } });
    tree = render();
    // Run the route/GPS effects with a camera that provides no marker host methods.
    for (const index of [1, 5, 6, 7]) effects[index]();
    const markers = nodes(tree).filter(node => node.type === 'Marker');
    assert.equal(markers[0].props.lngLat[0], longitude);
    assert.equal(markers[0].props.ref, undefined);
    assert.equal(markers[1].props.lngLat[0], 123.89);
    assert.ok(nodes(tree).some(node => typeof node.type === 'function' && node.props.points?.length === 2));
  }
});
