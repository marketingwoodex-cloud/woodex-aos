/**
 * Runs the original Woodex cPanel PHP application locally.
 *
 *   PHP 8.2 (WebAssembly)  →  serves public_html/ exactly as Apache would
 *   mysql_bridge.py        →  MySQL protocol on 127.0.0.1:3306, backed by the
 *                             SQLite import of the cpanel dump
 *
 *   npm start --prefix tools/php-run          # http://0.0.0.0:3000
 *
 * Nothing in public_html/ is modified: mysqli speaks to the bridge, which
 * speaks SQLite, which holds the real 41-table data.
 */
import { createServer } from 'node:http';
import { createReadStream, existsSync, readFileSync, statSync, mkdirSync, appendFileSync } from 'node:fs';
import { extname, join, normalize, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { loadNodeRuntime, useHostFilesystem } from '@php-wasm/node';
import { PHP, PHPRequestHandler, setPhpIniEntries } from '@php-wasm/universal';

const here = fileURLToPath(new URL('.', import.meta.url));
const repo = resolve(here, '..', '..');
const docRoot = join(repo, 'php-source', 'public_html');
const port = Number(process.env.PORT || 3000);
const phpVersion = process.env.PHP_VERSION || '8.2';
const host = process.env.HOST || '0.0.0.0';
const errorLog = join(repo, '.tmp', 'php-errors.log');

if (!existsSync(docRoot)) {
  console.error(`PHP source not found at ${docRoot}\nExtract the archive first: tools/php-source/assemble.sh`);
  process.exit(1);
}
mkdirSync(join(repo, '.tmp'), { recursive: true });
mkdirSync(join(repo, 'php-source', 'sessions'), { recursive: true, mode: 0o775 });
const sessionDir = join(repo, '.tmp', 'php-sessions');
mkdirSync(sessionDir, { recursive: true, mode: 0o775 });

const MIME = {
  '.html': 'text/html; charset=utf-8', '.htm': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8', '.js': 'application/javascript; charset=utf-8',
  '.mjs': 'application/javascript; charset=utf-8', '.json': 'application/json; charset=utf-8',
  '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.gif': 'image/gif',
  '.webp': 'image/webp', '.svg': 'image/svg+xml', '.ico': 'image/x-icon',
  '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.eot': 'application/vnd.ms-fontobject',
  '.pdf': 'application/pdf', '.txt': 'text/plain; charset=utf-8',
};

/**
 * The public URLs come from the project's own .htaccess. Parse the 301 rules
 * so /shop, /about-us, /e-quotation and friends resolve without Apache.
 */
function buildRewriteRules() {
  const rules = new Map();
  // Slug routes such as /shop/<slug> need a real RegExp, not a lookup table.
  const dynamic = [];
  const htaccess = join(docRoot, '.htaccess');
  if (existsSync(htaccess)) {
    const text = readFileSync(htaccess, 'utf8');
    // Three shapes appear in this .htaccess:
    //   RewriteRule ^shop\.php$ shop [L,R=301]              → /shop serves shop.php
    //   RewriteRule ^shop$ shop.php [L]                     → same mapping, no redirect
    //   RewriteRule ^shop/([a-zA-Z0-9-]+)$ singleproduct.php?proslug=$1 [L,QSA]
    // Only literal and single-capture rules are replayed; anything else is left
    // to the dynamic SEO router in redirect.php.
    for (const line of text.split('\n')) {
      const slugRule = line.match(/^RewriteRule\s+\^(.+)\$\s+([\w\-/]+\.php)\?(\w+)=\$1\s*\[/);
      if (slugRule) {
        const [, from, phpFile, param] = slugRule;
        // Escape the literal segments, turn every (...) group into one capture.
        const pattern = from
          .split(/\([^)]*\)/)
          .map((segment) => segment.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
          .join('([^/]+)');
        dynamic.push({ regex: new RegExp(`^/${pattern}$`), file: `/${phpFile}`, param });
        continue;
      }
      const rule = line.match(/^RewriteRule\s+\^(\\.\w+|[\w\-]+)\$\s+([\w\-/.]+)\s*(\[[^\]]*\])?(.*)$/);
      if (!rule) continue;
      const [, from, to, flags] = rule;
      const fromPhp = from.match(/^([\w\-/]+)\\\.php$/);
      const toPhp = to.match(/^([\w\-/]+)\.php$/);
      if (fromPhp && !to.endsWith('.php')) {
        rules.set(`/${to}`, `/${fromPhp[1]}.php`);
      } else if (!fromPhp && toPhp && flags !== undefined) {
        if (!/^https?:/.test(to)) rules.set(`/${from}`, `/${toPhp[1]}.php`);
      }
    }
  }
  // Structural routes that are not 1:1 file renames.
  rules.set('/lbpanel', '/lbpanel/index.php');
  rules.set('/admin', '/lbpanel/index.php');
  rules.set('/search', '/search_results.php');
  rules.set('/thank-you', '/order_success.php');
  rules.set('/categories', '/shop.php');
  return { rules, dynamic };
}

const { rules: rewriteRules, dynamic: dynamicRules } = buildRewriteRules();

function resolveTarget(pathname) {
  let path = decodeURIComponent(pathname);

  // Direct file (includes .php).
  const direct = join(docRoot, normalize(path));
  if (direct.startsWith(docRoot) && existsSync(direct) && statSync(direct).isFile()) {
    return { file: direct, url: path };
  }

  // Directory index.
  if (existsSync(direct) && statSync(direct).isDirectory()) {
    const index = join(direct, 'index.php');
    if (existsSync(index)) return { file: index, url: path.endsWith('/') ? `${path}index.php` : `${path}/index.php` };
  }

  // Pretty URL from .htaccess.
  const bare = path.replace(/\/+$/, '') || '/';
  const mapped = rewriteRules.get(bare);
  if (mapped) {
    const file = join(docRoot, normalize(mapped));
    if (existsSync(file)) return { file, url: mapped };
  }

  // Slug routes from .htaccess: /shop/<slug>, /blog/<slug>, /projects/<slug>, ...
  for (const rule of dynamicRules) {
    const match = path.match(rule.regex);
    if (match) {
      const file = join(docRoot, normalize(rule.file));
      if (existsSync(file)) {
        return { file, url: rule.file, extraQuery: `${rule.param}=${encodeURIComponent(match[1])}` };
      }
    }
  }

  // Dynamic SEO redirects live in the database (redirect.php) — .htaccess uses
  // it as the final fallback for anything that is not lbpanel/api.
  if (!path.startsWith('/lbpanel') && !path.startsWith('/api')) {
    const fallback = join(docRoot, 'redirect.php');
    if (existsSync(fallback) && !extname(path)) return { file: fallback, url: '/redirect.php' };
  }

  return null;
}

const phpRuntimeId = await loadNodeRuntime(phpVersion, { emscriptenOptions: { processId: 1 } });
const php = new PHP(phpRuntimeId);
useHostFilesystem(php);

// Surface PHP problems while the port is being validated.
setPhpIniEntries(php, {
  display_errors: '1',
  error_reporting: 'E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING',
  log_errors: '1',
  'error_log': '/tmp/php-error.log',
  memory_limit: '512M',
  'upload_max_filesize': '64M',
  'post_max_size': '64M',
  date: 'Asia/Karachi',
  // Sessions live on the host filesystem (useHostFilesystem maps it at /), so
  // they survive across PHP requests and are easy to inspect while debugging.
  'session.save_path': sessionDir,
  'session.gc_maxlifetime': '86400',
  'session.cookie_httponly': '1',
});

const handler = new PHPRequestHandler({
  php,
  documentRoot: docRoot,
  absoluteUrl: `http://localhost:${port}`,
  rewriteRules: [],
  getFileNotFoundAction: () => ({ type: '404' }),
});

const server = createServer(async (req, res) => {
  const started = Date.now();
  try {
    const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
    const target = resolveTarget(url.pathname);

    if (!target) {
      res.writeHead(404, { 'Content-Type': 'text/html; charset=utf-8' });
      res.end(`<h1>404</h1><p>No route for ${url.pathname}</p>`);
      return;
    }

    const ext = extname(target.file).toLowerCase();

    // Static assets go straight from disk — faster and byte-exact.
    if (ext !== '.php') {
      const stats = statSync(target.file);
      res.writeHead(200, {
        'Content-Type': MIME[ext] || 'application/octet-stream',
        'Content-Length': stats.size,
        'Cache-Control': 'public, max-age=3600',
      });
      createReadStream(target.file).pipe(res);
      return;
    }

    // Everything else is executed by PHP.
    const bodyChunks = [];
    for await (const chunk of req) bodyChunks.push(chunk);
    const body = Buffer.concat(bodyChunks);

    const headers = {};
    for (const [key, value] of Object.entries(req.headers)) {
      if (typeof value === 'string') headers[key] = value;
    }
    headers['content-length'] = String(body.length);

    const query = [target.extraQuery, url.search.replace(/^\?/, '')].filter(Boolean).join('&');
    const response = await handler.request({
      url: `http://localhost:${port}${target.url}${query ? `?${query}` : ''}`,
      method: req.method,
      headers,
      body,
    });

    if (response.errors) {
      appendFileSync(errorLog, `[${new Date().toISOString()}] ${target.url}\n${response.errors}\n\n`);
    }

    const outHeaders = { ...response.headers };
    // PHP sets its own Location; keep status as returned (302 etc).
    res.writeHead(response.httpStatusCode || 200, outHeaders);
    res.end(Buffer.from(response.bytes));

    const ms = Date.now() - started;
    if (process.env.VERBOSE) console.log(`${req.method} ${url.pathname} -> ${response.httpStatusCode} (${ms}ms)`);
  } catch (error) {
    appendFileSync(errorLog, `[${new Date().toISOString()}] ${req.url}\n${error.stack || error}\n\n`);
    res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
    res.end(`PHP runner error: ${error.message}`);
  }
});

server.listen(port, host, () => {
  console.log(`\n  Woodex PHP (original app, PHP 8.2 WASM)`);
  console.log(`  ▸ http://${host}:${port}/`);
  console.log(`  ▸ admin  http://${host}:${port}/lbpanel/`);
  console.log(`  ▸ docroot ${docRoot}`);
  console.log(`  ▸ MySQL  ${process.env.MYSQL_BRIDGE || '127.0.0.1:3306'} → SQLite import`);
  console.log(`  ▸ PHP errors → ${errorLog}\n`);
});
