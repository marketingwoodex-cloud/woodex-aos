/**
 * Live database — the imported woodexbaks data (see tools/db-import).
 * Kept separate from the demo database so a re-import never touches demo rows.
 * Every helper degrades gracefully when the live database has not been imported.
 */
import { DatabaseSync } from 'node:sqlite';
import { existsSync, statSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

export const LIVE_DB_PATH = process.env.LIVE_DB_PATH
  ? resolve(process.env.LIVE_DB_PATH)
  : join(__dirname, '..', '..', 'data', 'woodex-live.db');

let handle = null;
let loadedStamp = '';
let schemaOk = false;

function closeHandle() {
  try {
    handle?.close();
  } catch {
    /* ignore */
  }
  handle = null;
  loadedStamp = '';
  schemaOk = false;
}

function fileStamp() {
  try {
    const stat = statSync(LIVE_DB_PATH);
    return `${stat.mtimeMs}:${stat.size}`;
  } catch {
    return '';
  }
}

/**
 * Opens the live database, reopening it when the file changes on disk (the
 * importer swaps in a new database atomically). Returns null when the file is
 * missing or is not a Woodex import, so every caller degrades gracefully.
 */
function openHandle() {
  if (!existsSync(LIVE_DB_PATH)) {
    closeHandle();
    return null;
  }
  const stamp = fileStamp();
  if (!stamp) {
    closeHandle();
    return null;
  }
  if (handle && stamp === loadedStamp) return handle;

  closeHandle();
  try {
    handle = new DatabaseSync(LIVE_DB_PATH);
    handle.exec('PRAGMA foreign_keys = ON;');
    loadedStamp = stamp;
    schemaOk = Boolean(
      handle.prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'meta'").get(),
    );
    if (!schemaOk) {
      console.warn('[live] database file is not a Woodex import — run: npm run db:import');
    }
  } catch (error) {
    console.warn('[live] could not open the live database:', error.message);
    closeHandle();
    return null;
  }
  return handle;
}

export function liveAvailable() {
  if (!existsSync(LIVE_DB_PATH)) return false;
  return Boolean(openHandle()) && schemaOk;
}

/** Live connection, or null when no imported database is available. */
function db() {
  return liveAvailable() ? handle : null;
}

export function liveAll(sql, params = []) {
  const conn = db();
  if (!conn) return [];
  try {
    return conn.prepare(sql).all(...params).map((row) => ({ ...row }));
  } catch (error) {
    console.warn('[live] query failed:', error.message);
    return [];
  }
}

export function liveGet(sql, params = []) {
  const conn = db();
  if (!conn) return null;
  try {
    const row = conn.prepare(sql).get(...params);
    return row ? { ...row } : null;
  } catch (error) {
    console.warn('[live] query failed:', error.message);
    return null;
  }
}

export function liveRun(sql, params = []) {
  const conn = db();
  if (!conn) return null;
  try {
    return conn.prepare(sql).run(...params);
  } catch (error) {
    console.warn('[live] write failed:', error.message);
    return null;
  }
}

function metaValue(key) {
  return liveGet('SELECT value FROM meta WHERE key = ?', [key])?.value ?? null;
}

export function liveMeta() {
  if (!liveAvailable()) {
    return { available: false, source: null, importedAt: null, counts: null };
  }
  let counts = null;
  try {
    counts = JSON.parse(metaValue('counts') || 'null');
  } catch {
    counts = null;
  }
  return {
    available: true,
    source: metaValue('source'),
    database: metaValue('database'),
    server: metaValue('server'),
    php: metaValue('php'),
    importedAt: metaValue('importedAt'),
    counts,
  };
}

export function liveSettings() {
  const rows = liveAll('SELECT key, value FROM settings');
  const result = {};
  for (const { key, value } of rows) {
    const plain = key.replace(/^footer\./, '');
    if (plain === 'paymentLogos' || plain === 'socialLinks') {
      try {
        result[plain] = JSON.parse(value);
      } catch {
        result[plain] = [];
      }
    } else {
      result[plain] = value;
    }
  }
  return result;
}

/** Absolute URL for a stored relative media path (images live on the shop host). */
export function mediaUrl(path) {
  if (!path) return null;
  if (/^https?:\/\//i.test(path)) return path;
  const base = process.env.MEDIA_BASE || 'https://woodexfurniture.pk/';
  return `${base.replace(/\/?$/, '/')}${String(path).replace(/^\/+/, '')}`;
}

/** All category ids at or below the given slug. */
export function categorySubtree(slug) {
  return liveAll(
    `WITH RECURSIVE tree(id) AS (
       SELECT id FROM categories WHERE slug = ?
       UNION ALL
       SELECT c.id FROM categories c JOIN tree t ON c.parent_id = t.id
     )
     SELECT id FROM tree`,
    [slug],
  ).map((row) => row.id);
}

export function categoryBreadcrumb(categoryId) {
  if (!categoryId) return [];
  const chain = [];
  let current = liveGet('SELECT id, parent_id, name, slug FROM categories WHERE id = ?', [categoryId]);
  let guard = 0;
  while (current && guard < 10) {
    chain.unshift(current);
    current = current.parent_id
      ? liveGet('SELECT id, parent_id, name, slug FROM categories WHERE id = ?', [current.parent_id])
      : null;
    guard += 1;
  }
  return chain;
}

export function parseJsonColumn(value, fallback) {
  if (!value) return fallback;
  try {
    return JSON.parse(value);
  } catch {
    return fallback;
  }
}

/** Shape a product row for the API/clients. */
export function presentProduct(row) {
  if (!row) return null;
  const images = parseJsonColumn(row.images_json, []);
  return {
    id: row.id,
    name: row.name,
    slug: row.slug,
    sku: row.sku,
    price: row.price,
    oldPrice: row.old_price,
    onSale: Boolean(row.is_sale_item) && row.old_price > row.price,
    discountPercent: row.old_price > row.price && row.old_price > 0
      ? Math.round(((row.old_price - row.price) / row.old_price) * 100)
      : 0,
    stock: row.stock,
    inStock: row.stock > 0,
    isBestSeller: Boolean(row.is_best_seller),
    isActive: Boolean(row.is_active),
    categoryId: row.category_id,
    categoryName: row.category_name ?? null,
    brand: row.brand,
    rating: row.rating_count > 0 ? Number((row.rating_sum / row.rating_count).toFixed(1)) : null,
    ratingCount: row.rating_count,
    shortDescription: row.short_description,
    images,
    image: images[0] ?? null,
    imageUrl: mediaUrl(images[0]),
    updatedAt: row.updated_at,
  };
}
