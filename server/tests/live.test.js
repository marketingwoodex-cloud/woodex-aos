/**
 * Live-database tests — build a throwaway "live" SQLite file from a fixture
 * snapshot with the real loader, then exercise the storefront and admin APIs
 * against it. Nothing here touches server/data/woodex-live.db or your dump.
 *
 *   npm test
 */
import { after, before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '..', '..');

const tempDir = mkdtempSync(join(tmpdir(), 'woodex-live-test-'));
const liveDbPath = join(tempDir, 'live.db');

process.env.DATA_DIR = tempDir;
process.env.DB_PATH = join(tempDir, 'test.db');
process.env.AUTO_SEED = 'false';
process.env.JWT_SECRET = 'test-secret';
process.env.LIVE_DB_PATH = liveDbPath;

// Build the fixture database with the same loader the real pipeline uses.
execFileSync(process.execPath, [join(root, 'tools', 'db-import', 'load.mjs')], {
  env: {
    ...process.env,
    SNAPSHOT_PATH: join(here, 'fixtures', 'live-snapshot.json'),
    LIVE_DB_PATH: liveDbPath,
  },
  stdio: 'ignore',
});

const { default: app } = await import('../src/app.js');
const { seedDatabase } = await import('../src/db/seed.js');

let server;
let baseUrl;

