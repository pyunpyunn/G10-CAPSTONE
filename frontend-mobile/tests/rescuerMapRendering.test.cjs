const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
function fixture(complete = false, offline = false) {
  const slots = [], effects = []; let cursor = 0, mounted = false;
  const mocks = {
    react: { useMemo: fn => fn(), useCallback: fn => fn, useRef: value => ({ current: value }),
      useState(value) { const i = cursor++; if (!(i in slots)) slots[i] = value; return [slots[i], next => { slots[i] = typeof next === 'function' ? next(slots[i]) : next; }]; },
      useEffect(fn) { if (!mounted) effects.push(fn); } },
    'react/jsx-runtime': require('react/jsx-runtime'), '@react-navigation/native': { useIsFocused: () => true },
    'react-native': { View: 'View', Text: 'Text', Pressable: 'Pressable', Alert: {}, StyleSheet: { create: value => value, absoluteFill: {} } },
    '@expo/vector-icons/Ionicons': 'Icon', '@maplibre/maplibre-react-native': { Map: 'Map', Camera: 'Camera', Marker: 'Marker', GeoJSONSource: 'Source', Layer: 'Layer' },
    '@/api/client': {}, '@/hooks/useMapGps': { useMapGps: () => ({ position: null, error: '' }) },
    '@/constants/resqTheme': { palette: {}, radius: {}, spacing: {} },
    '@/utils/navigationStore': { getActivePack: async () => ({ style_url: 'https://unavailable.test/style.json' }) },
    '@/utils/navigationPacks': { navigationPackStatus: async () => complete ? { state: 'complete' } : null },
    'expo-network': { getNetworkStateAsync: async () => ({ isConnected: !offline, isInternetReachable: !offline }) },
    '@/utils/routeGeometry': { downsampleRouteCoordinates: points => points },
    './RescuerUI': { ActionButton: 'ActionButton', EmptyState: 'EmptyState', SectionHeader: 'SectionHeader' },
  };
  const context = { exports: {}, require(name) { if (!(name in mocks)) throw Error(`Unexpected import: ${name}`); return mocks[name]; } };
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../components/rescuer/RescueMapScreen.native.tsx'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
  }).outputText, context);
  function findMap(node) {
    if (Array.isArray(node)) return node.map(findMap).find(Boolean);
    if (!node || !node.props) return null;
    return node.type === 'Map' ? node : findMap(node.props.children);
  }
  return { render() { cursor = 0; const map = findMap(context.exports.RescueMapScreen({ assignments: [], evacuationCenters: [], activeAssignment: null })); mounted = true; return map; },
    async mountEffects() { effects.forEach(effect => effect()); await new Promise(setImmediate); } };
}
test('rescuer Map tab mounts its native map without a GPS fix or assignment', () => {
  const app = fixture();
  const map = app.render();
  assert.ok(map);
  assert.equal(map.props.androidView, 'texture');
});
test('an undownloaded offline-pack manifest cannot replace the working street map', async () => {
  const app = fixture(); app.render(); await app.mountEffects();
  assert.equal(typeof app.render().props.mapStyle, 'object');
});
test('a downloaded map is retained, and a style-loading error restores the street map', async () => {
  const app = fixture(true, true); app.render(); await app.mountEffects();
  const map = app.render();
  assert.equal(map.props.mapStyle, 'https://unavailable.test/style.json');
  map.props.onDidFailLoadingMap();
  assert.equal(typeof app.render().props.mapStyle, 'object');
});

test('online map stays on its street style when a downloaded pack has an unreachable remote style', async () => {
  const app = fixture(true, false); app.render(); await app.mountEffects();
  assert.equal(typeof app.render().props.mapStyle, 'object');
});
