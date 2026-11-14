/**
 * UI smoke test — mounts the real React bundle in jsdom and checks that every
 * route renders with live data, that the login form works end to end, and that
 * each role sees the right controls.
 *
 * Requires a running server:  npm start   (then, in another shell)  npm run test:ui
 */
import { spawnSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

// jsdom is installed inside the client workspace, so resolve it from there.
const requireFromClient = createRequire(join(root, 'client', 'package.json'));
const { JSDOM, VirtualConsole } = requireFromClient('jsdom');
const BASE = process.env.SMOKE_BASE || `http://127.0.0.1:${process.env.PORT || 3000}`;
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const failures = [];
let checks = 0;

function check(name, condition, detail = '') {
  checks += 1;
  if (!condition) failures.push(`${name}${detail ? ` — ${detail}` : ''}`);
  console.log(`${condition ? '✓' : '✗'} ${name}`);
}

/* --------------------------------------------------------------- build bundle */
const tempDir = join(root, '.tmp');
mkdirSync(tempDir, { recursive: true });
const bundlePath = join(tempDir, 'ui-smoke.js');

const build = spawnSync(
  join(root, 'client', 'node_modules', '.bin', 'esbuild'),
  [
    join(root, 'client', 'src', 'main.jsx'),
    '--bundle',
    '--format=iife',
    `--outfile=${bundlePath}`,
    '--loader:.css=empty',
    '--jsx=automatic',
    '--define:process.env.NODE_ENV="development"',
    '--log-level=warning',
  ],
  { cwd: join(root, 'client'), encoding: 'utf8' },
);

if (build.status !== 0) {
  console.error('Could not bundle the client:\n', build.stderr || build.stdout);
  process.exit(1);
}
const bundle = readFileSync(bundlePath, 'utf8');

/* ------------------------------------------------------------------ helpers */
async function serverIsUp() {
  try {
    const response = await fetch(`${BASE}/api/health`);
    return response.ok;
  } catch {
    return false;
  }
}

async function sessionFor(email, password) {
  const response = await fetch(`${BASE}/api/auth/login`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  if (!response.ok) return { cookie: '' };
  return { cookie: response.headers.getSetCookie()[0].split(';')[0] };
}

function makeDom(jar, path) {
  const html = `<!doctype html><html data-theme="light"><head><meta charset="utf-8"></head>
    <body><div id="root"></div><script>${bundle}</script></body></html>`;

  const errors = [];
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', (error) => errors.push(error.message));
  virtualConsole.on('error', (...args) => errors.push(args.join(' ')));

  const dom = new JSDOM(html, {
    url: `${BASE}${path}`,
    runScripts: 'dangerously',
    pretendToBeVisual: true,
    virtualConsole,
    beforeParse(window) {
      window.matchMedia = () => ({
        matches: false, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {},
      });
      window.fetch = async (input, init = {}) => {
        const url = new URL(typeof input === 'string' ? input : input.url, BASE).toString();
        const headers = { ...(init.headers || {}) };
        if (jar.cookie) headers.cookie = jar.cookie;
        const response = await fetch(url, { ...init, headers });
        for (const value of response.headers.getSetCookie?.() ?? []) {
          if (value.startsWith('woodex_session=')) jar.cookie = value.split(';')[0];
        }
        return response;
      };
    },
  });

  const text = () => dom.window.document.getElementById('root')?.textContent ?? '';
  return { dom, errors, text };
}

function typeInto(window, input, value) {
  const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
  setter.call(input, value);
  input.dispatchEvent(new window.Event('input', { bubbles: true }));
}

function clickText(window, label) {
  const button = [...window.document.querySelectorAll('button')].find((item) => item.textContent.trim() === label);
  button?.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  return Boolean(button);
}

function navigate(dom, path) {
  dom.window.history.pushState({}, '', path);
  dom.window.dispatchEvent(new dom.window.PopStateEvent('popstate'));
}

/* --------------------------------------------------------------------- run */
if (!(await serverIsUp())) {
  console.error(`The API is not answering on ${BASE}. Start it with "npm start" first.`);
  process.exit(1);
}

console.log('\nLogin page');
{
  const { dom, errors, text } = makeDom({ cookie: '' }, '/login');
  await sleep(700);
  check('renders the sign-in form', text().includes('Welcome back'));
  check('lists the demo accounts', text().includes('admin@woodex.pk'));
  check('no console errors', errors.length === 0, errors.join(' | '));
  dom.window.close();
}

console.log('\nForm login');
{
  const jar = { cookie: '' };
  const { dom, text, errors } = makeDom(jar, '/login');
  await sleep(700);
  const { window } = dom;
  const inputs = [...window.document.querySelectorAll('input')];
  typeInto(window, inputs.find((input) => input.type === 'email'), 'admin@woodex.pk');
  typeInto(window, inputs.find((input) => input.type === 'password'), 'Woodex@2026');
  await sleep(100);
  clickText(window, 'Sign in');
  await sleep(1600);
  check('stores the session cookie', jar.cookie.startsWith('woodex_session='));
  check('lands on the dashboard', text().includes('Revenue trend'));
  check('no console errors', errors.length === 0, errors.join(' | '));
  dom.window.close();
}

console.log('\nRejects a bad password');
{
  const { dom, text } = makeDom({ cookie: '' }, '/login');
  await sleep(600);
  const { window } = dom;
  const inputs = [...window.document.querySelectorAll('input')];
  typeInto(window, inputs.find((input) => input.type === 'email'), 'admin@woodex.pk');
  typeInto(window, inputs.find((input) => input.type === 'password'), 'wrong-password');
  await sleep(100);
  clickText(window, 'Sign in');
  await sleep(1100);
  check('shows an inline error', text().includes('Incorrect email or password'));
  dom.window.close();
}

console.log('\nRoutes (admin)');
{
  const jar = await sessionFor('admin@woodex.pk', 'Woodex@2026');
  const { dom, errors, text } = makeDom(jar, '/dashboard');
  await sleep(1300);

  const routes = [
    ['/dashboard', 'Revenue trend'],
    ['/products', 'Product catalogue'],
    ['/orders', 'Order book'],
    ['/customers', 'Customers & trade partners'],
    ['/projects', 'Projects & integrations'],
    ['/projects/1', 'Deployment history'],
    ['/users', 'Team & access'],
    ['/activity', 'Activity & audit trail'],
    ['/settings', 'Organisation profile'],
    ['/account', 'Change password'],
    ['/live', 'Imported catalogue & commerce'],
    ['/live/catalogue', 'The real product table'],
    ['/live/orders', 'customer details, line items'],
    ['/live/quotations', 'Quotation requests'],
  ];

  for (const [path, marker] of routes) {
    const before = errors.length;
    navigate(dom, path);
    await sleep(850);
    check(`${path} renders`, text().includes(marker) && errors.length === before, errors.slice(before).join(' | '));
  }

  navigate(dom, '/orders');
  await sleep(700);
  check('opens the new-order modal', clickText(dom.window, 'New order'));
  await sleep(500);
  check('modal shows the line-item builder', text().includes('Line items') && text().includes('Sales tax'));

  const toggle = [...dom.window.document.querySelectorAll('button')].find(
    (button) => button.getAttribute('aria-label') === 'Toggle colour theme',
  );
  toggle?.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true }));
  await sleep(200);
  check('theme toggle switches to dark', dom.window.document.documentElement.dataset.theme === 'dark');
  dom.window.close();
}

