/**
 * Loads db-source/snapshot.json into server/data/woodex-live.db (SQLite).
 * This is the read/write store behind the storefront preview and the live
 * admin modules — deliberately separate from the demo database so the real
 * data can be re-imported at any time without touching demo records.
 *
 *   npm run db:import
 *
 * Set LIVE_DB_PATH / SNAPSHOT_PATH to override the default locations.
 */
import { DatabaseSync } from 'node:sqlite';
import { existsSync, mkdirSync, readFileSync, renameSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..', '..');
const snapshotPath = process.env.SNAPSHOT_PATH
  ? resolve(process.env.SNAPSHOT_PATH)
  : join(root, 'db-source', 'snapshot.json');
const dbPath = process.env.LIVE_DB_PATH
  ? resolve(process.env.LIVE_DB_PATH)
  : join(root, 'server', 'data', 'woodex-live.db');

if (!existsSync(snapshotPath)) {
  console.error(`Snapshot not found: ${snapshotPath}`);
  console.error('Generate it first: python3 tools/db-import/export_snapshot.py db-source/woodexbaks.sql');
  process.exit(1);
}

const snapshot = JSON.parse(readFileSync(snapshotPath, 'utf8'));
mkdirSync(dirname(dbPath), { recursive: true });

// Build into a scratch file and rename it into place when complete. Replacing
// the file atomically means a running server picks up a finished database on
// its next query instead of reading a half-written one. Sidecar journals from
// a previous import are removed too — a stale -wal next to a fresh file makes
// SQLite serve an empty schema.
const tempPath = `${dbPath}.importing`;
for (const stale of [dbPath, `${dbPath}-wal`, `${dbPath}-shm`, tempPath, `${tempPath}-wal`, `${tempPath}-shm`]) {
  if (existsSync(stale)) rmSync(stale);
}

const db = new DatabaseSync(tempPath);
db.exec('PRAGMA journal_mode = DELETE;'); // single file, no WAL sidecars
db.exec('PRAGMA foreign_keys = ON;');

db.exec(`
CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT NOT NULL);

CREATE TABLE categories (
  id INTEGER PRIMARY KEY,
  parent_id INTEGER,
  level INTEGER NOT NULL DEFAULT 1,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  path TEXT,
  short_description TEXT,
  long_description TEXT,
  main_image TEXT,
  background_image TEXT,
  is_active INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0,
  show_in_home_carousel INTEGER NOT NULL DEFAULT 0,
  show_in_best_sellers INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE products (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  sku TEXT NOT NULL,
  price REAL NOT NULL DEFAULT 0,
  old_price REAL NOT NULL DEFAULT 0,
  stock INTEGER NOT NULL DEFAULT 0,
  sold_quantity INTEGER NOT NULL DEFAULT 0,
  category_id INTEGER,
  brand TEXT,
  short_description TEXT,
  details TEXT,
  specs_json TEXT NOT NULL DEFAULT '[]',
  images_json TEXT NOT NULL DEFAULT '[]',
  is_best_seller INTEGER NOT NULL DEFAULT 0,
  is_sale_item INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  rating_count INTEGER NOT NULL DEFAULT 0,
  rating_sum INTEGER NOT NULL DEFAULT 0,
  video_embed TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_flags ON products(is_best_seller, is_sale_item);
CREATE INDEX idx_products_name ON products(name);

CREATE TABLE product_categories (
  product_id INTEGER NOT NULL,
  category_id INTEGER NOT NULL,
  PRIMARY KEY (product_id, category_id)
);
CREATE INDEX idx_pc_category ON product_categories(category_id);

CREATE TABLE faqs (
  id INTEGER PRIMARY KEY,
  scope TEXT NOT NULL,
  item_id INTEGER NOT NULL,
  question TEXT NOT NULL,
  answer TEXT NOT NULL
);
CREATE INDEX idx_faqs_item ON faqs(scope, item_id);

CREATE TABLE orders (
  id INTEGER PRIMARY KEY,
  order_number TEXT NOT NULL UNIQUE,
  customer_name TEXT,
  customer_email TEXT,
  customer_phone TEXT,
  shipping_address TEXT,
  order_date TEXT NOT NULL,
  total REAL NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'Pending',
  payment_status TEXT DEFAULT 'Pending',
  payment_method TEXT,
  coupon_code TEXT,
  discount REAL NOT NULL DEFAULT 0,
  shipping REAL NOT NULL DEFAULT 0,
  delivery_company TEXT,
  tracking_url TEXT,
  tracking_code TEXT,
  created_at TEXT NOT NULL
);

CREATE TABLE order_items (
  id INTEGER PRIMARY KEY,
  order_number TEXT NOT NULL,
  product_id INTEGER,
  product_name TEXT NOT NULL,
  price REAL NOT NULL DEFAULT 0,
  quantity INTEGER NOT NULL DEFAULT 1,
  subtotal REAL NOT NULL DEFAULT 0,
  product_missing INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_order_items_order ON order_items(order_number);

CREATE TABLE quotations (
  id INTEGER PRIMARY KEY,
  quote_no TEXT NOT NULL UNIQUE,
  customer_name TEXT,
  customer_email TEXT,
  customer_phone TEXT,
  customer_address TEXT,
  subtotal REAL NOT NULL DEFAULT 0,
  total REAL NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'new',
  created_at TEXT NOT NULL
);

CREATE TABLE quotation_items (
  id INTEGER PRIMARY KEY,
  quote_id INTEGER NOT NULL,
  product_id INTEGER,
  product_name TEXT NOT NULL,
  sku TEXT,
  unit_price REAL NOT NULL DEFAULT 0,
  quantity INTEGER NOT NULL DEFAULT 1,
  line_total REAL NOT NULL DEFAULT 0,
  image TEXT,
  product_missing INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_quote_items_quote ON quotation_items(quote_id);

CREATE TABLE hero_slides (
  id INTEGER PRIMARY KEY,
  kicker TEXT,
  title TEXT,
  description TEXT,
  cta_label TEXT,
  cta_url TEXT,
  image TEXT,
  alt TEXT,
  published INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE blogs (
  id INTEGER PRIMARY KEY,
  title TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  meta_description TEXT,
  image TEXT,
  content TEXT,
  created_at TEXT
);

CREATE TABLE footer_links (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  section TEXT NOT NULL,
  title TEXT NOT NULL,
  url TEXT,
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
`);

const importedAt = new Date().toISOString();

function insertMany(sql, rows, mapper) {
  const statement = db.prepare(sql);
  db.exec('BEGIN');
  try {
    for (const row of rows) statement.run(...mapper(row));
    db.exec('COMMIT');
  } catch (error) {
    db.exec('ROLLBACK');
    throw error;
  }
}

insertMany(
  `INSERT INTO categories (id, parent_id, level, name, slug, path, short_description, long_description,
     main_image, background_image, is_active, sort_order, show_in_home_carousel, show_in_best_sellers)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
  snapshot.categories,
  (c) => [
    c.id, c.parentId, c.level, c.name, c.slug, c.path, c.shortDescription, c.longDescription,
    c.mainImage, c.backgroundImage, c.isActive ? 1 : 0, c.sortOrder || 0,
    c.showInHomeCarousel ? 1 : 0, c.showInBestSellers ? 1 : 0,
  ],
);

insertMany(
  `INSERT INTO products (id, name, slug, sku, price, old_price, stock, sold_quantity, category_id, brand,
     short_description, details, specs_json, images_json, is_best_seller, is_sale_item, is_active,
     rating_count, rating_sum, video_embed, created_at, updated_at)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
  snapshot.products,
  (p) => [
    p.id, p.name, p.slug, p.sku, p.price, p.oldPrice, p.stock, p.soldQuantity, p.categoryId, p.brand,
    p.shortDescription, p.details, JSON.stringify(p.specifications || []), JSON.stringify(p.images || []),
    p.isBestSeller ? 1 : 0, p.isSaleItem ? 1 : 0, p.isActive ? 1 : 0,
    p.ratingCount, p.ratingSum, p.videoEmbed, importedAt, importedAt,
  ],
);

insertMany(
  'INSERT OR IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)',
  snapshot.productCategories,
  (pc) => [pc.productId, pc.categoryId],
);

insertMany(
  'INSERT INTO faqs (id, scope, item_id, question, answer) VALUES (?, ?, ?, ?, ?)',
  snapshot.faqs,
  (f) => [f.id, f.scope, f.itemId, f.question, f.answer],
);

insertMany(
  `INSERT INTO orders (id, order_number, customer_name, customer_email, customer_phone, shipping_address,
     order_date, total, status, payment_status, payment_method, coupon_code, discount, shipping,
     delivery_company, tracking_url, tracking_code, created_at)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
  snapshot.orders,
  (o) => [
    o.id, o.orderNumber, o.customerName, o.customerEmail, o.customerPhone, o.shippingAddress,
    o.orderDate, o.total, o.status, o.paymentStatus, o.paymentMethod, o.couponCode, o.discount,
    o.shipping, o.deliveryCompany, o.trackingUrl, o.trackingCode, o.orderDate,
  ],
);

insertMany(
  `INSERT INTO order_items (id, order_number, product_id, product_name, price, quantity, subtotal, product_missing)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
  snapshot.orderItems,
  (i) => [i.id, i.orderNumber, i.productId, i.productName, i.price, i.quantity, i.subtotal, i.productMissing ? 1 : 0],
);

insertMany(
  `INSERT INTO quotations (id, quote_no, customer_name, customer_email, customer_phone, customer_address,
     subtotal, total, status, created_at)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
  snapshot.quotations,
  (q) => [q.id, q.quoteNo, q.customerName, q.customerEmail, q.customerPhone, q.customerAddress, q.subtotal, q.total, q.status, q.createdAt],
);

insertMany(
  `INSERT INTO quotation_items (id, quote_id, product_id, product_name, sku, unit_price, quantity, line_total, image, product_missing)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
  snapshot.quotationItems,
  (i) => [i.id, i.quoteId, i.productId, i.productName, i.sku, i.unitPrice, i.quantity, i.lineTotal, i.image, i.productMissing ? 1 : 0],
);

// A slide whose title is a placeholder ("sss") would look broken in the preview.
const looksLikeTest = (text = '') => {
  const clean = text.replace(/\s/g, '');
  return clean.length < 6 || /^(.)\1+$/.test(clean);
};
const heroSlides = snapshot.heroSlides.filter((slide) => !looksLikeTest(slide.title || ''));

insertMany(
  'INSERT INTO hero_slides (id, kicker, title, description, cta_label, cta_url, image, alt, published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
  heroSlides,
  (h) => [h.id, h.kicker, h.title, h.description, h.ctaLabel, h.ctaUrl, h.image, h.alt, h.published ? 1 : 0],
);

insertMany(
  'INSERT INTO blogs (id, title, slug, meta_description, image, content, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
  snapshot.blogs,
  (b) => [b.id, b.title, b.slug, b.metaDescription, b.image, b.content, b.createdAt],
);

insertMany(
  'INSERT INTO footer_links (section, title, url, sort_order) VALUES (?, ?, ?, ?)',
  snapshot.footerLinks,
  (l) => [l.section, l.title, l.url, l.sort],
);

const settingsStatement = db.prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
for (const [key, value] of Object.entries(snapshot.footer || {})) {
  settingsStatement.run(`footer.${key}`, typeof value === 'string' ? value : JSON.stringify(value));
}

const counts = {
  products: db.prepare('SELECT COUNT(*) AS c FROM products').get().c,
  categories: db.prepare('SELECT COUNT(*) AS c FROM categories').get().c,
  productCategories: db.prepare('SELECT COUNT(*) AS c FROM product_categories').get().c,
  faqs: db.prepare('SELECT COUNT(*) AS c FROM faqs').get().c,
  orders: db.prepare('SELECT COUNT(*) AS c FROM orders').get().c,
  orderItems: db.prepare('SELECT COUNT(*) AS c FROM order_items').get().c,
  quotations: db.prepare('SELECT COUNT(*) AS c FROM quotations').get().c,
  quotationItems: db.prepare('SELECT COUNT(*) AS c FROM quotation_items').get().c,
  heroSlides: db.prepare('SELECT COUNT(*) AS c FROM hero_slides').get().c,
  blogs: db.prepare('SELECT COUNT(*) AS c FROM blogs').get().c,
  outOfStock: db.prepare('SELECT COUNT(*) AS c FROM products WHERE stock <= 0').get().c,
  zeroPrice: db.prepare('SELECT COUNT(*) AS c FROM products WHERE price = 0').get().c,
  orderValue: db.prepare('SELECT COALESCE(SUM(total), 0) AS v FROM orders').get().v,
  quotationValue: db.prepare('SELECT COALESCE(SUM(total), 0) AS v FROM quotations').get().v,
};

const meta = db.prepare('INSERT OR REPLACE INTO meta (key, value) VALUES (?, ?)');
meta.run('source', snapshot.meta.source);
meta.run('database', snapshot.meta.database || '');
meta.run('server', snapshot.meta.server || '');
meta.run('php', snapshot.meta.php || '');
meta.run('importedAt', importedAt);
meta.run('counts', JSON.stringify(counts));
meta.run('sourceCounts', JSON.stringify(snapshot.meta.counts));
meta.run('skippedHeroSlides', String(snapshot.heroSlides.length - heroSlides.length));

db.close();

// The scratch file is complete and closed — publish it atomically so a running
// server swaps to the new database instead of reading a half-written one.
renameSync(tempPath, dbPath);

console.log(`\nLive database written to ${dbPath}`);
for (const [key, value] of Object.entries(counts)) {
  console.log(`  ${key.padEnd(20)} ${typeof value === 'number' ? value.toLocaleString('en-US') : value}`);
}
console.log(`\n${snapshot.heroSlides.length - heroSlides.length} placeholder hero slide(s) skipped.`);
console.log('Start the server and open /shop for the storefront preview.\n');
