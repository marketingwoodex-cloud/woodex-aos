/**
 * API integration tests — boot the real Express app against a throwaway
 * database, then exercise auth, RBAC and the commerce endpoints.
 *
 *   npm test
 */
import { after, before, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const tempDir = mkdtempSync(join(tmpdir(), 'woodex-test-'));
process.env.DATA_DIR = tempDir;
process.env.DB_PATH = join(tempDir, 'test.db');
process.env.AUTO_SEED = 'false';
process.env.JWT_SECRET = 'test-secret';

const { default: app } = await import('../src/app.js');
const { seedDatabase } = await import('../src/db/seed.js');

let server;
let baseUrl;

/** Minimal cookie-aware fetch helper. */
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
    const setCookie = response.headers.getSetCookie?.() ?? [];
    for (const value of setCookie) {
      if (value.startsWith('woodex_session=')) cookie = value.split(';')[0];
    }
    const text = await response.text();
    let json = null;
    if (text) {
      try {
        json = JSON.parse(text);
      } catch {
        json = { raw: text };
      }
    }
    return { status: response.status, body: json, cookie };
  };
}

before(async () => {
  seedDatabase({ reset: true });
  server = app.listen(0, '127.0.0.1');
  await new Promise((resolve) => server.once('listening', resolve));
  baseUrl = `http://127.0.0.1:${server.address().port}`;
});

after(() => {
  server?.close();
  try {
    rmSync(tempDir, { recursive: true, force: true });
  } catch {
    /* best effort */
  }
});

describe('health & auth', () => {
  it('reports health without a session', async () => {
    const request = client();
    const response = await request('/api/health');
    assert.equal(response.status, 200);
    assert.equal(response.body.status, 'ok');
  });

  it('rejects unauthenticated dashboard access', async () => {
    const request = client();
    const response = await request('/api/dashboard');
    assert.equal(response.status, 401);
  });

  it('rejects a wrong password', async () => {
    const request = client();
    const response = await request('/api/auth/login', {
      method: 'POST',
      body: { email: 'admin@woodex.pk', password: 'nope' },
    });
    assert.equal(response.status, 401);
  });

  it('validates missing credentials', async () => {
    const request = client();
    const response = await request('/api/auth/login', { method: 'POST', body: { email: 'admin@woodex.pk' } });
    assert.equal(response.status, 400);
  });

  it('signs an admin in and returns their profile', async () => {
    const request = client();
    const login = await request('/api/auth/login', {
      method: 'POST',
      body: { email: 'admin@woodex.pk', password: 'Woodex@2026' },
    });
    assert.equal(login.status, 200);
    assert.equal(login.body.user.role, 'admin');
    assert.ok(login.body.token);
    assert.equal(login.body.user.password_hash, undefined);

    const me = await request('/api/auth/me');
    assert.equal(me.status, 200);
    assert.equal(me.body.user.email, 'admin@woodex.pk');
  });

  it('signs out and clears the session', async () => {
    const request = client();
    await request('/api/auth/login', { method: 'POST', body: { email: 'viewer@woodex.pk', password: 'Viewer@2026' } });
    const out = await request('/api/auth/logout', { method: 'POST' });
    assert.equal(out.status, 200);
    const me = await request('/api/auth/me');
    assert.equal(me.body.user, null);
  });
});

describe('commerce', () => {
  let request;

  before(async () => {
    request = client();
    await request('/api/auth/login', { method: 'POST', body: { email: 'manager@woodex.pk', password: 'Manager@2026' } });
  });

  it('lists products with pagination metadata', async () => {
    const response = await request('/api/products?limit=5&page=1');
    assert.equal(response.status, 200);
    assert.ok(response.body.data.length <= 5);
    assert.equal(response.body.meta.page, 1);
    assert.ok(response.body.meta.total >= 25);
  });

  it('filters products by search term', async () => {
    const response = await request('/api/products?search=Alder');
    assert.equal(response.status, 200);
    assert.ok(response.body.data.some((product) => product.name.includes('Alder')));
  });

  it('creates, updates and deletes a product', async () => {
    const created = await request('/api/products', {
      method: 'POST',
      body: { name: 'Test Bench', sku: 'WD-TST-001', price: 15000, stock: 3, categoryId: 1, reorderLevel: 4 },
    });
    assert.equal(created.status, 201);
    const id = created.body.data.id;
    assert.equal(created.body.data.sku, 'WD-TST-001');

    const updated = await request(`/api/products/${id}`, { method: 'PATCH', body: { price: 17500 } });
    assert.equal(updated.status, 200);
    assert.equal(updated.body.data.price, 17500);

    const removed = await request(`/api/products/${id}`, { method: 'DELETE' });
    assert.equal(removed.status, 200);

    const missing = await request(`/api/products/${id}`);
    assert.equal(missing.status, 404);
  });

  it('rejects duplicate SKUs', async () => {
    const response = await request('/api/products', {
      method: 'POST',
      body: { name: 'Duplicate', sku: 'WD-SOF-101', price: 1000 },
    });
    assert.equal(response.status, 409);
  });

  it('builds an order and decrements stock', async () => {
    const products = await request('/api/products?limit=1');
    const product = products.body.data[0];
    const stockBefore = product.stock;

    const customers = await request('/api/customers?limit=1');
    const customer = customers.body.data[0];

    const created = await request('/api/orders', {
      method: 'POST',
      body: {
        customerId: customer.id,
        channel: 'showroom',
        items: [{ productId: product.id, qty: 2 }],
      },
    });
    assert.equal(created.status, 201);
    assert.match(created.body.data.order_no, /^WO-\d{4}-\d{4}$/);

    const after = await request(`/api/products/${product.id}`);
    assert.equal(after.body.data.stock, stockBefore - 2);

    const detail = await request(`/api/orders/${created.body.data.id}`);
    assert.equal(detail.body.data.items.length, 1);
    assert.equal(detail.body.data.items[0].qty, 2);

    const moved = await request(`/api/orders/${created.body.data.id}`, { method: 'PATCH', body: { status: 'shipped' } });
    assert.equal(moved.body.data.status, 'shipped');
  });

  it('rejects an order without line items', async () => {
    const customers = await request('/api/customers?limit=1');
    const response = await request('/api/orders', {
      method: 'POST',
      body: { customerId: customers.body.data[0].id, items: [] },
    });
    assert.equal(response.status, 400);
  });

  it('returns a dashboard payload with every panel', async () => {
    const response = await request('/api/dashboard');
    assert.equal(response.status, 200);
    for (const key of ['kpis', 'revenueSeries', 'ordersByStatus', 'topProducts', 'recentOrders', 'recentActivity', 'projects']) {
      assert.ok(key in response.body, `missing ${key}`);
    }
    assert.equal(response.body.revenueSeries.length, 6);
  });
});