console.log('\nRole visibility');
{
  const viewerJar = await sessionFor('viewer@woodex.pk', 'Viewer@2026');
  const { dom, errors, text } = makeDom(viewerJar, '/products');
  await sleep(1400);
  check(
    'viewer sees catalogue data',
    text().includes('Product catalogue') && dom.window.document.querySelectorAll('tbody tr').length > 0,
  );
  check('viewer has no create button', !text().includes('Add product'));
  check('viewer has no row actions', dom.window.document.querySelectorAll('[aria-label="Delete product"]').length === 0);
  navigate(dom, '/users');
  await sleep(600);
  check('viewer is blocked from team management', text().includes('Administrators only'));
  const navLabels = [...dom.window.document.querySelectorAll('.nav-item')].map((item) => item.textContent.trim());
  check('viewer nav hides Team', !navLabels.includes('Team'));
  check('no console errors', errors.length === 0, errors.join(' | '));
  dom.window.close();
}

console.log('\nLive database pages');
{
  const jar = await sessionFor('admin@woodex.pk', 'Woodex@2026');
  const { dom, text, errors } = makeDom(jar, '/live/catalogue');
  await sleep(1600);
  const rows = dom.window.document.querySelectorAll('tbody tr').length;
  check('live catalogue lists imported products', rows >= 8, `rows=${rows}`);
  check('live catalogue shows real PKR prices', /(PKR|Rs)\s?[\d,]{4,}/.test(text()));
  check('live catalogue flags stock-outs', text().includes('Out of stock'));
  navigate(dom, '/live/orders');
  await sleep(1000);
  check('live orders opens an imported order', dom.window.document.querySelectorAll('tbody tr').length === 5);
  dom.window.document.querySelector('tbody tr')?.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true }));
  await sleep(900);
  check(
    'order drawer shows line items',
    text().includes('Line items') && text().includes('ORDER-'),
  );
  navigate(dom, '/live/quotations');
  await sleep(1000);
  check(
    'live quotations lists the pipeline',
    dom.window.document.querySelectorAll('tbody tr').length === 10 && text().includes('11 quotations')
      || dom.window.document.querySelectorAll('tbody tr').length >= 10,
  );
  const navLabels = [...dom.window.document.querySelectorAll('.nav-item')].map((item) => item.textContent.trim());
  check('sidebar exposes the live database group', navLabels.includes('Live catalogue') && navLabels.includes('Storefront'));
  check('no console errors', errors.length === 0, errors.join(' | '));
  dom.window.close();
}

