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
import { execSync } from 'node:child_process';
import { extname, join, normalize, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { randomBytes } from 'node:crypto';
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

/**
 * Link aliases for the local run.
 *
 * The delivered header/footer templates and the footer_settings defaults point
 * at pages and category slugs that do not exist in the database (they 404 on
 * the live host as well). Rather than leaving dead ends in the preview, known
 * menu targets are redirected to the closest real page; anything else under
 * /categories or /blog falls back to the listing page.
 */
const LINK_ALIASES = new Map([
  // footer "Shop" column → categories that exist in the import
  ['/categories/ergonomic-chair', '/categories/office-chairs'],
  ['/categories/executive-desks', '/categories/executive-table'],
  ['/categories/manager-tables', '/categories/manager-table'],
  ['/categories/office-chair', '/categories/office-chairs'],
  ['/categories/workstations', '/categories/workstation'],
  ['/categories/cubicle-system', '/categories/cubicle-workstation'],
  ['/categories/office-sofa', '/categories/office-sofas'],
  ['/categories/office-storage', '/categories/office-storages'],
  ['/categories/office-sofa-storage', '/categories/office-sofas'],
  ['/categories/reception', '/categories/reception-desk'],
  // footer "Quick links" / "Learn more" columns
  ['/portfolio', '/projects'],
  ['/b2b-markets', '/b2b'],
  ['/careers', '/contact-us'],
  ['/distributors', '/b2b'],
  ['/support', '/contact-us'],
  ['/resources', '/blog'],
  ['/ideas-and-inspiration', '/projects'],
  ['/awards', '/about-us'],
  ['/planning-ideas', '/space-planning'],
  ['/workspace-design', '/space-planning'],
  // lbpanel/pos/manage_pos.php redirects logged-out visitors to ../login.php,
  // which does not exist (the login form is lbpanel/index.php) — a 404 dead end.
  ['/lbpanel/login.php', '/lbpanel/index.php'],
  ['/login.php', '/lbpanel/index.php'],
]);

/** Slugs known to the database, used for /sitemap.xml and the listing fallback. */
function loadSlugs() {
  try {
    const raw = execSync(`python3 tools/php-run/list-slugs.py ${join(repo, '.tmp', 'php-live.sqlite')}`, {
      encoding: 'utf8',
      cwd: repo,
      timeout: 15000,
    });
    const parsed = JSON.parse(raw);
    return {
      categories: Array.isArray(parsed.categories) ? parsed.categories : [],
      products: Array.isArray(parsed.products) ? parsed.products : [],
    };
  } catch (error) {
    console.warn('could not read slugs for the sitemap:', error.message);
    return { categories: [], products: [] };
  }
}

const slugs = loadSlugs();

function sitemapXml(origin) {
  const pages = ['/', '/shop', '/about-us', '/contact-us', '/blog', '/faqs', '/projects', '/services',
    '/showrooms', '/b2b', '/space-planning', '/warranty', '/privacy-policy', '/terms-and-conditions'];
  const urls = [
    ...pages,
    ...slugs.categories.map((slug) => `/categories/${slug}`),
    ...slugs.products.map((slug) => `/shop/${slug}`),
  ];
  const body = urls
    .map((path) => `  <url><loc>${origin}${path}</loc></url>`)
    .join('\n');
  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemap.org/schemas/sitemap/0.9">
${body}
</urlset>`;
}

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

/**
 * Everything PHP needs before the application runs lives in
 * tools/security/guard.php (Apache-style cwd, admin authentication, session
 * rotation, baseline headers). It is registered below as auto_prepend_file.
 */
const prependFile = join(repo, 'tools', 'security', 'guard.php');

const bootRuntime = () => loadNodeRuntime(phpVersion, { emscriptenOptions: { processId: 1 } });
const phpRuntimeId = await bootRuntime();
const php = new PHP(phpRuntimeId);
useHostFilesystem(php);

// Surface PHP problems while the port is being validated.
setPhpIniEntries(php, {
  display_errors: '1',
  error_reporting: 'E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING',
  log_errors: '1',
  'error_log': errorLog,
  memory_limit: '512M',
  'upload_max_filesize': '64M',
  'post_max_size': '64M',
  date: 'Asia/Karachi',
  // Sessions live on the host filesystem (useHostFilesystem maps it at /), so
  // they survive across PHP requests and are easy to inspect while debugging.
  'session.save_path': sessionDir,
  'session.gc_maxlifetime': '86400',
  'session.cookie_httponly': '1',
  // The application never sees session cookies: tools/security/guard.php owns
  // the cookie (the runtime otherwise emits a second, stale Set-Cookie).
  'session.use_cookies': '0',
  'session.use_only_cookies': '0',
  'session.use_strict_mode': '0',
  'session.cookie_samesite': 'Lax',
  // cwd per script + admin auth guard + session rotation (tools/security/guard.php).
  auto_prepend_file: prependFile,
});

/**
 * PHP-WASM is one runtime: two PHP requests in flight at the same time crash it
 * and both answer "Internal Server Error". A browser happily requests a page,
 * an AJAX quick-view and a cart count at once, so PHP work is queued while
 * static files (images, CSS, JS) keep streaming in parallel.
 */
let phpQueue = Promise.resolve();
function withPhpLock(task) {
  const run = phpQueue.then(task, task);
  phpQueue = run.then(() => undefined, () => undefined);
  return run;
}

/**
 * PHP-WASM keeps one long-lived PHP process, and that process leaks state
 * between requests: a session started by the previous visitor stays active, the
 * previous request's Cookie header stays in $_SERVER, and the session-id
 * generator happily returns the same id twice. Guarding all of that from PHP
 * proved unreliable (see tools/security/guard.php for what is still done), so
 * the runtime is recreated for every request — the same guarantee Apache gives
 * with a clean process per request. Set PHP_MAX_REQUESTS=25 to trade isolation
 * for speed.
 */
php.enableRuntimeRotation({
  maxRequests: Number(process.env.PHP_MAX_REQUESTS || 1),
  recreateRuntime: () => bootRuntime(),
});

const handler = new PHPRequestHandler({
  php,
  documentRoot: docRoot,
  absoluteUrl: `http://localhost:${port}`,
  rewriteRules: [],
  getFileNotFoundAction: () => ({ type: '404' }),
});

