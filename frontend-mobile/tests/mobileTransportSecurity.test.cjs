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

function loadApiClient(savedServerUrl, options = {}) {
  let requestInterceptor;
  let responseErrorInterceptor;
  let clientOptions;
  const probedUrls = [];
  const instance = {
    defaults: {},
    getUri(config) { return `${config.baseURL}${config.url}`; },
    async request(config) {
      await requestInterceptor(config);
      return config;
    },
    interceptors: {
      request: { use(callback) { requestInterceptor = callback; } },
      response: { use(_success, failure) { responseErrorInterceptor = failure; } },
    },
  };
  const axios = {
    create(config) { clientOptions = config; return instance; },
    async get(url, config) {
      probedUrls.push({ url, config });
      if (options.probe) return options.probe(url, config);
      throw new Error('Unexpected API probe.');
    },
    CanceledError: class CanceledError extends Error {},
    isAxiosError: () => false,
    isCancel: () => false,
  };
  axios.default = axios;
  const normalizeContext = { exports: {}, URL };
  const normalizeSource = fs.readFileSync(path.join(__dirname, '../utils/normalizeApiServerUrl.ts'), 'utf8');
  vm.runInNewContext(ts.transpileModule(normalizeSource, {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
  }).outputText, normalizeContext);

  const mocks = {
    axios,
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
    process: { env: {
      EXPO_PUBLIC_API_BASE_URL: options.baseUrl || 'https://api.example.test/api/v1',
      EXPO_PUBLIC_API_FALLBACK_URLS: options.fallbackUrls,
    } },
    require(name) {
      if (!(name in mocks)) throw Error(`Unexpected import: ${name}`);
      return mocks[name];
    },
  };
  vm.runInNewContext(ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText, context);

  return {
    clientOptions,
    probedUrls,
    request(config) { return requestInterceptor(config); },
    failover(error) { return responseErrorInterceptor(error); },
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
  assert.equal(client.probedUrls.length, 0);
});

test('API routes are checked in order: ngrok first, then configured fallbacks', async () => {
  const ngrok = 'https://sturdily-morse-sitter.ngrok-free.dev/api/v1';
  const tailscale = 'https://resq.example.ts.net/api/v1';
  const lan = 'https://resq-lan.example.test/api/v1';
  const client = loadApiClient(null, {
    baseUrl: ngrok,
    fallbackUrls: `${tailscale},${lan}`,
    probe: async (url) => {
      if (url.startsWith(ngrok)) throw new Error('ngrok unavailable');
      return { data: { status: 'ready' } };
    },
  });

  let request;
  try {
    request = await client.request({ headers: {}, method: 'post', url: '/auth/login' });
  } catch (error) {
    assert.fail(`${error.message}; probes: ${JSON.stringify(client.probedUrls.map(({ url }) => url))}`);
  }

  assert.deepEqual(client.probedUrls.map(({ url }) => url), [
    `${ngrok}/health/ready`,
    `${tailscale}/health/ready`,
  ]);
  assert.equal(client.probedUrls[0].config.timeout, 4000);
  assert.equal(client.clientOptions.timeout, 20000);
  assert.equal(request.baseURL, tailscale);
});

test('a saved API route is preferred and configured routes remain available as fallbacks', async () => {
  const saved = 'https://resq.example.ts.net/api/v1';
  const configured = 'https://api.example.test/api/v1';
  const client = loadApiClient(saved, {
    baseUrl: configured,
    probe: async (url) => {
      if (url.startsWith(saved)) throw new Error('saved route unavailable');
      return { data: { status: 'ready' } };
    },
  });

  const request = await client.request({ headers: {}, method: 'post', url: '/auth/login' });

  assert.deepEqual(client.probedUrls.map(({ url }) => url), [
    `${saved}/health/ready`,
    `${configured}/health/ready`,
  ]);
  assert.equal(request.baseURL, configured);
});

test('login POST requests fail over only after a connection loss, not an HTTP response', async () => {
  const primary = 'https://api.example.test/api/v1';
  const fallback = 'https://backup.example.test/api/v1';
  const client = loadApiClient(null, {
    baseUrl: primary,
    fallbackUrls: fallback,
    probe: async () => ({ data: { status: 'ready' } }),
  });
  const request = await client.request({ headers: {}, method: 'post', url: '/auth/login' });
  const retried = await client.failover({ config: request });

  assert.equal(retried.baseURL, fallback);
  assert.equal(retried._resqFailoverAttempted, true);
});

test('non-login write requests are not replayed across API routes', async () => {
  const primary = 'https://api.example.test/api/v1';
  const client = loadApiClient(null, {
    baseUrl: primary,
    fallbackUrls: 'https://backup.example.test/api/v1',
    probe: async () => ({ data: { status: 'ready' } }),
  });
  const request = await client.request({ headers: {}, method: 'post', url: '/household/status' });
  const error = { config: request };

  await assert.rejects(client.failover(error), (received) => received === error);
  assert.deepEqual(client.probedUrls.map(({ url }) => url), [`${primary}/health/ready`]);
});