console.log('\nStorefront (public, no session)');
{
  const { dom, text, errors } = makeDom({ cookie: '' }, '/shop');
  await sleep(1700);
  const cards = dom.window.document.querySelectorAll('.product-card');
  check('storefront renders without a session', text().includes('Best sellers'));
  check('storefront shows catalogue cards', cards.length >= 4, `cards=${cards.length}`);
  check('storefront prices in PKR', /(PKR|Rs)\s?[\d,]{4,}/.test(text()));
  check('storefront footer carries the imported details', text().includes('+92 322 4000 768'));
  check('storefront surfaces the showroom FAQs', text().includes('Answers before you ask'));

  const href = cards[0]?.getAttribute('href');
  check('cards link to product pages', Boolean(href && href.startsWith('/shop/p/')), String(href));
  navigate(dom, href);
  await sleep(1500);
  check('product page renders specs and SKU', text().includes('Specifications') && text().includes('SKU'));
  check('product page shows the imported breadcrumb', text().includes('Office Furniture'));

  navigate(dom, '/shop?flag=sale');
  await sleep(1300);
  check('sale filter returns products', dom.window.document.querySelectorAll('.product-card').length > 0);

  navigate(dom, '/shop?q=chair');
  await sleep(1300);
  check('search returns chair results', dom.window.document.querySelectorAll('.product-card').length > 0);
  check('no console errors', errors.length === 0, errors.join(' | '));
  dom.window.close();
}

{
  const managerJar = await sessionFor('manager@woodex.pk', 'Manager@2026');
  const { dom, text } = makeDom(managerJar, '/products');
  await sleep(1400);
  check('manager can create products', text().includes('Add product'));
  const navLabels = [...dom.window.document.querySelectorAll('.nav-item')].map((item) => item.textContent.trim());
  check('manager nav hides Team', !navLabels.includes('Team'));
  dom.window.close();
}

console.log(`\n${checks - failures.length}/${checks} UI checks passed`);
if (failures.length) {
  console.log('\nFailures:');
  for (const failure of failures) console.log(' -', failure);
  process.exit(1);
}
