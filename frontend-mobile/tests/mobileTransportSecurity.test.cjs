const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');

function loadUrlNormalizer() {
  const source = fs.readFileSync(path.join(__dirname, '../utils/normalizeApiServerUrl.ts'), 'utf8');
  const context = { exports: {}, URL };
  vm.runInNewContext(ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
  }).outputText, context);
  return context.exports.normalizeApiServerUrl;
}

function resolveCleartextSetting(environment) {
  const configPath = require.resolve('../app.config.js');
  const previous = new Map(Object.keys(environment).map((key) => [key, process.env[key]]));

  try {
    for (const [key, value] of Object.entries(environment)) {
      if (value === undefined) delete process.env[key];
      else process.env[key] = value;
    }

    delete require.cache[configPath];
    const config = require(configPath);
    const buildProperties = config.plugins.find((plugin) => Array.isArray(plugin) && plugin[0] === 'expo-build-properties');
    return buildProperties[1].android.usesCleartextTraffic;
  } finally {
    delete require.cache[configPath];
    for (const [key, value] of previous) {
      if (value === undefined) delete process.env[key];
      else process.env[key] = value;
    }
  }
}

function loadApiClient(savedServerUrl) {
  let requestInterceptor;
  let readyServerProbeCalled = false;
  const instance = {
    defaults: {},
    interceptors: {
      request: { use(callback) { requestInterceptor = callback; } },
      response: { use() {} },
    },
  };
  const axios = {
    create: () => instance,
    CanceledError: class CanceledError extends Error {},
    isAxiosError: () => false,
    isCancel: () => false,
  };
  const normalizeContext = { exports: {}, URL };
  const normalizeSource = fs.readFileSync(path.join(__dirname, '../utils/normalizeApiServerUrl.ts'), 'utf8');
  vm.runInNewContext(ts.transpileModule(normalizeSource, {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
  }).outputText, normalizeContext);

  const mocks = {
    axios,
    '@/utils/networkScheduling': { firstSuccessful() { readyServerProbeCalled = true; throw Error('Unexpected API probe.'); } },
    'expo-constants': { default: {} },
    'react-native': { Platform: { OS: 'android' } },
    '@/utils/secureStorage': {
      async getStoredItem(key) { return key === 'resqperation_server_url' ? savedServerUrl : null; },
      async setStoredItem() {},
      async deleteStoredItem() {},
    },
    '@/state/apiStore': { installReduxTransport() {}, resetApiState() {} },
    '@/utils/normalizeApiServerUrl': normalizeContext.exports,
  };
  const source = fs.readFileSync(path.join(__dirname, '../api/client.ts'), 'utf8');
  const context = {
    exports: {},
    URL,
    __DEV__: false,
    process: { env: { EXPO_PUBLIC_API_BASE_URL: 'https://api.example.test/api/v1' } },
    require(name) {
      if (!(name in mocks)) throw Error(`Unexpected import: ${name}`);
      return mocks[name];
    },
  };
  vm.runInNewContext(ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText, context);

  return {
    readyServerProbeCalled: () => readyServerProbeCalled,
    request(config) { return requestInterceptor(config); },
  };
}

test('release API URLs require HTTPS and normalize the API path', () => {
  const normalize = loadUrlNormalizer();
  assert.equal(normalize(' https://api.example.test/ ', true), 'https://api.example.test/api/v1');
  assert.equal(normalize('https://api.example.test/custom/api/v1/', true), 'https://api.example.test/custom/api/v1');
  assert.throws(() => normalize('http://api.example.test/api/v1', true), /HTTPS/);
});

test('HTTP API URLs are accepted only in development; unsafe URL parts stay rejected', () => {
  const normalize = loadUrlNormalizer();
  assert.equal(normalize('http://192.168.1.10:8000/api/v1', false), 'http://192.168.1.10:8000/api/v1');
  for (const url of [
    'ftp://api.example.test/api/v1',
    'https://user:password@api.example.test/api/v1',
    'https://api.example.test/api/v1?token=secret',
    'https://api.example.test/api/v1#fragment',
  ]) {
    assert.throws(() => normalize(url, true));
  }
  assert.throws(() => normalize('https://api.example.test/other', true), /end with \/api\/v1/);
});

test('Android cleartext traffic is disabled in production, EAS, and release profiles', () => {
  assert.equal(resolveCleartextSetting({ NODE_ENV: 'production', EAS_BUILD: undefined, RESQ_ANDROID_RELEASE: undefined }), false);
  assert.equal(resolveCleartextSetting({
    NODE_ENV: 'development', EAS_BUILD: 'true', RESQ_ANDROID_RELEASE: undefined,
    EXPO_PUBLIC_API_BASE_URL: 'https://api.example.test/api/v1',
  }), false);
  assert.equal(resolveCleartextSetting({
    NODE_ENV: 'development', EAS_BUILD: undefined, RESQ_ANDROID_RELEASE: '1',
    EXPO_PUBLIC_API_BASE_URL: 'https://api.example.test/api/v1',
  }), false);
});

test('Android cleartext traffic is opt-in to a local development build', () => {
  assert.equal(resolveCleartextSetting({ NODE_ENV: 'development', EAS_BUILD: undefined, RESQ_ANDROID_RELEASE: undefined }), true);
  assert.equal(resolveCleartextSetting({ NODE_ENV: 'test', EAS_BUILD: undefined, RESQ_ANDROID_RELEASE: undefined }), false);
});

test('secure release config rejects a missing or non-HTTPS production API endpoint', () => {
  assert.throws(() => resolveCleartextSetting({
    NODE_ENV: 'production',
    EAS_BUILD: undefined,
    RESQ_ANDROID_RELEASE: '1',
    EXPO_PUBLIC_API_BASE_URL: undefined,
    EXPO_PUBLIC_API_FALLBACK_URLS: undefined,
  }), /require an HTTPS API base URL/);
  assert.throws(() => resolveCleartextSetting({
    NODE_ENV: 'production',
    EAS_BUILD: undefined,
    RESQ_ANDROID_RELEASE: '1',
    EXPO_PUBLIC_API_BASE_URL: 'http://api.example.test/api/v1',
    EXPO_PUBLIC_API_FALLBACK_URLS: undefined,
  }), /must use HTTPS/);
  assert.equal(resolveCleartextSetting({
    NODE_ENV: 'production',
    EAS_BUILD: undefined,
    RESQ_ANDROID_RELEASE: '1',
    EXPO_PUBLIC_API_BASE_URL: 'https://api.example.test/api/v1',
    EXPO_PUBLIC_API_FALLBACK_URLS: 'https://backup.example.test/api/v1',
  }), false);
});

test('a previously saved HTTP server URL is rejected before the app probes or sends authenticated requests', async () => {
  const client = loadApiClient('http://old-server.example.test/api/v1');
  await assert.rejects(client.request({ headers: {} }), /HTTPS/);
  assert.equal(client.readyServerProbeCalled(), false);
});
