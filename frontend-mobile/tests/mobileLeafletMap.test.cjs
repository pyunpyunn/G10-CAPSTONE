const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const ts = require('typescript');

function loadMap(hasNativeWebView) {
  let webViewImports = 0;
  const source = fs.readFileSync(path.join(__dirname, '../components/MobileLeafletMap.native.tsx'), 'utf8');
  const code = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
  }).outputText;
  const exported = {};
  vm.runInNewContext(code, {
    exports: exported,
    require(name) {
      if (name === 'react') return {
        useRef: () => ({ current: null }), useMemo: (fn) => fn(),
        useCallback: (fn) => fn, useEffect: () => {},
      };
      if (name === 'react/jsx-runtime') return require(name);
      if (name === 'react-native') return {
        View: 'View', Text: 'Text', Pressable: 'Pressable', Linking: { openURL: async () => {} },
        StyleSheet: { create: (styles) => styles },
        TurboModuleRegistry: { get: () => hasNativeWebView ? {} : null },
      };
      if (name === 'react-native-webview') {
        webViewImports++;
        if (!hasNativeWebView) throw new Error('RNCWebViewModule is missing');
        return { WebView: 'WebView' };
      }
      if (name === '@/utils/mobileLeafletHtml') return { mobileLeafletHtml: () => '<html>map</html>' };
      throw new Error(`Unexpected import: ${name}`);
    },
  });
  return { Map: exported.MobileLeafletMap, importedWebViews: () => webViewImports };
}

test('older APK opens the map screen without importing the missing native module', () => {
  const { Map, importedWebViews } = loadMap(false);
  const markers = [{ latitude: 10.3, longitude: 123.9, label: 'Household' }];
  const screen = Map({ markers, routes: [] });
  assert.equal(importedWebViews(), 0);
  assert.equal(screen.props.children[0].props.children, 'Update RESQPERATION to view the map');
  assert.equal(screen.props.children[2].type, 'Pressable');
  assert.equal(screen.props.children[2].props.children.props.children, 'Download app update');
});

test('APK with WebView renders the embedded map and handles only valid map presses', () => {
  const { Map, importedWebViews } = loadMap(true);
  const points = [];
  const screen = Map({ markers: [], routes: [], onMapPress: (point) => points.push(point) });
  const webView = screen.props.children;
  assert.equal(importedWebViews(), 1);
  assert.equal(webView.type, 'WebView');
  assert.equal(webView.props.source.html, '<html>map</html>');
  for (const data of ['invalid JSON', '{"type":"mapPress","latitude":null,"longitude":123}', '{"type":"other","latitude":10,"longitude":123}', '{"type":"mapPress","latitude":10,"longitude":123}']) {
    webView.props.onMessage({ nativeEvent: { data } });
  }
  assert.equal(points.length, 1);
  assert.equal(points[0].latitude, 10);
  assert.equal(points[0].longitude, 123);
});

function loadMapHtml() {
  const source = fs.readFileSync(path.join(__dirname, '../utils/mobileLeafletHtml.ts'), 'utf8');
  const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, esModuleInterop: true } }).outputText;
  const exported = {};
  vm.runInNewContext(code, {
    exports: exported,
    require: () => JSON.parse(fs.readFileSync(path.join(__dirname, '../assets/mambaling-boundary.json'), 'utf8')),
  });
  return exported.mobileLeafletHtml;
}

test('household picker fits Mambaling borders and publishes the draggable pin on release', () => {
  const html = loadMapHtml()({ markers: [], routes: [], geotagPicker: true });
  const script = html.match(/<script>\s*([\s\S]*?)<\/script>/)[1];
  const messages = [], events = {}, mapEvents = {}, fits = [];
  let position = { lat: 10.287, lng: 123.88 };
  const map = {
    setView() { return this; }, on: (name, callback) => { mapEvents[name] = callback; },
    fitBounds: (bounds) => fits.push(bounds), getBounds: () => ({ contains: () => true }),
  };
  const pin = {
    addTo() { return this; }, bindTooltip() { return this; },
    on: (name, callback) => { events[name] = callback; }, getLatLng: () => position,
    setLatLng(value) { position = Array.isArray(value) ? { lat: value[0], lng: value[1] } : value; },
  };
  let boundary, draggable;
  const window = { addEventListener() {}, ReactNativeWebView: { postMessage: (message) => messages.push(JSON.parse(message)) } };
  vm.runInNewContext(script, {
    window,
    L: {
      map: () => map, tileLayer: () => ({ addTo() {} }), latLngBounds: () => ({ isValid: () => false }),
      geoJSON: (data) => {
        boundary = data;
        return { addTo() { return this; }, getBounds: () => ({ getCenter: () => position }) };
      },
      marker: (_, options) => { draggable = options.draggable; return pin; },
    },
  });
  assert.equal(boundary.properties.brgy_name, 'Mambaling');
  assert.equal(fits.length, 1);
  assert.equal(draggable, true);
  assert.equal(messages.length, 0, 'default pin must not save before user chooses');
  events.dragstart();
  events.dragend();
  assert.equal(messages[0].type, 'pinMoving');
  assert.equal(messages[1].latitude, 10.287);
  window.setHouseholdPin(10.288, 123.881);
  assert.equal(messages.length, 2, 'controlled GPS update must not trigger another save');
  mapEvents.click({ latlng: { lat: 10.289, lng: 123.882 } });
  assert.equal(messages[2].latitude, 10.289);
  assert.equal(fits.length, 1, 'pin changes must preserve the map viewport');
});

test('household GPS reminder waits for confirmation and allows cancellation', async () => {
  const source = fs.readFileSync(path.join(__dirname, '../utils/confirmHouseholdGps.ts'), 'utf8');
  const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
  const exported = {};
  let prompt;
  vm.runInNewContext(code, {
    exports: exported,
    require: () => ({ Platform: { OS: 'android' }, Alert: { alert: (...args) => { prompt = args; } } }),
  });
  let answered = false;
  const confirmed = exported.confirmHouseholdGps().then((answer) => { answered = true; return answer; });
  await Promise.resolve();
  assert.equal(answered, false);
  assert.equal(prompt[1], 'Make sure you are in your house to pin your location');
  prompt[2][1].onPress();
  assert.equal(await confirmed, true);
  const cancelled = exported.confirmHouseholdGps();
  prompt[2][0].onPress();
  assert.equal(await cancelled, false);
  const dismissed = exported.confirmHouseholdGps();
  prompt[3].onDismiss();
  assert.equal(await dismissed, false);
});