describe('projects & deployments', () => {
  it('deploys a project and issues a revocable API key', async () => {
    const request = client();
    await request('/api/auth/login', { method: 'POST', body: { email: 'admin@woodex.pk', password: 'Woodex@2026' } });

    const projects = await request('/api/projects');
    const project = projects.body.data[0];

    const deploy = await request(`/api/projects/${project.id}/deployments`, {
      method: 'POST',
      body: { version: 'v9.9.9', environment: 'staging' },
    });
    assert.equal(deploy.status, 201);
    assert.equal(deploy.body.data.status, 'success');
    assert.ok(deploy.body.data.commit_sha);

    const key = await request(`/api/projects/${project.id}/keys`, {
      method: 'POST',
      body: { label: 'Test key', scopes: ['read'] },
    });
    assert.equal(key.status, 201);
    assert.match(key.body.secret, /^wk_live_/);

    const revoke = await request(`/api/projects/${project.id}/keys/${key.body.data.id}`, { method: 'DELETE' });
    assert.equal(revoke.status, 200);

    const detail = await request(`/api/projects/${project.id}`);
    assert.ok(detail.body.data.deployments.length > 0);
    assert.ok(!detail.body.data.apiKeys.some((item) => item.id === key.body.data.id && item.status === 'active'));
  });
});

describe('role permissions', () => {
  it('blocks a viewer from writing', async () => {
    const request = client();
    await request('/api/auth/login', { method: 'POST', body: { email: 'viewer@woodex.pk', password: 'Viewer@2026' } });

    const created = await request('/api/products', {
      method: 'POST',
      body: { name: 'Viewer Product', sku: 'WD-VIEW-1', price: 100 },
    });
    assert.equal(created.status, 403);

    const users = await request('/api/users');
    assert.equal(users.status, 403);
  });

  it('blocks a manager from team administration', async () => {
    const request = client();
    await request('/api/auth/login', { method: 'POST', body: { email: 'manager@woodex.pk', password: 'Manager@2026' } });
    const users = await request('/api/users');
    assert.equal(users.status, 403);
  });

  it('lets an admin invite a user and refuses to delete themselves', async () => {
    const request = client();
    const login = await request('/api/auth/login', { method: 'POST', body: { email: 'admin@woodex.pk', password: 'Woodex@2026' } });
    const selfId = login.body.user.id;

    const created = await request('/api/users', {
      method: 'POST',
      body: { name: 'QA Runner', email: 'qa@woodex.pk', password: 'QaRunner@2026', role: 'viewer' },
    });
    assert.equal(created.status, 201);

    const selfDelete = await request(`/api/users/${selfId}`, { method: 'DELETE' });
    assert.equal(selfDelete.status, 400);

    const removed = await request(`/api/users/${created.body.data.id}`, { method: 'DELETE' });
    assert.equal(removed.status, 200);
  });
});

describe('audit trail', () => {
  it('records mutations made through the API', async () => {
    const request = client();
    await request('/api/auth/login', { method: 'POST', body: { email: 'admin@woodex.pk', password: 'Woodex@2026' } });
    const activity = await request('/api/activity?limit=20');
    assert.equal(activity.status, 200);
    assert.ok(activity.body.data.length > 0);
    assert.ok(activity.body.data.some((entry) => entry.action === 'login'));
  });
});