/**
 * The browser's real origin. Behind the preview proxy the request arrives on
 * 0.0.0.0:3000 with the public host in x-forwarded-*; the application builds
 * absolute URLs from $_SERVER['HTTP_HOST'] and its scheme from $_SERVER['HTTPS']
 * / REQUEST_SCHEME, so handing it the public origin is what keeps images, CSS
 * and links from pointing at http://127.0.0.1 (mixed content → broken images).
 */
function requestOrigin(req) {
  const first = (value) => String(value || '').split(',')[0].trim();
  const host = first(req.headers['x-forwarded-host']) || req.headers.host || `localhost:${port}`;
  const isLocalHost = /^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/.test(host);
  const scheme = first(req.headers['x-forwarded-proto']) || (isLocalHost ? 'http' : 'https');
  return { scheme, host };
}

/**
 * A handful of templates hardcode the production domain (footer/header links,
 * some legal pages). Served locally those links would jump to the live site, so
 * rewrite them to the origin the browser is actually on. Mail addresses are left
 * alone because only real URLs (host followed by "/") are matched.
 */
function rewriteAbsoluteUrls(html, origin) {
  const { scheme, host } = origin;
  return html
    .replace(/(?<![@\w.-])https?:\/\/(?:www\.)?woodexfurniture\.pk(?=\/)/gi, `${scheme}://${host}`)
    .replace(/(?<![@\w.-])https?:\/\/(?:localhost|127\.0\.0\.1):\d+(?=\/)/gi, `${scheme}://${host}`);
}

/**
 * Session identity is decided HERE, not by PHP.
 *
 * The PHP-WASM runtime reuses one PHP process, and its session module leaks
 * state between requests: the previous visitor's session stays active, the id
 * generator returns the same id twice, and writes land in the wrong file. The
 * guard (tools/security/guard.php) therefore reads a session id from this
 * header and writes the session data to the file for that id, while the runner
 * owns the cookie. One visitor, one id, always.
 */
const SESSION_COOKIE = 'WDXSESSID';

function sessionIdFromRequest(req, res) {
  const cookieHeader = String(req.headers.cookie || '');
  const match = cookieHeader.match(new RegExp(`(?:^|;\\s*)${SESSION_COOKIE}=([A-Fa-f0-9]{16,128})`));
  const sid = match ? match[1].toLowerCase() : randomBytes(16).toString('hex');
  res.setHeader('Set-Cookie', `${SESSION_COOKIE}=${sid}; Path=/; HttpOnly; SameSite=Lax`);
  return sid;
}

/**
 * Files that must never be served. The delivered web root contains an
 * `error_log` (a MySQL access-denied trace), `connection.php.orig` — a backup of
 * the connection file with the live database password in it — `php.ini`,
 * `.user.ini`, a `database/*.sql` schema dump and 239 session files under
 * `sessions/`. On the cPanel host Apache serves all of them (see
 * tools/security/README.md for the .htaccess that fixes that).
 */
