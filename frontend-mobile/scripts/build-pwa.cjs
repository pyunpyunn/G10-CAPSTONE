const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

const output = path.resolve(__dirname, '../dist');
const index = path.join(output, 'index.html');
let html = fs.readFileSync(index, 'utf8');
html = html.replace('</head>', '<link rel="manifest" href="/manifest.webmanifest"><meta name="theme-color" content="#0f1d2d"><link rel="apple-touch-icon" href="/pwa-192.png"></head>');
fs.writeFileSync(index, html);

function files(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
    const filename = path.join(directory, entry.name);
    return entry.isDirectory() ? files(filename) : [filename];
  });
}
// Only exported application assets are cached. API responses and map tiles are excluded.
const assets = files(output).filter(file => /\.(?:html|js|css|png|jpg|jpeg|webp|svg|ico|ttf|woff2?|webmanifest)$/.test(file)
  && path.basename(file) !== 'sw.js');
const version = crypto.createHash('sha256');
for (const file of assets) version.update(fs.readFileSync(file));
const urls = assets.map(file => '/' + path.relative(output, file).split(path.sep).map(encodeURIComponent).join('/'));
const cacheName = `resq-shell-${version.digest('hex').slice(0, 16)}`;
fs.writeFileSync(path.join(output, 'sw.js'), `
const CACHE = ${JSON.stringify(cacheName)};
const ASSETS = ${JSON.stringify(urls)};
const assetsByPath = new Map(ASSETS.map(asset => [decodeURIComponent(new URL(asset, self.location.origin).pathname), asset]));
self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(ASSETS)));
});
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('resq-shell-') && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) return;
  let asset;
  try { asset = assetsByPath.get(decodeURIComponent(url.pathname)); } catch { return; }
  if (request.mode === 'navigate') {
    // Use this worker's matching HTML and JS build, including while offline.
    event.respondWith(caches.open(CACHE).then(cache => cache.match('/index.html')).then(response => response || fetch(request)));
  } else if (asset) {
    event.respondWith(caches.open(CACHE).then(async cache => {
      const saved = await cache.match(asset);
      if (saved) return saved;
      const response = await fetch(request);
      if (response.ok) await cache.put(asset, response.clone());
      return response;
    }));
  }
});
`);
console.log(`PWA shell prepared: ${assets.length} assets, ${cacheName}`);
