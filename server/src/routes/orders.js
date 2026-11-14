import { Router } from 'express';
import { all, get, run, tx } from '../db/index.js';
import { ApiError, asyncHandler, requireFields, pagination, listResponse, toInt, toNumber, nowIso } from '../lib/http.js';
import { requireWrite } from '../lib/auth.js';
import { logFromRequest } from '../lib/activity.js';

const router = Router();

const STATUSES = ['pending', 'confirmed', 'in_production', 'shipped', 'delivered', 'cancelled'];
const CHANNELS = ['showroom', 'online', 'trade', 'phone'];

function nextOrderNumber() {
  const year = new Date().getUTCFullYear();
  const row = get(`SELECT COUNT(*) AS c FROM orders WHERE order_no LIKE ?`, [`WO-${year}-%`]);
  const sequence = String((row?.c ?? 0) + 1).padStart(4, '0');
  return `WO-${year}-${sequence}`;
}

router.get(
  '/',
  asyncHandler(async (req, res) => {
    const { page, limit, offset } = pagination(req, 10);
    const filters = [];
    const params = [];

    if (req.query.status && req.query.status !== 'all') {
      filters.push('o.status = ?');
      params.push(String(req.query.status));
    }
    if (req.query.channel && req.query.channel !== 'all') {
      filters.push('o.channel = ?');
      params.push(String(req.query.channel));
    }
    if (req.query.search) {
      const term = `%${String(req.query.search).trim()}%`;
      filters.push('(o.order_no LIKE ? OR c.name LIKE ? OR c.company LIKE ?)');
      params.push(term, term, term);
    }

    const where = filters.length ? `WHERE ${filters.join(' AND ')}` : '';
    const total = get(`SELECT COUNT(*) AS c FROM orders o LEFT JOIN customers c ON c.id = o.customer_id ${where}`, params).c;

    const rows = all(
      `SELECT o.*, c.name AS customer, c.company, c.type AS customer_type,
              (SELECT COALESCE(SUM(qty), 0) FROM order_items i WHERE i.order_id = o.id) AS item_count
         FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
         ${where}
        ORDER BY o.placed_at DESC, o.id DESC
        LIMIT ? OFFSET ?`,
      [...params, limit, offset],
    );

    const summary = get(
      `SELECT COALESCE(SUM(total), 0) AS value, COUNT(*) AS count FROM orders o ${where}`,
      params,
    );

    res.json(listResponse(rows, total, { page, limit }, { summary }));
  }),
);

router.get(
  '/:id',
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const order = get(
      `SELECT o.*, c.name AS customer, c.company, c.email AS customer_email, c.phone AS customer_phone, c.city AS customer_city
         FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE o.id = ?`,
      [id],
    );
    if (!order) throw new ApiError(404, 'Order not found.');
    order.items = all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [id]);
    res.json({ data: order });
  }),
);

router.post(
  '/',
  requireWrite,
  asyncHandler(async (req, res) => {
    const body = req.body ?? {};
    requireFields(body, ['customerId']);
    if (!Array.isArray(body.items) || body.items.length === 0) {
      throw new ApiError(400, 'An order needs at least one line item.');
    }
    const customer = get('SELECT * FROM customers WHERE id = ?', [toInt(body.customerId)]);
    if (!customer) throw new ApiError(404, 'Customer not found.');

    const lines = body.items.map((item) => {
      const product = get('SELECT * FROM products WHERE id = ?', [toInt(item.productId)]);
      if (!product) throw new ApiError(400, 'One of the selected products no longer exists.');
      const qty = Math.max(1, toInt(item.qty, 1));
      return { product, qty, unit_price: toNumber(item.unitPrice, product.price) };
    });

    const subtotal = lines.reduce((sum, line) => sum + line.unit_price * line.qty, 0);
    const taxRate = toNumber(body.taxRate, 5);
    const tax = Math.round(subtotal * (taxRate / 100));
    const shipping = toNumber(body.shipping, 0);
    const total = subtotal + tax + shipping;
    const stamp = new Date().toISOString();
    const status = STATUSES.includes(body.status) ? body.status : 'pending';
    const channel = CHANNELS.includes(body.channel) ? body.channel : 'showroom';

    const created = tx(() => {
      const result = run(
        `INSERT INTO orders (order_no, customer_id, status, channel, subtotal, tax, shipping, total, currency, notes, placed_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          nextOrderNumber(), customer.id, status, channel, subtotal, tax, shipping, total,
          body.currency || 'PKR', body.notes ?? null, body.placedAt || stamp, stamp, stamp,
        ],
      );
      const orderId = Number(result.lastInsertRowid);
      for (const line of lines) {
        run(
          `INSERT INTO order_items (order_id, product_id, name, sku, qty, unit_price) VALUES (?, ?, ?, ?, ?, ?)`,
          [orderId, line.product.id, line.product.name, line.product.sku, line.qty, line.unit_price],
        );
        run('UPDATE products SET stock = MAX(0, stock - ?), updated_at = ? WHERE id = ?', [line.qty, stamp, line.product.id]);
      }
      return orderId;
    });

    const order = get('SELECT * FROM orders WHERE id = ?', [created]);
    logFromRequest(req, {
      action: 'create',
      entity: 'order',
      entityId: created,
      summary: `Created order ${order.order_no} for ${customer.name}`,
    });
    res.status(201).json({ data: order });
  }),
);

router.patch(
  '/:id',
  requireWrite,
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const existing = get('SELECT * FROM orders WHERE id = ?', [id]);
    if (!existing) throw new ApiError(404, 'Order not found.');
    const body = req.body ?? {};
    if (body.status && !STATUSES.includes(body.status)) throw new ApiError(400, 'Unknown order status.');

    run('UPDATE orders SET status = ?, channel = ?, notes = ?, updated_at = ? WHERE id = ?', [
      body.status ?? existing.status,
      CHANNELS.includes(body.channel) ? body.channel : existing.channel,
      body.notes ?? existing.notes,
      new Date().toISOString(),
      id,
    ]);

    const updated = get('SELECT * FROM orders WHERE id = ?', [id]);
    logFromRequest(req, {
      action: body.status && body.status !== existing.status ? 'status' : 'update',
      entity: 'order',
      entityId: id,
      summary: body.status && body.status !== existing.status
        ? `Order ${existing.order_no} moved to ${body.status.replace('_', ' ')}`
        : `Updated order ${existing.order_no}`,
    });
    res.json({ data: updated });
  }),
);

export default router;