const BLOCKED_FILE_RX = /(^|\/)(error_log|php_errors?\.log|\.env[^/]*|\.user\.ini|php\.ini|\.htaccess|\.htpasswd|composer\.(json|lock)|web\.config|connection\.php|connection\.local\.php|auth_guard\.php|login_throttle\.php|session\.php|wdx_mysqli\.php)$/i;
const BLOCKED_EXT_RX = /\.(log|orig|bak|backup|sql|sqlite|db|ini|zip|tar|gz|tgz|env|sh|py|mjs|yml|yaml)$/i;

function isBlockedPath(pathname) {
  const path = decodeURIComponent(pathname);
  if (path.includes('/../')) return true;
  if (path.startsWith('/sessions/')) return true;
  if (BLOCKED_FILE_RX.test(path) || BLOCKED_EXT_RX.test(path)) return true;
  return path.split('/').some((segment) => segment.startsWith('.') && segment !== '' && segment !== '..');
}

/** Missing images get a neutral placeholder instead of a broken-image icon. */
const IMAGE_EXTENSIONS = new Set(['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg']);

/**
 * Files the application asks for that were never uploaded anywhere — every one
 * of these answers 404 on the live site too (see docs/media-audit.md). Where an
 * existing photo has the same subject it is substituted; everything else gets
 * the placeholder.
 */
const MISSING_IMAGE_ALIASES = new Map([
  ['/assets/images/installation.jpg', '/assets/images/services/delivery-installation.jpg'],
]);

