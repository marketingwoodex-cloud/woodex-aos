/**
 * Woodex AOS — database seeder.
 *
 *   node server/src/db/seed.js --reset   # wipe + reseed everything
 *   node server/src/db/seed.js           # seed only when the database is empty
 */
import { pathToFileURL } from 'node:url';
import { all, get, run, tx } from './index.js';
import { hashPassword } from '../lib/auth.js';

/* ------------------------------------------------------------------ helpers */

function mulberry32(seed) {
  let a = seed;
  return () => {
    a |= 0;
    a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

const random = mulberry32(20261114);
const pick = (list) => list[Math.floor(random() * list.length)];
const between = (min, max) => min + Math.floor(random() * (max - min + 1));
const daysAgo = (days) => new Date(Date.now() - days * 86400000).toISOString();

/* ------------------------------------------------------------- demo accounts */

export const DEMO_ACCOUNTS = [
  {
    name: 'Ayesha Khan', email: 'admin@woodex.pk', password: 'Woodex@2026',
    role: 'admin', title: 'Platform Owner', phone: '+92 300 8841200', accent: '#B4531F',
  },
  {
    name: 'Bilal Ahmed', email: 'manager@woodex.pk', password: 'Manager@2026',
    role: 'manager', title: 'Retail Operations Lead', phone: '+92 321 4472090', accent: '#2C5D8F',
  },
  {
    name: 'Sana Malik', email: 'viewer@woodex.pk', password: 'Viewer@2026',
    role: 'viewer', title: 'Showroom Analyst', phone: '+92 333 9907321', accent: '#2F7A5A',
  },
];

/* --------------------------------------------------------------- catalog data */

const CATEGORIES = [
  ['Sofas & Lounges', 'sofas-lounges', 'Deep-seat sofas, sectionals and lounge chairs.'],
  ['Beds', 'beds', 'Upholstered and solid-wood beds with storage options.'],
  ['Dining', 'dining', 'Dining tables, chairs and bar furniture.'],
  ['Office', 'office', 'Executive desks, task seating and shelving.'],
  ['Outdoor', 'outdoor', 'Weather-ready teak and aluminium outdoor ranges.'],
  ['Storage', 'storage', 'Wardrobes, sideboards and media consoles.'],
  ['Lighting', 'lighting', 'Floor, pendant and table lighting.'],
  ['Textiles', 'textiles', 'Rugs, throws and soft furnishing.'],
];

const PRODUCTS = [
  ['Alder Lounge Chair', 'WD-SOF-101', 'Sofas & Lounges', 84500, 24, 'Solid sheesham', 'Natural oil', 'active'],
  ['Oslo 3-Seater Sofa', 'WD-SOF-102', 'Sofas & Lounges', 289000, 9, 'Kiln-dried hardwood', 'Ivory bouclé', 'active'],
  ['Marina Sectional', 'WD-SOF-103', 'Sofas & Lounges', 415000, 4, 'Hardwood frame', 'Slate linen', 'active'],
  ['Terra Accent Chair', 'WD-SOF-104', 'Sofas & Lounges', 62000, 31, 'Mango wood', 'Terracotta velvet', 'active'],
  ['Nordic Recliner', 'WD-SOF-105', 'Sofas & Lounges', 158000, 2, 'Steel + hardwood', 'Charcoal leather', 'active'],
  ['Haven Queen Bed', 'WD-BED-201', 'Beds', 198000, 12, 'Ash hardwood', 'Oatmeal weave', 'active'],
  ['Aspen King Bed', 'WD-BED-202', 'Beds', 265000, 6, 'Solid oak', 'Smoke grey linen', 'active'],
  ['Luna Single Bed', 'WD-BED-203', 'Beds', 96000, 18, 'Pine + MDF', 'White wash', 'active'],
  ['Koa Storage Bed', 'WD-BED-204', 'Beds', 232000, 3, 'Sheesham', 'Walnut stain', 'active'],
  ['Rustic Oak Dining Table', 'WD-DIN-301', 'Dining', 176000, 7, 'Reclaimed oak', 'Matte lacquer', 'active'],
  ['Ember 6-Seater Set', 'WD-DIN-302', 'Dining', 312000, 5, 'Solid acacia', 'Charcoal wash', 'active'],
  ['Cove Dining Chair', 'WD-DIN-303', 'Dining', 28500, 46, 'Beech', 'Natural rattan', 'active'],
  ['Slate Bar Stool', 'WD-DIN-304', 'Dining', 19800, 38, 'Metal + oak', 'Black powder coat', 'active'],
  ['Executive Desk', 'WD-OFF-401', 'Office', 145000, 8, 'Walnut veneer', 'Satin finish', 'active'],
  ['Focus Task Chair', 'WD-OFF-402', 'Office', 48000, 22, 'Mesh + alloy', 'Graphite', 'active'],
  ['Linear Bookshelf', 'WD-OFF-403', 'Office', 72000, 14, 'Oak veneer', 'Clear lacquer', 'active'],
  ['Studio Work Table', 'WD-OFF-404', 'Office', 98000, 11, 'Birch ply', 'White oil', 'draft'],
  ['Veranda Teak Set', 'WD-OUT-501', 'Outdoor', 388000, 3, 'Grade-A teak', 'Weathered', 'active'],
  ['Breeze Sun Lounger', 'WD-OUT-502', 'Outdoor', 74000, 16, 'Teak + rope', 'Natural', 'active'],
  ['Cedar Planter Bench', 'WD-OUT-503', 'Outdoor', 42000, 4, 'Cedar', 'Teak oil', 'active'],
  ['Vault Wardrobe', 'WD-STO-601', 'Storage', 218000, 6, 'Engineered oak', 'Soft matte', 'active'],
  ['Cube Sideboard', 'WD-STO-602', 'Storage', 132000, 10, 'Mango wood', 'Walnut', 'active'],
  ['Astra TV Console', 'WD-STO-603', 'Storage', 88000, 13, 'Walnut veneer', 'Matte', 'active'],
  ['Nest Shoe Rack', 'WD-STO-604', 'Storage', 26500, 52, 'Bamboo', 'Natural', 'active'],
  ['Halo Floor Lamp', 'WD-LGT-701', 'Lighting', 34000, 21, 'Brass + linen', 'Antique brass', 'active'],
  ['Aurora Pendant', 'WD-LGT-702', 'Lighting', 28000, 27, 'Glass + brass', 'Amber', 'active'],
  ['Grove Table Lamp', 'WD-LGT-703', 'Lighting', 18500, 33, 'Ceramic', 'Sand', 'active'],
  ['Linen Sofa Throw', 'WD-TEX-801', 'Textiles', 9800, 64, 'Belgian linen', 'Oat', 'active'],
  ['Wool Area Rug 5x8', 'WD-TEX-802', 'Textiles', 46000, 19, 'Hand-knotted wool', 'Rust', 'active'],
  ['Velvet Cushion Set', 'WD-TEX-803', 'Textiles', 7500, 80, 'Cotton velvet', 'Mixed', 'active'],
];

const CUSTOMERS = [
  ['Fatima Raza', 'fatima@sereneinteriors.pk', '+92 300 1122334', 'Serene Interiors', 'Lahore', 'trade'],
  ['Bilal Ahmed', 'bilal.ahmed@gmail.com', '+92 321 5566778', 'Ahmed Residence', 'Karachi', 'retail'],
  ['Hina Qureshi', 'hina@studioverde.pk', '+92 333 9988776', 'Studio Verde', 'Islamabad', 'trade'],
  ['Usman Tariq', 'usman@thenest.pk', '+92 42 3577 8899', 'The Nest Hotel', 'Lahore', 'hospitality'],
  ['Zara Sheikh', 'zara.sheikh@outlook.com', '+92 300 4455667', 'Sheikh Villa', 'Faisalabad', 'retail'],
  ['Ali Raza', 'ali@rasafoods.pk', '+92 61 4567 890', 'Rasa Foods', 'Multan', 'wholesale'],
  ['Mariam Yousaf', 'mariam@yousafapts.pk', '+92 51 2299 445', 'Yousaf Apartments', 'Islamabad', 'trade'],
  ['Hamza Iqbal', 'hamza@iqbalsons.pk', '+92 52 4455 121', 'Iqbal & Sons', 'Sialkot', 'wholesale'],
  ['Sana Malik', 'sana@casanova.pk', '+92 21 3456 7788', 'Casa Nova Living', 'Karachi', 'retail'],
  ['Danish Farooq', 'danish@farooq-ent.pk', '+92 91 5566 233', 'Farooq Enterprises', 'Peshawar', 'trade'],
  ['Areeba Khan', 'areeba@coastline.pk', '+92 862 4455 66', 'Coastline Cafe', 'Gwadar', 'hospitality'],
  ['Omar Siddiqui', 'omar@siddiquioffices.pk', '+92 42 3666 1122', 'Siddiqui Offices', 'Lahore', 'trade'],
];

const PROJECTS = [
  ['Woodex Commerce Platform', 'WOODEX-WEB', 'Woodex HQ', 'Customer storefront, checkout and order sync for the full catalogue.', 'production', 'live', 'https://api.woodex.pk/v1', 100, 'ap-south-1'],
  ['Woodex Retail OS', 'WOODEX-RETAIL', 'Woodex HQ', 'Showroom POS, stock transfers and staff rota for six retail outlets.', 'staging', 'building', 'https://retail.woodex.pk/api', 62, 'ap-south-1'],
  ['Serene Interiors Portal', 'SERENE-PORTAL', 'Serene Interiors', 'Trade partner portal with tiered pricing and project moodboards.', 'production', 'live', 'https://portal.sereneinteriors.pk/v1', 100, 'me-central-1'],
  ['The Nest Hospitality Suite', 'NEST-HOSP', 'The Nest Hotel', 'Room-by-room furnishing catalogue with phased delivery tracking.', 'staging', 'staging', 'https://nest-ff.woodex.pk/api', 48, 'ap-south-1'],
  ['Karachi Showroom Kiosk', 'WOODEX-KIOSK', 'Woodex HQ', 'In-store kiosk app for fabric swatches and lead capture.', 'development', 'building', null, 27, 'ap-south-1'],
];

const INTEGRATIONS = [
  ['Stripe', 'Payments', 'connected', 'Card payments for the online storefront.', { account: 'acct_woodex_pk', mode: 'live' }],
  ['WhatsApp Business', 'Communication', 'connected', 'Order updates and delivery notifications.', { number: '+92 300 8841200' }],
  ['TCS Courier', 'Logistics', 'connected', 'Automated consignment booking for shipped orders.', { region: 'PK', account: 'TCS-99213' }],
  ['Zoho Books', 'Finance', 'error', 'Invoice sync failed — token expired 3 days ago.', { org: 'woodex-pk' }],
  ['Google Analytics 4', 'Analytics', 'connected', 'Storefront traffic and conversion tracking.', { property: 'G-WDX4421' }],
  ['Slack', 'Communication', 'disabled', 'Deployment + low-stock alerts to #woodex-ops.', { channel: '#woodex-ops' }],
];

/* --------------------------------------------------------------------- seeding */

function wipe() {
  const tables = [
    'order_items', 'orders', 'api_keys', 'deployments', 'activity',
    'products', 'categories', 'customers', 'projects', 'integrations',
    'settings', 'users',
  ];
  for (const table of tables) run(`DELETE FROM ${table}`);
  run("DELETE FROM sqlite_sequence WHERE name IN ('users','categories','products','customers','orders','order_items','projects','deployments','api_keys','integrations','activity')");
}

export function seedDatabase({ reset = false } = {}) {
  const stamp = new Date().toISOString();

  tx(() => {
    if (reset) wipe();

    /* users */
    for (const user of DEMO_ACCOUNTS) {
      run(
        `INSERT INTO users (name, email, password_hash, role, title, phone, accent, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)`,
        [user.name, user.email, hashPassword(user.password), user.role, user.title, user.phone, user.accent, daysAgo(120)],
      );
    }

    /* categories */
    const categoryIds = {};
    for (const [name, slug, description] of CATEGORIES) {
      const result = run('INSERT INTO categories (name, slug, description, created_at) VALUES (?, ?, ?, ?)', [name, slug, description, stamp]);
      categoryIds[name] = Number(result.lastInsertRowid);
    }

    /* products */
    const productIds = [];
    for (const [name, sku, category, price, stock, material, finish, status] of PRODUCTS) {
      const result = run(
        `INSERT INTO products (sku, name, category_id, description, material, finish, price, cost, stock, reorder_level, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          sku, name, categoryIds[category] ?? null,
          `${name} — hand-finished ${material.toLowerCase()} built in the Woodex Lahore workshop.`,
          material, finish, price, Math.round(price * 0.61), stock,
          stock < 6 ? 8 : 5, status, daysAgo(between(20, 300)), daysAgo(between(1, 18)),
        ],
      );
      productIds.push({ id: Number(result.lastInsertRowid), name, sku, price });
    }

    /* customers */
    const customerIds = [];
    for (const [name, email, phone, company, city, type] of CUSTOMERS) {
      const result = run(
        `INSERT INTO customers (name, email, phone, company, city, country, type, status, created_at)
         VALUES (?, ?, ?, ?, ?, 'Pakistan', ?, 'active', ?)`,
        [name, email, phone, company, city, type, daysAgo(between(30, 320))],
      );
      customerIds.push({ id: Number(result.lastInsertRowid), name, type });
    }

    /* orders — spread over the last ~6 months so the dashboard chart has shape */
    const statusPlan = [
      { maxDays: 4, statuses: ['pending', 'confirmed'] },
      { maxDays: 18, statuses: ['confirmed', 'in_production'] },
      { maxDays: 45, statuses: ['in_production', 'shipped'] },
      { maxDays: 999, statuses: ['delivered', 'delivered', 'shipped'] },
    ];

    const orderDrafts = [];
    for (let i = 0; i < 34; i += 1) {
      const customer = pick(customerIds);
      const placedDays = i < 8 ? between(0, 12) : between(10, 172);
      const plan = statusPlan.find((entry) => placedDays <= entry.maxDays) ?? statusPlan[3];
      let status = pick(plan.statuses);
      if (random() < 0.07) status = 'cancelled';

      const itemCount = between(1, 3);
      const chosen = new Set();
      const items = [];
      while (items.length < itemCount) {
        const product = pick(productIds);
        if (chosen.has(product.id)) continue;
        chosen.add(product.id);
        const qty = customer.type === 'wholesale' ? between(2, 6) : between(1, 2);
        items.push({ ...product, qty });
      }

      const subtotal = items.reduce((sum, item) => sum + item.price * item.qty, 0);
      const tax = Math.round(subtotal * 0.05);
      const shipping = subtotal > 300000 ? 0 : pick([0, 3500, 8500]);
      orderDrafts.push({
        customer, status, placedDays,
        channel: customer.type === 'hospitality' ? 'trade' : pick(['showroom', 'online', 'trade', 'phone']),
        subtotal, tax, shipping, total: subtotal + tax + shipping, items,
      });
    }

    orderDrafts.sort((a, b) => b.placedDays - a.placedDays);
    const sequences = {};
    for (const draft of orderDrafts) {
      const placedAt = daysAgo(draft.placedDays);
      const year = new Date(placedAt).getUTCFullYear();
      sequences[year] = (sequences[year] ?? 0) + 1;
      const orderNo = `WO-${year}-${String(sequences[year]).padStart(4, '0')}`;
      const createdAt = placedAt;

      const result = run(
        `INSERT INTO orders (order_no, customer_id, status, channel, subtotal, tax, shipping, total, currency, notes, placed_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PKR', ?, ?, ?, ?)`,
        [
          orderNo, draft.customer.id, draft.status, draft.channel, draft.subtotal, draft.tax, draft.shipping, draft.total,
          draft.status === 'cancelled' ? 'Cancelled — client changed the room layout.' : null,
          placedAt, createdAt, createdAt,
        ],
      );
      const orderId = Number(result.lastInsertRowid);
      for (const item of draft.items) {
        run(
          'INSERT INTO order_items (order_id, product_id, name, sku, qty, unit_price) VALUES (?, ?, ?, ?, ?, ?)',
          [orderId, item.id, item.name, item.sku, item.qty, item.price],
        );
      }
    }

    /* projects */
    const projectIds = {};
    for (const [name, code, client, description, environment, status, apiBase, progress, region] of PROJECTS) {
      const result = run(
        `INSERT INTO projects (name, code, client, description, environment, status, api_base, progress, region, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [name, code, client, description, environment, status, apiBase, progress, region, daysAgo(between(90, 400)), daysAgo(between(0, 12))],
      );
      projectIds[code] = Number(result.lastInsertRowid);
    }

    /* deployments */
    const deploymentPlan = [
      ['WOODEX-WEB', 'v3.8.2', 'production', 'success', 42000, 1, 'Catalogue bulk-import hotfix'],
      ['WOODEX-WEB', 'v3.8.1', 'production', 'success', 51000, 6, 'Checkout tax rules for Punjab'],
      ['WOODEX-WEB', 'v3.9.0-rc1', 'staging', 'success', 38000, 2, 'New collection landing pages'],
      ['WOODEX-RETAIL', 'v0.9.4', 'staging', 'failed', 96000, 3, 'POS printer bridge timed out'],
      ['WOODEX-RETAIL', 'v0.9.3', 'staging', 'success', 61000, 9, 'Stock transfer approvals'],
      ['SERENE-PORTAL', 'v2.1.0', 'production', 'success', 33000, 5, 'Tiered trade pricing tables'],
      ['SERENE-PORTAL', 'v2.0.7', 'production', 'rolled_back', 29000, 15, 'Rolled back — price rounding bug'],
      ['NEST-HOSP', 'v0.4.1', 'staging', 'success', 47000, 4, 'Room-by-room delivery tracker'],
      ['WOODEX-KIOSK', 'v0.1.2', 'development', 'success', 22000, 8, 'Swatch capture flow'],
    ];
    for (const [code, version, environment, status, duration, age, notes] of deploymentPlan) {
      run(
        `INSERT INTO deployments (project_id, version, environment, status, commit_sha, duration_ms, notes, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          projectIds[code], version, environment, status,
          Math.random().toString(16).slice(2, 10), duration, notes,
          pick(DEMO_ACCOUNTS).name, daysAgo(age),
        ],
      );
    }

    /* api keys */
    const keyPlan = [
      ['WOODEX-WEB', 'Storefront production', 'read,write', 'active', 1],
      ['WOODEX-WEB', 'Analytics read-only', 'read', 'active', 12],
      ['SERENE-PORTAL', 'Serene partner sync', 'read', 'active', 30],
      ['WOODEX-RETAIL', 'Legacy POS (retired)', 'read,write', 'revoked', 60],
    ];
    for (const [code, label, scopes, status, age] of keyPlan) {
      run(
        `INSERT INTO api_keys (project_id, label, prefix, token_hash, scopes, status, last_used_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          projectIds[code], label, `wk_live_${Math.random().toString(16).slice(2, 10)}`,
          Math.random().toString(16).padEnd(64, '0').slice(0, 64), scopes, status,
          status === 'active' ? daysAgo(between(0, 3)) : null, daysAgo(age),
        ],
      );
    }

    /* integrations */
    for (const [name, category, status, description, config] of INTEGRATIONS) {
      run(
        `INSERT INTO integrations (name, category, status, description, config_json, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [name, category, status, description, JSON.stringify(config), daysAgo(between(0, 20))],
      );
    }

    /* settings */
    const settings = {
      org_name: 'Woodex Furniture',
      currency: 'PKR',
      tax_rate: '5',
      support_email: 'care@woodex.pk',
      phone: '+92 42 3577 8899',
      address: '12-C Gulberg III, Lahore, Pakistan',
      timezone: 'Asia/Karachi',
      low_stock_threshold: '5',
      brand_color: '#B4531F',
    };
    for (const [key, value] of Object.entries(settings)) {
      run('INSERT INTO settings (key, value) VALUES (?, ?)', [key, value]);
    }

    /* activity trail */
    const activityPlan = [
      ['Ayesha Khan', 'deploy', 'deployment', 'Deployed v3.8.2 of “Woodex Commerce Platform” to production', 1],
      ['Bilal Ahmed', 'status', 'order', 'Order WO-2026-0031 moved to in production', 2],
      ['Ayesha Khan', 'create', 'api_key', 'Issued API key “Storefront production” for Woodex Commerce Platform', 3],
      ['Sana Malik', 'export', 'report', 'Exported the weekly sales summary', 4],
      ['Bilal Ahmed', 'create', 'customer', 'Added customer “Coastline Cafe”', 5],
      ['Ayesha Khan', 'update', 'integration', 'Flagged Zoho Books integration — token expired', 6],
      ['Bilal Ahmed', 'update', 'product', 'Updated product “Marina Sectional” pricing', 8],
      ['Sana Malik', 'login', 'session', 'Sana Malik signed in', 9],
    ];
    for (const [userName, action, entity, summary, age] of activityPlan) {
      run(
        'INSERT INTO activity (user_id, user_name, action, entity, entity_id, summary, created_at) VALUES (NULL, ?, ?, ?, NULL, ?, ?)',
        [userName, action, entity, summary, daysAgo(age)],
      );
    }
  });

  return {
    users: get('SELECT COUNT(*) AS c FROM users').c,
    products: get('SELECT COUNT(*) AS c FROM products').c,
    customers: get('SELECT COUNT(*) AS c FROM customers').c,
    orders: get('SELECT COUNT(*) AS c FROM orders').c,
    projects: get('SELECT COUNT(*) AS c FROM projects').c,
    activity: get('SELECT COUNT(*) AS c FROM activity').c,
  };
}

export function ensureSeeded() {
  const users = get('SELECT COUNT(*) AS c FROM users').c;
  if (users > 0) return false;
  const summary = seedDatabase();
  console.log('[seed] demo workspace created:', summary);
  console.log('[seed] sign in with admin@woodex.pk / Woodex@2026');
  return true;
}

const isCli = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
if (isCli) {
  const reset = process.argv.includes('--reset');
  const summary = seedDatabase({ reset });
  console.log(`[seed] database ${reset ? 'reset and reseeded' : 'seeded'} →`, summary);
  console.log('\nDemo accounts');
  for (const account of DEMO_ACCOUNTS) {
    console.log(`  ${account.role.padEnd(8)} ${account.email.padEnd(20)} ${account.password}`);
  }
}
