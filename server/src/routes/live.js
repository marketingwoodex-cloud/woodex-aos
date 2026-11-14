/**
 * Live admin API — the imported woodexbaks data with edit access for the
 * catalogue (price, stock, merchandising flags, visibility) plus read-only
 * views of the real orders, quotations and CMS content.
 */
import { Router } from 'express';
import { all, get } from '../db/index.js';
import { ApiError, asyncHandler, pagination, listResponse, toInt, toNumber } from '../lib/http.js';
import { requireWrite } from '../lib/auth.js';
import { logFromRequest } from '../lib/activity.js';
import {
  categorySubtree, liveAll, liveAvailable, liveGet, liveMeta, liveRun, liveSettings,
  mediaUrl, parseJsonColumn, presentProduct,
} from '../db/live.js';

const router = Router();

const PRODUCT_SELECT = `
  SELECT p.*, c.name AS category_name
    FROM products p LEFT JOIN categories c ON c.id = p.category_id
`;

function requireLive() {
  if (!liveAvailable()) {
    throw new ApiError(
      503,
      'The live database is not imported. Run: python3 tools/db-import/export_snapshot.py db-source/woodexbaks.sql && npm run db:import',
    );
  }
}

router.get(
  '/overview',
  asyncHandler(async (_req, res) => {
    if (!liveAvailable()) {
      return res.json({ available: false, meta: liveMeta() });
    }
    const totals = liveGet(`
      SELECT
        (SELECT COUNT(*) FROM products) AS products,
        (SELECT COUNT(*) FROM categories) AS categories,
        (SELECT COUNT(*) FROM products WHERE stock <= 0) AS out_of_stock,
        (SELECT COUNT(*) FROM products WHERE price = 0) AS zero_price,
        (SELECT COUNT(*) FROM products WHERE is_best_seller = 1) AS best_sellers,
        (SELECT COALESCE(SUM(total),0) FROM orders) AS order_value,
        (SELECT COUNT(*) FROM orders) AS orders,
        (SELECT COALESCE(SUM(total),0) FROM quotations) AS quotation_value,
        (SELECT COUNT(*) FROM quotations) AS quotations,
        (SELECT COUNT(*) FROM faqs) AS faqs,
        (SELECT COUNT(*) FROM order_items WHERE product_missing = 1) AS orphan_order_lines,
        (SELECT COUNT(*) FROM quotation_items WHERE product_missing = 1) AS orphan_quote_lines
    `);

    const categories = liveAll(`
      SELECT c.id, c.name, c.level, c.parent_id,
             (SELECT COUNT(*) FROM product_categories pc WHERE pc.category_id = c.id) AS product_count
        FROM categories c
       WHERE c.is_active = 1 AND c.level = 1
       ORDER BY c.sort_order, c.name
    `);

    const recentOrders = liveAll(
      'SELECT id, order_number, customer_name, total, status, order_date FROM orders ORDER BY order_date DESC LIMIT 5',
    );
    const recentQuotations = liveAll(
      'SELECT id, quote_no, customer_name, total, status, created_at FROM quotations ORDER BY created_at DESC LIMIT 5',
    );
    const lowStock = liveAll(
      'SELECT id, name, sku, stock, price FROM products WHERE stock <= 2 ORDER BY stock ASC, name LIMIT 6',
    );

    res.json({
      available: true,
      meta: liveMeta(),
      totals,
      categories,
      recentOrders,
      recentQuotations,
      lowStock,
    });
  }),
);

