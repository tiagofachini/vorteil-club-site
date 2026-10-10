/**
 * Servidor estático local para pré-visualizar o site.
 * Sem dependências: usa apenas módulos nativos do Node.
 * Uso: `npm run dev` (ou `node scripts/dev-server.js [porta]`).
 *
 * Escuta só em 127.0.0.1, então não fica exposto na rede local.
 * Resolve caminhos absolutos (/assets/style.css) como o servidor de produção.
 */
const http = require('http');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const PORT = Number(process.argv[2] || process.env.PORT || 5173);

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.xml': 'application/xml; charset=utf-8',
  '.txt': 'text/plain; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.ico': 'image/x-icon',
};

// Arquivos de desenvolvimento que nunca devem ser servidos, nem localmente.
const BLOCKED = /(^|\/)(\.git|\.github|node_modules|scripts|\.env)(\/|$)|(^|\/)package(-lock)?\.json$/;

function resolveFile(urlPath) {
  const decoded = decodeURIComponent(urlPath.split('?')[0]);
  const target = path.normalize(path.join(ROOT, decoded));
  if (target !== ROOT && !target.startsWith(ROOT + path.sep)) return null; // path traversal
  const rel = path.relative(ROOT, target).split(path.sep).join('/');
  if (BLOCKED.test(rel)) return null;
  let file = target;
  if (fs.existsSync(file) && fs.statSync(file).isDirectory()) file = path.join(file, 'index.html');
  return fs.existsSync(file) && fs.statSync(file).isFile() ? file : null;
}

http
  .createServer((req, res) => {
    let file;
    try {
      file = resolveFile(req.url);
    } catch {
      file = null;
    }
    if (!file) {
      res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      res.end('404 Not Found');
      return;
    }
    res.writeHead(200, {
      'Content-Type': MIME[path.extname(file).toLowerCase()] || 'application/octet-stream',
      'Cache-Control': 'no-store',
    });
    fs.createReadStream(file).pipe(res);
  })
  .listen(PORT, '127.0.0.1', () => {
    console.log(`Pré-visualização em http://localhost:${PORT}`);
  });
