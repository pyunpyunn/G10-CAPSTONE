const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../dist');
const types = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json',
  '.webmanifest': 'application/manifest+json', '.png': 'image/png', '.svg': 'image/svg+xml', '.ico': 'image/x-icon', '.ttf': 'font/ttf' };
http.createServer((request, response) => {
  let pathname;
  try { pathname = decodeURIComponent(new URL(request.url, 'http://localhost').pathname); }
  catch { response.writeHead(400).end(); return; }
  if (request.method !== 'GET' && request.method !== 'HEAD' || /^\/api(?:\/|$)/.test(pathname)) {
    response.writeHead(404).end(); return;
  }
  let file = path.resolve(root, '.' + pathname);
  if (!file.startsWith(root + path.sep) && file !== root) { response.writeHead(403).end(); return; }
  if (!fs.existsSync(file) || fs.statSync(file).isDirectory()) {
    if (path.extname(pathname)) { response.writeHead(404).end(); return; }
    file = path.join(root, 'index.html');
  }
  response.writeHead(200, { 'Content-Type': types[path.extname(file)] || 'application/octet-stream',
    'Cache-Control': /(?:index\.html|sw\.js|webmanifest)$/.test(file) ? 'no-cache' : 'public, max-age=3600',
    'X-Content-Type-Options': 'nosniff' });
  if (request.method === 'HEAD') response.end(); else fs.createReadStream(file).pipe(response);
}).listen(8099, '127.0.0.1', () => console.log('PWA preview: http://127.0.0.1:8099'));