function placeholderImage(label) {
  const text = String(label || 'Woodex').replace(/[<>&"]/g, '').slice(0, 48);
  return Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400" viewBox="0 0 600 400">
  <rect width="600" height="400" fill="#eef1f5"/>
  <rect x="0.5" y="0.5" width="599" height="399" fill="none" stroke="#d6dbe3"/>
  <text x="300" y="200" font-family="Arial, Helvetica, sans-serif" font-size="22" fill="#8a94a6" text-anchor="middle">${text}</text>
  <text x="300" y="232" font-family="Arial, Helvetica, sans-serif" font-size="14" fill="#aab2c0" text-anchor="middle">photo not available</text>
</svg>`);
}

/**
 * Login throttling. The application has none: an attacker can try passwords as
 * fast as the server answers. Failures are counted per client IP; after
 * LOGIN_FAIL_LIMIT wrong passwords inside LOGIN_WINDOW_MS the form is refused
 * with 429 until the window passes.
 */
const LOGIN_FAIL_LIMIT = Number(process.env.LOGIN_FAIL_LIMIT || 6);
const LOGIN_WINDOW_MS = Number(process.env.LOGIN_WINDOW_MS || 15 * 60 * 1000);
const loginFailures = new Map();

function clientIp(req) {
  const forwarded = String(req.headers['x-forwarded-for'] || '').split(',')[0].trim();
  return forwarded || req.socket?.remoteAddress || 'unknown';
}

function loginLockRemaining(ip) {
  const entry = loginFailures.get(ip);
  if (!entry) return 0;
  const age = Date.now() - entry.first;
  if (age > LOGIN_WINDOW_MS) {
    loginFailures.delete(ip);
    return 0;
  }
  if (entry.count < LOGIN_FAIL_LIMIT) return 0;
  return Math.ceil((LOGIN_WINDOW_MS - age) / 1000);
}

function recordLogin(ip, succeeded) {
  if (succeeded) {
    loginFailures.delete(ip);
    return;
  }
  const entry = loginFailures.get(ip);
  if (!entry || Date.now() - entry.first > LOGIN_WINDOW_MS) {
    loginFailures.set(ip, { count: 1, first: Date.now() });
  } else {
    entry.count += 1;
  }
}

const server = createServer(async (req, res) => {
  const started = Date.now();
  try {
    const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
    const origin = requestOrigin(req);

    if (url.pathname === '/sitemap.xml') {
      const xml = Buffer.from(sitemapXml(`${origin.scheme}://${origin.host}`), 'utf8');
      res.writeHead(200, { 'Content-Type': 'application/xml; charset=utf-8', 'Content-Length': xml.length });
      res.end(xml);
      return;
    }

    if (isBlockedPath(url.pathname)) {
      res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      res.end('Not Found');
      return;
    }

    const alias = LINK_ALIASES.get(url.pathname.replace(/\/+$/, ''));
    if (alias) {
      res.writeHead(302, { Location: `${origin.scheme}://${origin.host}${alias}` });
      res.end();
      return;
    }

    const imageAlias = MISSING_IMAGE_ALIASES.get(url.pathname);
    if (imageAlias && existsSync(join(docRoot, normalize(imageAlias)))) {
      res.writeHead(302, { Location: imageAlias, 'Cache-Control': 'no-store' });
      res.end();
      return;
    }

    const target = resolveTarget(url.pathname);

    if (!target) {
      const missingExt = extname(url.pathname).toLowerCase();
      if (IMAGE_EXTENSIONS.has(missingExt)) {
        const label = decodeURIComponent(url.pathname).split('/').pop().replace(/[-_]/g, ' ');
        const svg = placeholderImage(label);
        res.writeHead(200, { 'Content-Type': 'image/svg+xml', 'Content-Length': svg.length, 'Cache-Control': 'public, max-age=300' });
        res.end(svg);
        return;
      }
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
    // PHP-WASM keeps the superglobals of the previous request alive; a header
    // that is absent now (Cookie, X-Requested-With) still reads as present.
    // Mark the absent ones so tools/security/guard.php can clear them.
    headers['x-wdx-cookie'] = req.headers.cookie ? 'present' : 'none';
    headers['x-wdx-ajax'] = req.headers['x-requested-with'] ? 'present' : 'none';
    headers['x-wdx-sid'] = sessionIdFromRequest(req, res);

    const isLoginPost = target.url === '/lbpanel/index.php' && req.method === 'POST';
    const ip = clientIp(req);
    if (isLoginPost) {
      const wait = loginLockRemaining(ip);
      if (wait > 0) {
        appendFileSync(errorLog, `[${new Date().toISOString()}] login throttled for ${ip} (${wait}s)\n`);
        res.writeHead(429, {
          'Content-Type': 'text/html; charset=utf-8',
          'Retry-After': String(wait),
        });
        res.end(`<!doctype html><meta charset="utf-8"><title>Too many attempts</title>`
          + `<div style="font-family:Arial,sans-serif;max-width:520px;margin:80px auto;text-align:center">`
          + `<h2>Too many login attempts</h2><p>Please wait ${Math.ceil(wait / 60)} minute(s) and try again.</p>`
          + `<p><a href="/lbpanel/index.php">Back to the login form</a></p></div>`);
        return;
      }
    }

    const query = [target.extraQuery, url.search.replace(/^\?/, '')].filter(Boolean).join('&');
    const response = await withPhpLock(() => handler.request({
      url: `${origin.scheme}://${origin.host}${target.url}${query ? `?${query}` : ''}`,
      method: req.method,
      headers,
      body,
    }));

    if (isLoginPost) {
      const saidNo = Buffer.from(response.bytes).subarray(0, 20000).toString('utf8').includes('Invalid username or password');
      recordLogin(ip, !saidNo);
    }

    const notFound = response.httpStatusCode === 404;
    const isSlugRoute = /^\/(categories|blog|projects|shop)\/[^/]+$/.test(url.pathname);
    if (notFound && isSlugRoute) {
      const listing = url.pathname.startsWith('/blog') ? '/blog'
        : url.pathname.startsWith('/projects') ? '/projects'
        : url.pathname.startsWith('/categories') ? '/shop'
        : '/shop';
      res.writeHead(302, { Location: `${origin.scheme}://${origin.host}${listing}` });
      res.end();
      return;
    }

    if (response.errors) {
      appendFileSync(errorLog, `[${new Date().toISOString()}] ${target.url}\n${response.errors}\n\n`);
    }

    const outHeaders = { ...response.headers };
    // The runner owns the session cookie (see SESSION_COOKIE above).
    for (const key of Object.keys(outHeaders)) {
      if (key.toLowerCase() === 'set-cookie') delete outHeaders[key];
    }
    const rotatedSid = String(outHeaders['x-wdx-session-id'] || outHeaders['X-WDX-Session-Id'] || '');
    delete outHeaders['x-wdx-session-id'];
    delete outHeaders['X-WDX-Session-Id'];
    if (/^[A-Fa-f0-9]{16,128}$/.test(rotatedSid)) {
      res.setHeader('Set-Cookie', `${SESSION_COOKIE}=${rotatedSid.toLowerCase()}; Path=/; HttpOnly; SameSite=Lax`);
    }
    let payload = Buffer.from(response.bytes);

    const contentType = String(outHeaders['content-type'] || outHeaders['Content-Type'] || '');
    if (contentType.includes('text/html')) {
      const rewritten = rewriteAbsoluteUrls(payload.toString('utf8'), origin);
      if (rewritten !== payload.toString('utf8')) {
        payload = Buffer.from(rewritten, 'utf8');
        outHeaders['content-length'] = String(payload.length);
      }
    }

    // PHP sets its own Location; keep status as returned (302 etc).
    res.writeHead(response.httpStatusCode || 200, outHeaders);
    res.end(payload);

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
