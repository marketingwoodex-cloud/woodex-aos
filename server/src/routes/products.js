import { Router } from 'express';
import { all, get, run } from '../db/index.js';
import { ApiError, asyncHandler, requireFields, pagination, listResponse, toNumber, toInt } from '../lib/http.js';
import { requireWrite } from '../lib/auth.js';
import { logFromRequest } from '../lib/activity.js';

const router = Router();

const SELECT_PRODUCT = `
  SELECT p.*, c.name AS category
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
`;

router.get(
  '/categories',
  asyncHandler(async (_req, res) => {
    const rows = all(`
      SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
        FROM categories c ORDER BY c.name
    `);
    res.json({ data: rows });
  }),
);

router.post(
  '/categories',
  requireWrite,
  asyncHandler(async (req, res) => {
    requireFields(req.body ?? {}, ['name']);
    const name = String(req.body.name).trim();
    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    if (get('SELECT id FROM categories WHERE lower(name) = lower(?)', [name])) {
      throw new ApiError(409, 'A category with that name already exists.');
    }
    const result = run('INSERT INTO categories (name, slug, description, created_at) VALUES (?, ?, ?, ?)', [
      name,
      slug,
      req.body.description ?? null,
      new Date().toISOString(),
    ]);
    logFromRequest(req, { action: 'create', entity: 'category', entityId: result.lastInsertRowid, summary: `Created category “${name}”` });
    res.status(201).json({ data: get('SELECT * FROM categories WHERE id = ?', [Number(result.lastInsertRowid)]) });
  }),
);

router.get(
  '/',
  asyncHandler(async (req, res) => {
    const { page, limit, offset } = pagination(req, 10);
    const filters = [];
    const params = [];

    if (req.query.search) {
      filters.push('(p.name LIKE ? OR p.sku LIKE ? OR p.material LIKE ?)');
      const term = `%${String(req.query.search).trim()}%`;
      params.push(term, term, term);
    }
    if (req.query.category && req.query.category !== 'all') {
      filters.push('p.category_id = ?');
      params.push(toInt(req.query.category));
    }
    if (req.query.status && req.query.status !== 'all') {
      filters.push('p.status = ?');
      params.push(String(req.query.status));
    }
    if (req.query.stock === 'low') filters.push('p.stock <= p.reorder_level');

    const where = filters.length ? `WHERE ${filters.join(' AND ')}` : '';
    const total = get(`SELECT COUNT(*) AS c FROM products p ${where}`, params).c;

    const rows = all(
      `${SELECT_PRODUCT} ${where} ORDER BY p.updated_at DESC, p.id DESC LIMIT ? OFFSET ?`,
      [...params, limit, offset],
    );

    res.json(listResponse(rows, total, { page, limit }));
  }),
);

router.get(
  '/:id',
  asyncHandler(async (req, res) => {
    const product = get(`${SELECT_PRODUCT} WHERE p.id = ?`, [toInt(req.params.id)]);
    if (!product) throw new ApiError(404, 'Product not found.');
    res.json({ data: product });
  }),
);

function productPayload(body) {
  requireFields(body ?? {}, ['name', 'sku', 'price']);
  return {
    name: String(body.name).trim(),
    sku: String(body.sku).trim().toUpperCase(),
    category_id: body.categoryId ? toInt(body.categoryId) : null,
    description: body.description ?? null,
    material: body.material ?? null,
    finish: body.finish ?? null,
    price: toNumber(body.price),
    cost: toNumber(body.cost),
    stock: toInt(body.stock),
    reorder_level: toInt(body.reorderLevel, 5),
    status: ['active', 'draft', 'archived'].includes(body.status) ? body.status : 'active',
  };
}

router.post(
  '/',
  requireWrite,
  asyncHandler(async (req, res) => {
    const payload = productPayload(req.body);
    if (get('SELECT id FROM products WHERE sku = ?', [payload.sku])) {
      throw new ApiError(409, 'A product with that SKU already exists.');
    }
    const stamp = new Date().toISOString();
    const result = run(
      `INSERT INTO products (sku, name, category_id, description, material, finish, price, cost, stock, reorder_level, status, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        payload.sku, payload.name, payload.category_id, payload.description, payload.material, payload.finish,
        payload.price, payload.cost, payload.stock, payload.reorder_level, payload.status, stamp, stamp,
      ],
    );
    const created = get(`${SELECT_PRODUCT} WHERE p.id = ?`, [Number(result.lastInsertRowid)]);
    logFromRequest(req, { action: 'create', entity: 'product', entityId: created.id, summary: `Added product “${created.name}”` });
    res.status(201).json({ data: created });
  }),
);

router.patch(
  '/:id',
  requireWrite,
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const existing = get('SELECT * FROM products WHERE id = ?', [id]);
    if (!existing) throw new ApiError(404, 'Product not found.');

    const body = req.body ?? {};
    const merged = {
      name: body.name ?? existing.name,
      sku: (body.sku ?? existing.sku).toUpperCase(),
      categoryId: body.categoryId ?? existing.category_id,
      description: body.description ?? existing.description,
      material: body.material ?? existing.material,
      finish: body.finish ?? existing.finish,
      price: body.price ?? existing.price,
      cost: body.cost ?? existing.cost,
      stock: body.stock ?? existing.stock,
      reorderLevel: body.reorderLevel ?? existing.reorder_level,
      status: body.status ?? existing.status,
    };
    const payload = productPayload(merged);

    const clash = get('SELECT id FROM products WHERE sku = ? AND id != ?', [payload.sku, id]);
    if (clash) throw new ApiError(409, 'Another product already uses that SKU.');

    run(
      `UPDATE products SET sku = ?, name = ?, category_id = ?, description = ?, material = ?, finish = ?,
         price = ?, cost = ?, stock = ?, reorder_level = ?, status = ?, updated_at = ?
       WHERE id = ?`,
      [
        payload.sku, payload.name, payload.category_id, payload.description, payload.material, payload.finish,
        payload.price, payload.cost, payload.stock, payload.reorder_level, payload.status, new Date().toISOString(), id,
      ],
    );
    const updated = get(`${SELECT_PRODUCT} WHERE p.id = ?`, [id]);
    logFromRequest(req, { action: 'update', entity: 'product', entityId: id, summary: `Updated product “${updated.name}”` });
    res.json({ data: updated });
  }),
);

router.delete(
  '/:id',
  requireWrite,
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const existing = get('SELECT * FROM products WHERE id = ?', [id]);
    if (!existing) throw new ApiError(404, 'Product not found.');
    run('DELETE FROM products WHERE id = ?', [id]);
    logFromRequest(req, { action: 'delete', entity: 'product', entityId: id, summary: `Removed product “${existing.name}”` });
    res.json({ ok: true });
  }),
);

export default router;