router.get(
  '/products',
  asyncHandler(async (req, res) => {
    requireLive();
    const { page, limit, offset } = pagination(req, 12, 100);
    const filters = ['1 = 1'];
    const params = [];

    if (req.query.search) {
      const term = `%${String(req.query.search).trim()}%`;
      filters.push('(p.name LIKE ? OR p.sku LIKE ?)');
      params.push(term, term);
    }
    if (req.query.category && req.query.category !== 'all') {
      const ids = categorySubtree(String(req.query.category));
      if (!ids.length) return res.json(listResponse([], 0, { page, limit }));
      filters.push(`(p.category_id IN (${ids.join(',')}) OR EXISTS (
        SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN (${ids.join(',')})
      ))`);
    }
    if (req.query.filter === 'out') filters.push('p.stock <= 0');
    if (req.query.filter === 'zero-price') filters.push('p.price = 0');
    if (req.query.filter === 'best') filters.push('p.is_best_seller = 1');
    if (req.query.filter === 'hidden') filters.push('p.is_active = 0');

    const where = `WHERE ${filters.join(' AND ')}`;
    const total = liveGet(`SELECT COUNT(*) AS c FROM products p ${where}`, params)?.c ?? 0;
    const rows = liveAll(`${PRODUCT_SELECT} ${where} ORDER BY p.id LIMIT ? OFFSET ?`, [...params, limit, offset]);

    res.json(listResponse(rows.map((row) => ({
      ...presentProduct(row),
      isBestSeller: Boolean(row.is_best_seller),
      isSaleItem: Boolean(row.is_sale_item),
      isActive: Boolean(row.is_active),
    })), total, { page, limit }));
  }),
);

router.patch(
  '/products/:id',
  requireWrite,
  asyncHandler(async (req, res) => {
    requireLive();
    const id = toInt(req.params.id);
    const existing = liveGet('SELECT * FROM products WHERE id = ?', [id]);
    if (!existing) throw new ApiError(404, 'Product not found in the live catalogue.');

    const body = req.body ?? {};
    for (const key of ['price', 'oldPrice', 'stock']) {
      if (body[key] === undefined) continue;
      const value = Number(body[key]);
      if (!Number.isFinite(value) || value < 0) {
        throw new ApiError(400, `${key} must be a non-negative number.`);
      }
    }
    const next = {
      price: body.price !== undefined ? Math.max(0, toNumber(body.price, existing.price)) : existing.price,
      stock: body.stock !== undefined ? Math.max(0, toInt(body.stock, existing.stock)) : existing.stock,
      isActive: body.isActive !== undefined ? (body.isActive ? 1 : 0) : existing.is_active,
      isBestSeller: body.isBestSeller !== undefined ? (body.isBestSeller ? 1 : 0) : existing.is_best_seller,
      isSaleItem: body.isSaleItem !== undefined ? (body.isSaleItem ? 1 : 0) : existing.is_sale_item,
      oldPrice: body.oldPrice !== undefined ? Math.max(0, toNumber(body.oldPrice, existing.old_price)) : existing.old_price,
    };

    liveRun(
      `UPDATE products SET price = ?, old_price = ?, stock = ?, is_active = ?, is_best_seller = ?,
         is_sale_item = ?, updated_at = ? WHERE id = ?`,
      [next.price, next.oldPrice, next.stock, next.isActive, next.isBestSeller, next.isSaleItem, new Date().toISOString(), id],
    );

    const changes = [];
    if (next.price !== existing.price) changes.push(`price ${existing.price} → ${next.price}`);
    if (next.stock !== existing.stock) changes.push(`stock ${existing.stock} → ${next.stock}`);
    if (next.isActive !== existing.is_active) changes.push(next.isActive ? 'published' : 'hidden');
    if (next.isBestSeller !== existing.is_best_seller) changes.push(next.isBestSeller ? 'marked best seller' : 'unmarked best seller');
    if (next.isSaleItem !== existing.is_sale_item) changes.push(next.isSaleItem ? 'marked on sale' : 'removed from sale');

    logFromRequest(req, {
      action: 'update',
      entity: 'live_product',
      entityId: id,
      summary: `Live catalogue: ${existing.name}${changes.length ? ` — ${changes.join(', ')}` : ''}`,
    });

    const updated = liveGet(`${PRODUCT_SELECT} WHERE p.id = ?`, [id]);
    res.json({ data: presentProduct(updated) });
  }),
);

