import { Router } from 'express';
import { all, get, run } from '../db/index.js';
import { ApiError, asyncHandler, requireFields, pagination, listResponse, toInt } from '../lib/http.js';
import { requireWrite } from '../lib/auth.js';
import { logFromRequest } from '../lib/activity.js';

const router = Router();

const CUSTOMER_TYPES = ['retail', 'trade', 'wholesale', 'hospitality'];

router.get(
  '/',
  asyncHandler(async (req, res) => {
    const { page, limit, offset } = pagination(req, 10);
    const filters = [];
    const params = [];
    if (req.query.search) {
      filters.push('(c.name LIKE ? OR c.email LIKE ? OR c.company LIKE ? OR c.city LIKE ?)');
      const term = `%${String(req.query.search).trim()}%`;
      params.push(term, term, term, term);
    }
    if (req.query.type && req.query.type !== 'all') {
      filters.push('c.type = ?');
      params.push(String(req.query.type));
    }
    const where = filters.length ? `WHERE ${filters.join(' AND ')}` : '';
    const total = get(`SELECT COUNT(*) AS c FROM customers c ${where}`, params).c;

    const rows = all(
      `SELECT c.*,
              (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,
              (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.customer_id = c.id AND o.status != 'cancelled') AS lifetime_value
         FROM customers c ${where}
        ORDER BY c.created_at DESC, c.id DESC
        LIMIT ? OFFSET ?`,
      [...params, limit, offset],
    );
    res.json(listResponse(rows, total, { page, limit }));
  }),
);

router.get(
  '/:id',
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const customer = get('SELECT * FROM customers WHERE id = ?', [id]);
    if (!customer) throw new ApiError(404, 'Customer not found.');
    const orders = all(
      'SELECT id, order_no, status, total, currency, placed_at FROM orders WHERE customer_id = ? ORDER BY placed_at DESC',
      [id],
    );
    res.json({ data: { ...customer, orders } });
  }),
);

router.post(
  '/',
  requireWrite,
  asyncHandler(async (req, res) => {
    requireFields(req.body ?? {}, ['name']);
    const body = req.body;
    const result = run(
      `INSERT INTO customers (name, email, phone, company, city, country, type, status, notes, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        String(body.name).trim(),
        body.email ?? null,
        body.phone ?? null,
        body.company ?? null,
        body.city ?? null,
        body.country ?? 'Pakistan',
        CUSTOMER_TYPES.includes(body.type) ? body.type : 'retail',
        body.status === 'inactive' ? 'inactive' : 'active',
        body.notes ?? null,
        new Date().toISOString(),
      ],
    );
    const created = get('SELECT * FROM customers WHERE id = ?', [Number(result.lastInsertRowid)]);
    logFromRequest(req, { action: 'create', entity: 'customer', entityId: created.id, summary: `Added customer “${created.name}”` });
    res.status(201).json({ data: created });
  }),
);

router.patch(
  '/:id',
  requireWrite,
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const existing = get('SELECT * FROM customers WHERE id = ?', [id]);
    if (!existing) throw new ApiError(404, 'Customer not found.');
    const body = req.body ?? {};
    run(
      `UPDATE customers SET name = ?, email = ?, phone = ?, company = ?, city = ?, country = ?, type = ?, status = ?, notes = ?
       WHERE id = ?`,
      [
        body.name ?? existing.name,
        body.email ?? existing.email,
        body.phone ?? existing.phone,
        body.company ?? existing.company,
        body.city ?? existing.city,
        body.country ?? existing.country,
        CUSTOMER_TYPES.includes(body.type) ? body.type : existing.type,
        body.status ?? existing.status,
        body.notes ?? existing.notes,
        id,
      ],
    );
    const updated = get('SELECT * FROM customers WHERE id = ?', [id]);
    logFromRequest(req, { action: 'update', entity: 'customer', entityId: id, summary: `Updated customer “${updated.name}”` });
    res.json({ data: updated });
  }),
);

router.delete(
  '/:id',
  requireWrite,
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const existing = get('SELECT * FROM customers WHERE id = ?', [id]);
    if (!existing) throw new ApiError(404, 'Customer not found.');
    run('DELETE FROM customers WHERE id = ?', [id]);
    logFromRequest(req, { action: 'delete', entity: 'customer', entityId: id, summary: `Removed customer “${existing.name}”` });
    res.json({ ok: true });
  }),
);

export default router;
