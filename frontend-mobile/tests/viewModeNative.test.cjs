const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');

function loadViewMode({ platform, width, window }) {
  const source = fs.readFileSync(path.join(__dirname, '../utils/viewMode.ts'), 'utf8');
  const context = {
    exports: {},
    URLSearchParams,
    window,
    require(name) {
      if (name === 'react') {
        return {
          useEffect() {},
          useState() { return [0, () => {}]; },
        };
      }
      if (name === 'react-native') {
        return {
          Platform: { OS: platform },
          useWindowDimensions: () => ({ width }),
        };
      }
      throw new Error(`Unexpected import: ${name}`);
    },
  };
  vm.runInNewContext(ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
  }).outputText, context);
  return context.exports;
}

test('native startup does not require the browser message event API', () => {
  const nativeWindow = { location: { search: '' } };
  nativeWindow.self = nativeWindow;
  nativeWindow.top = nativeWindow;
  const viewMode = loadViewMode({ platform: 'android', width: 400, window: nativeWindow });

  assert.equal(viewMode.useWebViewMode().viewMode, 'mobile');
});

test('web startup still registers the view-mode message handler', () => {
  let messageHandler;
  const webWindow = {
    location: { search: '' },
    addEventListener(type, handler) {
      assert.equal(type, 'message');
      messageHandler = handler;
    },
  };
  webWindow.self = webWindow;
  webWindow.top = webWindow;
  const viewMode = loadViewMode({ platform: 'web', width: 1024, window: webWindow });

  assert.equal(viewMode.useWebViewMode().viewMode, 'desktop');
  messageHandler({ data: { type: 'RESQ_SET_VIEW_MODE', mode: 'mobile' } });
  assert.equal(viewMode.useWebViewMode().viewMode, 'mobile');
});
