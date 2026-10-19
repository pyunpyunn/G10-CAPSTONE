const test = require('node:test');
const assert = require('node:assert/strict');
const ts = require('typescript');
const fs = require('node:fs');
const vm = require('node:vm');
const { createHash } = require('node:crypto');

function setup({ corrupt = false, resume = 0, status = 206 } = {}) {
  const payload = Buffer.alloc(1024 * 1024 + 23, 7);
  const checksum = createHash('sha256').update(payload).digest('hex');
  const manifest = { version: 'v2', checksum, size_bytes: payload.length, pack_format: 'maplibre_db' };
  const files = new Map(), metadata = new Map(), requests = [], imports = [];
  const filename = `resq-pack-v2-${checksum}.db`;
  if (resume) files.set(filename, payload.subarray(0, resume));
  class File {
    constructor(_root, name) { this.name = name; this.uri = `file:///documents/${name}`; }
    get exists() { return files.has(this.name); }
    get size() { return files.get(this.name)?.length || 0; }
    create() { files.set(this.name, Buffer.alloc(0)); }
    delete() { files.delete(this.name); }
    async bytes() { return new Uint8Array(files.get(this.name)); }
    open() {
      const name = this.name;
      return { offset: 0, close() {}, writeBytes(bytes) {
        const current = files.get(name), next = Buffer.alloc(this.offset + bytes.length);
        current.copy(next); Buffer.from(bytes).copy(next, this.offset); files.set(name, next);
      } };
    }
  }
  const modules = {
    'expo-file-system': { File, Paths: { document: 'documents' } },
    'expo/fetch': { fetch: async (_url, options) => {
      const [start, end] = options.headers.Range.replace('bytes=', '').split('-').map(Number);
      requests.push([start, end]);
      const bytes = new Uint8Array(payload.subarray(start, end + 1));
      if (corrupt) bytes[0] ^= 1;
      return { status, headers: { get: () => `bytes ${start}-${end}/${payload.length}` }, arrayBuffer: async () => bytes.buffer };
    } },
    'expo-crypto': { CryptoDigestAlgorithm: { SHA256: 'sha256' }, digest: async (algorithm, bytes) => new Uint8Array(createHash(algorithm).update(bytes).digest()).buffer },
    'expo-network': { NetworkStateType: { WIFI: 'wifi' }, getNetworkStateAsync: async () => ({ type: 'wifi' }) },
    '@maplibre/maplibre-react-native': { OfflineManager: { mergeOfflineRegions: async (path) => imports.push(path) } },
    '@/api/client': { api: { get: async () => ({ data: { data: { ...manifest, download_url: 'https://signed.example/db' } } }) } },
    './navigationStore': { navigationMetadata: async (key, value) => { if (value !== undefined) metadata.set(key, value); return metadata.get(key) || null; } },
  };
  const output = ts.transpileModule(fs.readFileSync(require('node:path').join(__dirname, '../utils/navigationPackDownload.ts'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 },
  }).outputText;
  const context = { exports: {}, require: (name) => modules[name], Uint8Array, AbortController, setTimeout, clearTimeout };
  vm.runInNewContext(output, context);
  return { download: context.exports.downloadNativeNavigationPack, manifest, files, requests, imports, metadata };
}

test('native pack resumes existing bytes, verifies SHA-256, imports once, then reuses version', async () => {
  const fixture = setup({ resume: 100 });
  await fixture.download(fixture.manifest, () => {});
  assert.equal(fixture.requests[0][0], 100);
  assert.equal(fixture.imports.length, 1);
  assert.equal(fixture.files.size, 0);
  await fixture.download(fixture.manifest, () => {});
  assert.equal(fixture.imports.length, 1);
});
test('checksum mismatch deletes corrupted data and never imports it', async () => {
  const fixture = setup({ corrupt: true });
  await assert.rejects(fixture.download(fixture.manifest, () => {}), /checksum/);
  assert.equal(fixture.imports.length, 0); assert.equal(fixture.files.size, 0);
});
test('a server ignoring Range fails without appending bytes or importing', async () => {
  const fixture = setup({ status: 200 });
  await assert.rejects(fixture.download(fixture.manifest, () => {}), /byte-range/);
  assert.equal(fixture.imports.length, 0);
  assert.equal([...fixture.files.values()][0].length, 0);
});
