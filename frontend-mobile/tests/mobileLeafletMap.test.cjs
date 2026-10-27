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

test('embedded map blocks external navigation and out-of-range coordinates', () => {
  const { Map } = loadMap(true);
  const points = [];
  const props = Map({ markers: [], routes: [], onMapPress: point => points.push(point) }).props.children.props;
  assert.equal(props.onShouldStartLoadWithRequest({ url: 'about:blank' }), true);
  for (const url of ['https://evil.example/', 'file:///etc/passwd', 'javascript:alert(1)', 'intent://example']) {
    assert.equal(props.onShouldStartLoadWithRequest({ url }), false);
  }
  for (const [latitude, longitude] of [[91, 123], [10, 181], [-91, 123], [10, -181]]) {
    props.onMessage({ nativeEvent: { data: JSON.stringify({ type: 'mapPress', latitude, longitude }) } });
  }
  assert.equal(points.length, 0);
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

test('route refresh replaces layers without moving or reloading the map', () => {
  const script=loadMapHtml()({markers:[],routes:[]}).match(/<script>\s*([\s\S]*?)<\/script>/)[1];
  let cameraMoves=0,removed=0,lines=0;
  const map={setView(){cameraMoves++;return this;},on(){},removeLayer(){removed++;}};
  const bounds={isValid:()=>false,extend(){return this;}};
  const window={addEventListener() {}};
  vm.runInNewContext(script,{window,L:{map:()=>map,tileLayer:()=>({addTo(){}}),latLngBounds:()=>bounds,
    control:()=>({addTo(){this.onAdd();}}),DomUtil:{create:()=>({style:{},setAttribute(){}})},DomEvent:{disableClickPropagation(){},on(){}},
    polyline:()=>{lines++;return {addTo(){return this;},bindPopup(){return this;}};},
  }});
  const data={markers:[],routes:[{id:'r1',coordinates:[{latitude:10,longitude:123},{latitude:10.1,longitude:123.1}]}]};
  window.updateRescueData(data);window.updateRescueData(data);
  assert.equal(lines,2);assert.equal(removed,1);assert.equal(cameraMoves,1);
});

test('GPS zooms once, moves one marker, permits panning and recenters on demand', () => {
  const script = loadMapHtml()({ markers: [], routes: [] }).match(/<script>\s*([\s\S]*?)<\/script>/)[1];
  const views = [], mapEvents = {}, buttonEvents = {};
  let markers = 0, moves = 0, removed = 0, zoom = 14;
  const button = { style: {}, setAttribute() {} };
  const map = { setView(point, level) { views.push({ point, level }); zoom = level; return this; },
    on: (name, fn) => { mapEvents[name] = fn; }, getZoom: () => zoom,
    removeLayer() { removed++; } };
  const window = { addEventListener() {} };
  vm.runInNewContext(script, { window, L: {
    map: () => map, tileLayer: () => ({ addTo() {} }),
    latLngBounds: () => ({ isValid: () => false }),
    control: () => ({ addTo() { this.onAdd(); } }),
    DomUtil: { create: (tag) => tag === 'button' ? button : {} },
    DomEvent: { disableClickPropagation() {}, on: (_, name, fn) => { buttonEvents[name] = fn; } },
    circleMarker: () => { markers++; return { addTo() { return this; }, setLatLng() { moves++; } }; },
  } });
  window.setGpsPosition([10.29, 123.88]);
  assert.equal(views.at(-1).level, 17);
  zoom = 18;
  window.setGpsPosition([10.291, 123.88]);
  assert.equal(views.at(-1).level, 18, 'preserve manual zoom after initial GPS fix');
  assert.equal(markers, 1);
  assert.equal(moves, 1);
  mapEvents.dragstart();
  const before = views.length;
  window.setGpsPosition([10.292, 123.88]);
  assert.equal(views.length, before, 'panning pauses camera follow but marker still moves');
  buttonEvents.click();
  assert.equal(views.length, before + 1);
  assert.equal(views.at(-1).level, 17);
  window.setGpsPosition(null);
  assert.equal(removed, 1);
  assert.equal(button.disabled, true);
});

test('household picker fits Mambaling borders and publishes the draggable pin on release', () => {
  const html = loadMapHtml()({ markers: [], routes: [], geotagPicker: true });
  const script = html.match(/<script>\s*([\s\S]*?)<\/script>/)[1];
  const messages = [], events = {}, mapEvents = {}, fits = [];
  let position = { lat: 10.287, lng: 123.88 };
  const map = {
    setView() { return this; }, on: (name, callback) => { mapEvents[name] = callback; },
    fitBounds: (bounds) => fits.push(bounds), getBounds: () => ({ contains: () => true }), removeLayer() {},
  };
  const pin = {
    addTo() { return this; }, bindTooltip() { return this; },
    on: (name, callback) => { events[name] = callback; }, getLatLng: () => position,
    setLatLng(value) { position = Array.isArray(value) ? { lat: value[0], lng: value[1] } : value; },
  };
  let boundary, draggable;
  const buttonEvents = {};
  const window = { addEventListener() {}, ReactNativeWebView: { postMessage: (message) => messages.push(JSON.parse(message)) } };
  vm.runInNewContext(script, {
    window,
    L: {
      map: () => map, tileLayer: () => ({ addTo() { return this; } }), latLngBounds: () => ({ isValid: () => false }),
      control: () => ({ onAdd: null, addTo() { this.onAdd(); return this; } }),
      DomUtil: { create: (tag) => tag === 'button' ? { style: {}, setAttribute() {} } : {} },
      DomEvent: { disableClickPropagation() {}, on: (_, name, fn) => { buttonEvents[name] = fn; } },
      geoJSON: (data) => {
        boundary = data;
        return { addTo() { return this; }, getBounds: () => ({ getCenter: () => position }) };
      },
      latLng: (latitude, longitude) => ({ lat: latitude, lng: longitude }),
      divIcon: (options) => options,
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
  const savedPosition = position;
  mapEvents.click({ latlng: { lat: 10.5, lng: 124 } });
  assert.equal(messages.length, 3, 'clicks outside Mambaling must not move or save the pin');
  position = { lat: 10.5, lng: 124 };
  events.dragend();
  assert.deepEqual(position, savedPosition, 'a pin dragged outside Mambaling must return to its last valid point');
  assert.equal(messages.length, 3, 'outside pin drops must not be saved');
  window.setHouseholdPin(10.5, 124);
  assert.deepEqual(position, savedPosition, 'controlled coordinates outside Mambaling must be ignored');
  assert.equal(fits.length, 1, 'pin changes must preserve the map viewport');
});

test('center pin publishes the map center on every pan end for server validation and toggles satellite imagery', () => {
  const html = loadMapHtml()({ markers: [], routes: [], geotagPicker: true, geotagMode: 'center' });
  const script = html.match(/<script>\s*([\s\S]*?)<\/script>/)[1];
  const messages = [], mapEvents = {}, buttonEvents = {}, tileSources = [];
  let center = { lat: 10.287, lng: 123.88 };
  let pinOptions;
  const map = {
    setView() { return this; }, on: (name, callback) => { mapEvents[name] = callback; },
    fitBounds() {}, getCenter: () => center, panTo(point) { center = point; return this; },
    removeLayer() {},
  };
  const satelliteButton = { style: {}, setAttribute() {}, textContent: '' };
  const window = { addEventListener() {}, ReactNativeWebView: { postMessage: (message) => messages.push(JSON.parse(message)) } };
  vm.runInNewContext(script, {
    window,
    L: {
      map: () => map, tileLayer: (url) => { tileSources.push(url); return { addTo() { return this; } }; },
      latLngBounds: () => ({ isValid: () => false }),
      control: () => ({ onAdd: null, addTo() { this.onAdd(); return this; } }),
      DomUtil: { create: (tag) => tag === 'button' ? satelliteButton : {} },
      DomEvent: { disableClickPropagation() {}, on: (_, name, fn) => { buttonEvents[name] = fn; } },
      geoJSON: () => ({ addTo() { return this; }, getBounds: () => ({ getCenter: () => center }) }),
      latLng: (latitude, longitude) => ({ lat: latitude, lng: longitude }),
      divIcon: (options) => options,
      marker: (_, options) => {
        pinOptions = options;
        return { addTo() { return this; }, setLatLng(point) { this.point = point; } };
      },
    },
  });
  assert.equal(pinOptions.interactive, false, 'the pin stays fixed at the map center');
  assert.equal(pinOptions.draggable, undefined);
  assert.equal(messages.length, 0, 'opening the map does not save a location');
  mapEvents.dragstart();
  center = { lat: 10.288, lng: 123.881 };
  mapEvents.moveend();
  assert.equal(messages.at(-1).type, 'mapPress');
  assert.equal(messages.at(-1).latitude, 10.288);
  mapEvents.dragstart();
  center = { lat: 10.5, lng: 124 };
  mapEvents.moveend();
  assert.equal(messages.at(-1).type, 'mapPress', 'server validation is authoritative and must receive the selected coordinates');
  assert.equal(messages.at(-1).latitude, 10.5);
  assert.equal(messages.at(-1).longitude, 124);
  assert.deepEqual(center, { lat: 10.5, lng: 124 }, 'the selected map center is not silently replaced on the client');
  buttonEvents.click();
  assert.match(tileSources.at(-1), /World_Imagery/);
  assert.equal(satelliteButton.textContent, 'Map');
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