function client() {
  let cookie = '';
  return async function request(path, { method = 'GET', body, as } = {}) {
    if (as === null) cookie = '';
    const headers = {};
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (cookie) headers.Cookie = cookie;
    const response = await fetch(`${baseUrl}${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    for (const value of response.headers.getSetCookie?.() ?? []) {
      if (value.startsWith('woodex_session=')) cookie = value.split(';')[0];
    }
    const text = await response.text();
    let json = null;
    try {
      json = text ? JSON.parse(text) : null;
    } catch {
      json = { raw: text };
    }
    return { status: response.status, body: json };
  };
}

const request = client();

before(async () => {
  seedDatabase({ reset: true });
  server = app.listen(0, '127.0.0.1');
  await new Promise((resolve) => server.once('listening', resolve));
  baseUrl = `http://127.0.0.1:${server.address().port}`;
});

after(async () => {
  await new Promise((resolve) => server.close(resolve));
  rmSync(tempDir, { recursive: true, force: true });
});

describe('storefront API', () => {
  it('reports the imported snapshot metadata', async () => {
    const { status, body } = await request('/api/shop/meta');
    assert.equal(status, 200);
    assert.equal(body.available, true);
    assert.equal(body.database, 'u128159657_woodexbaks_fixture');
    assert.equal(body.counts.products, 3);
  });

  it('serves the home payload with hero, categories and shelves', async () => {
    const { status, body } = await request('/api/shop/home');
    assert.equal(status, 200);
    assert.equal(body.hero[0].kicker, 'Fixture Series');
    assert.equal(body.categories[0].slug, 'office-furniture');
    assert.ok(body.shelves.bestSellers.length >= 1);
    assert.equal(body.totals.products, 2, 'one fixture product is hidden');
  });

  it('filters products by category subtree and flags', async () => {
    const { body } = await request('/api/shop/products?category=office-furniture');
    assert.equal(body.meta.total, 2, 'root category covers both products');
    const sale = await request('/api/shop/products?flag=sale');
    assert.equal(sale.body.meta.total, 1);
    const out = await request('/api/shop/products?flag=instock');
    assert.equal(out.body.meta.total, 1);
  });

  it('hides inactive products from the catalogue and detail route', async () => {
    const list = await request('/api/shop/products?limit=50');
    assert.ok(!list.body.data.some((item) => item.slug === 'retired-chair'));
    const detail = await request('/api/shop/products/retired-chair');
    assert.equal(detail.status, 404);
  });

  it('serves a product detail with breadcrumb, specs and FAQs', async () => {
    const { status, body } = await request('/api/shop/products/ambassador-executive-chair');
    assert.equal(status, 200);
    assert.equal(body.data.sku, 'EC-10001');
    assert.equal(body.data.discountPercent, 14);
    assert.deepEqual(body.data.breadcrumb.map((crumb) => crumb.slug), ['office-furniture', 'executive-chairs']);
    assert.equal(body.data.specifications.length, 1);
    assert.equal(body.data.gallery.length, 1);
    assert.ok(body.data.gallery[0].url.endsWith('assets/images/products/chair.jpg'));
    assert.equal(body.faqs.length, 1);
    assert.equal(body.categoryFaqs.length, 1, 'category FAQ inherited from the ancestor category');
    assert.ok(Array.isArray(body.related));
  });

  it('lists category FAQs for the storefront accordion', async () => {
    const { status, body } = await request('/api/shop/faqs?limit=5');
    assert.equal(status, 200);
    assert.equal(body.data.length, 1);
    assert.equal(body.data[0].category_slug, 'office-furniture');
    const scoped = await request('/api/shop/faqs?category=executive-chairs');
    assert.equal(scoped.body.data.length, 1, 'descendant category inherits the ancestor FAQ');
  });

  it('returns the imported footer content', async () => {
    const { body } = await request('/api/shop/content');
    assert.equal(body.footer.tagline, 'Make your space work®');
    assert.equal(body.footer.phone, '+92 322 4000 768');
    assert.equal(body.blogs[0].slug, 'choosing-an-office-chair');
  });
});

describe('live admin API', () => {
  it('requires a session', async () => {
    const { status } = await request('/api/live/overview', { as: null });
    assert.equal(status, 401);
  });

  it('summarises the imported database', async () => {
    const login = await request('/api/auth/login', {
      method: 'POST',
      body: { email: 'admin@woodex.pk', password: 'Woodex@2026' },
    });
    assert.equal(login.status, 200);
    const { status, body } = await request('/api/live/overview');
    assert.equal(status, 200);
    assert.equal(body.totals.products, 3);
    assert.equal(body.totals.out_of_stock, 2);
    assert.equal(body.totals.zero_price, 1);
    assert.equal(body.totals.orders, 1);
    assert.equal(body.totals.quotations, 1);
    assert.equal(body.totals.orphan_quote_lines, 1);
  });

  it('lists and filters products', async () => {
    const all = await request('/api/live/products?limit=50');
    assert.equal(all.body.meta.total, 3);
    const hidden = await request('/api/live/products?filter=hidden');
    assert.equal(hidden.body.meta.total, 1);
    const search = await request('/api/live/products?search=summit');
    assert.equal(search.body.data[0].slug, 'summit-workstation');
  });

  it('patches price, stock and flags', async () => {
    const { status, body } = await request('/api/live/products/35', {
      method: 'PATCH',
      body: { price: 64000, stock: 3, isBestSeller: true },
    });
    assert.equal(status, 200);
    assert.equal(body.data.price, 64000);
    assert.equal(body.data.stock, 3);
    assert.equal(body.data.isBestSeller, true);

    const reread = await request('/api/live/products?search=summit');
    assert.equal(reread.body.data[0].price, 64000);
  });

  it('rejects invalid writes', async () => {
    const { status } = await request('/api/live/products/35', { method: 'PATCH', body: { price: -5 } });
    assert.equal(status, 400);
    const missing = await request('/api/live/products/9999', { method: 'PATCH', body: { stock: 1 } });
    assert.equal(missing.status, 404);
  });

  it('serves orders and quotations with their line items', async () => {
    const orders = await request('/api/live/orders');
    assert.equal(orders.body.meta.total, 1);
    assert.equal(orders.body.data[0].order_number, 'ORDER-FIXTURE-1');
    const order = await request(`/api/live/orders/${orders.body.data[0].id}`);
    assert.equal(order.body.data.items.length, 1);
    assert.equal(order.body.data.items[0].subtotal, 164000);

    const quotes = await request('/api/live/quotations');
    assert.equal(quotes.body.meta.total, 1);
    const quote = await request(`/api/live/quotations/${quotes.body.data[0].id}`);
    assert.equal(quote.body.data.items.length, 2);
    assert.equal(quote.body.data.items.filter((item) => item.product_missing).length, 1);
  });

  it('blocks writes for the viewer role', async () => {
    await request('/api/auth/login', {
      method: 'POST',
      body: { email: 'viewer@woodex.pk', password: 'Viewer@2026' },
    });
    const read = await request('/api/live/products');
    assert.equal(read.status, 200);
    const write = await request('/api/live/products/35', { method: 'PATCH', body: { stock: 9 } });
    assert.equal(write.status, 403);
  });
});

describe('storefront without an import', () => {
  it('degrades gracefully when the live database is missing', async () => {
    const { existsSync, renameSync } = await import('node:fs');
    const { livePath } = { livePath: liveDbPath };
    assert.ok(existsSync(livePath));
    renameSync(livePath, `${livePath}.bak`);
    try {
      const meta = await request('/api/shop/meta');
      assert.equal(meta.body.available, false);
      const list = await request('/api/shop/products');
      assert.equal(list.body.meta.total, 0);
      const home = await request('/api/shop/home');
      assert.equal(home.status, 200);
      assert.equal(home.body.available, false);
    } finally {
      renameSync(`${livePath}.bak`, livePath);
    }
  });
});
