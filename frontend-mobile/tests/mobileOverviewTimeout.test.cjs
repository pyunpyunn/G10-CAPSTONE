const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const ts = require('typescript');

function loadOverviewModule(file, extraMocks = {}) {
  const requests = [];
  const exports = {};
  const source = fs.readFileSync(path.join(process.cwd(), 'api', file), 'utf8');
  const code = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
  }).outputText;

  vm.runInNewContext(code, {
    exports,
    require(name) {
      if (name === './client') {
        return {
          api: {
            async get(url, config) {
              requests.push({ url, config });
              return { data: { data: { overview: url } } };
            },
          },
        };
      }
      if (name === './reportSubmission') return {};
      if (name === 'react-native') return { Platform: { OS: 'android' } };
      if (name in extraMocks) return extraMocks[name];
      throw new Error(`Unexpected import ${name}`);
    },
  });

  return { exports, requests };
}

test('household and rescuer overviews allow cold Ngrok requests to finish', async () => {
  for (const [file, method, route] of [
    ['household.ts', 'getHouseholdOverview', '/household/overview'],
    ['rescuer.ts', 'getRescuerOverview', '/rescuer/overview'],
  ]) {
    const module = loadOverviewModule(file);
    await module.exports[method]();
    assert.equal(module.requests[0].url, route);
    assert.equal(module.requests[0].config.timeout, 30000);
    assert.equal(module.requests[0].config.cache, true);

    await module.exports[method](true);
    assert.equal(module.requests[1].url, route);
    assert.equal(module.requests[1].config.timeout, 30000);
    assert.equal(module.requests[1].config.cache, false);
  }
});
