const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
function fixture(initial) {
  let state, effect, listener;
  const app = { currentState: initial, addEventListener(_, callback) { listener = callback; return { remove() { listener = null; } }; } };
  const context = { exports: {}, require(name) {
    if (name === 'react-native') return { AppState: app };
    if (name === 'react') return { useState(value) { state ??= value; return [state, next => { state = next; }]; }, useEffect(callback) { effect = callback; } };
    throw Error(name);
  } };
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../hooks/useAppActive.ts'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
  }).outputText, context);
  return { render: context.exports.useAppActive, mount: () => effect(), value: () => state,
    resolveBeforeMount(value) { app.currentState = value; }, change(value) { app.currentState = value; listener?.(value); } };
}
test('unknown initial Android state does not hide foreground content', () => {
  const app = fixture(null);
  assert.equal(app.render(), true);
});
test('state resolved before effect subscription is applied without another lifecycle event', () => {
  const app = fixture('background');
  assert.equal(app.render(), false);
  app.resolveBeforeMount('active'); app.mount();
  assert.equal(app.value(), true);
});
test('background and resume changes are observed and the listener is cleaned up', () => {
  const app = fixture('active'); app.render(); const stop = app.mount();
  app.change('background'); assert.equal(app.value(), false);
  app.change('active'); assert.equal(app.value(), true);
  stop(); app.change('background'); assert.equal(app.value(), true);
});