router.get(
  '/orders',
  asyncHandler(async (req, res) => {
    requireLive();
    const { page, limit, offset } = pagination(req, 10, 100);
    const params = [];
    const filters = ['1 = 1'];
    if (req.query.status && req.query.status !== 'all') {
      filters.push('o.status = ?');
      params.push(String(req.query.status));
    }
    if (req.query.search) {
      const term = `%${String(req.query.search).trim()}%`;
      filters.push('(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)');
      params.push(term, term, term);
    }
    const where = `WHERE ${filters.join(' AND ')}`;
    const total = liveGet(`SELECT COUNT(*) AS c FROM orders o ${where}`, params)?.c ?? 0;
    const rows = liveAll(
      `SELECT o.*, (SELECT COALESCE(SUM(quantity),0) FROM order_items i WHERE i.order_number = o.order_number) AS item_count
         FROM orders o ${where} ORDER BY o.order_date DESC, o.id DESC LIMIT ? OFFSET ?`,
      [...params, limit, offset],
    );
    res.json(listResponse(rows, total, { page, limit }));
  }),
);

router.get(
  '/orders/:id',
  asyncHandler(async (req, res) => {
    requireLive();
    const order = liveGet('SELECT * FROM orders WHERE id = ?', [toInt(req.params.id)]);
    if (!order) throw new ApiError(404, 'Order not found.');
    order.items = liveAll('SELECT * FROM order_items WHERE order_number = ? ORDER BY id', [order.order_number]);
    res.json({ data: order });
  }),
);

router.get(
  '/quotations',
  asyncHandler(async (req, res) => {
    requireLive();
    const { page, limit, offset } = pagination(req, 10, 100);
    const params = [];
    const filters = ['1 = 1'];
    if (req.query.status && req.query.status !== 'all') {
      filters.push('q.status = ?');
      params.push(String(req.query.status));
    }
    if (req.query.search) {
      const term = `%${String(req.query.search).trim()}%`;
      filters.push('(q.quote_no LIKE ? OR q.customer_name LIKE ? OR q.customer_email LIKE ?)');
      params.push(term, term, term);
    }
    const where = `WHERE ${filters.join(' AND ')}`;
    const total = liveGet(`SELECT COUNT(*) AS c FROM quotations q ${where}`, params)?.c ?? 0;
    const rows = liveAll(
      `SELECT q.*, (SELECT COUNT(*) FROM quotation_items i WHERE i.quote_id = q.id) AS item_count
         FROM quotations q ${where} ORDER BY q.created_at DESC, q.id DESC LIMIT ? OFFSET ?`,
      [...params, limit, offset],
    );
    res.json(listResponse(rows, total, { page, limit }));
  }),
);

router.get(
  '/quotations/:id',
  asyncHandler(async (req, res) => {
    requireLive();
    const quote = liveGet('SELECT * FROM quotations WHERE id = ?', [toInt(req.params.id)]);
    if (!quote) throw new ApiError(404, 'Quotation not found.');
    quote.items = liveAll('SELECT * FROM quotation_items WHERE quote_id = ? ORDER BY id', [quote.id]).map((item) => ({
      ...item,
      imageUrl: mediaUrl(item.image),
    }));
    res.json({ data: quote });
  }),
);

router.get(
  '/content',
  asyncHandler(async (_req, res) => {
    requireLive();
    res.json({
      available: true,
      heroSlides: liveAll('SELECT * FROM hero_slides ORDER BY id').map((slide) => ({ ...slide, imageUrl: mediaUrl(slide.image) })),
      blogs: liveAll('SELECT id, title, slug, image, created_at FROM blogs ORDER BY created_at DESC').map((blog) => ({
        ...blog,
        imageUrl: mediaUrl(blog.image),
      })),
      faqCounts: liveAll('SELECT scope, COUNT(*) AS count FROM faqs GROUP BY scope'),
      faqs: liveAll('SELECT id, scope, item_id, question FROM faqs ORDER BY id LIMIT 40'),
      footer: liveSettings(),
      footerLinks: liveAll('SELECT section, title, url FROM footer_links ORDER BY section, sort_order'),
      meta: liveMeta(),
    });
  }),
);

export default router;
