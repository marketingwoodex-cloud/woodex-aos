import { Router } from 'express';
import { all, get } from '../db/index.js';
import { asyncHandler, nowIso } from '../lib/http.js';

const router = Router();

/** Build the last N month buckets, oldest first. */
function monthBuckets(count = 6) {
  const buckets = [];
  const cursor = new Date();
  cursor.setUTCDate(1);
  for (let i = count - 1; i >= 0; i -= 1) {
    const d = new Date(Date.UTC(cursor.getUTCFullYear(), cursor.getUTCMonth() - i, 1));
    buckets.push({
      key: `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`,
      label: d.toLocaleString('en-US', { month: 'short', timeZone: 'UTC' }),
      value: 0,
      orders: 0,
    });
  }
  return buckets;
}

router.get(
  '/',
  asyncHandler(async (_req, res) => {
    const buckets = monthBuckets(6);
    const since = `${buckets[0].key}-01`;

    const revenueRows = all(
      `SELECT strftime('%Y-%m', placed_at) AS month, SUM(total) AS revenue, COUNT(*) AS orders
         FROM orders
        WHERE status != 'cancelled' AND placed_at >= ?
        GROUP BY month`,
      [since],
    );
    for (const row of revenueRows) {
      const bucket = buckets.find((b) => b.key === row.month);
      if (bucket) {
        bucket.value = Math.round(row.revenue || 0);
        bucket.orders = row.orders || 0;
      }
    }

    const totals = get(
      `SELECT
         COUNT(*) AS order_count,
         COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total END), 0) AS revenue,
         COALESCE(AVG(CASE WHEN status != 'cancelled' THEN total END), 0) AS avg_order,
         SUM(CASE WHEN status IN ('pending','confirmed','in_production') THEN 1 ELSE 0 END) AS open_orders
       FROM orders`,
    );

    const customers = get("SELECT COUNT(*) AS c FROM customers WHERE status = 'active'");
    const products = get('SELECT COUNT(*) AS c FROM products');
    const lowStock = get("SELECT COUNT(*) AS c FROM products WHERE stock <= reorder_level AND status != 'archived'");

    const statusRows = all('SELECT status, COUNT(*) AS count, COALESCE(SUM(total), 0) AS value FROM orders GROUP BY status');
    const orderStatuses = ['pending', 'confirmed', 'in_production', 'shipped', 'delivered', 'cancelled'].map((status) => {
      const row = statusRows.find((r) => r.status === status);
      return { status, count: row?.count ?? 0, value: Math.round(row?.value ?? 0) };
    });

    const topProducts = all(
      `SELECT p.id, p.name, p.sku, c.name AS category, SUM(oi.qty) AS units, SUM(oi.qty * oi.unit_price) AS revenue
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id AND o.status != 'cancelled'
         LEFT JOIN products p ON p.id = oi.product_id
         LEFT JOIN categories c ON c.id = p.category_id
        GROUP BY oi.product_id
        ORDER BY revenue DESC
        LIMIT 5`,
    );

    const recentOrders = all(
      `SELECT o.id, o.order_no, o.status, o.total, o.currency, o.placed_at, c.name AS customer
         FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
        ORDER BY o.placed_at DESC LIMIT 6`,
    );

    const recentActivity = all(
      `SELECT id, user_name, action, entity, summary, created_at FROM activity ORDER BY created_at DESC LIMIT 8`,
    );

    const projects = all(
      `SELECT p.*, (SELECT COUNT(*) FROM deployments d WHERE d.project_id = p.id) AS deployment_count
         FROM projects p ORDER BY p.name`,
    );

    const lowStockItems = all(
      `SELECT id, name, sku, stock, reorder_level FROM products
        WHERE stock <= reorder_level AND status != 'archived'
        ORDER BY stock ASC LIMIT 5`,
    );

    res.json({
      generatedAt: nowIso(),
      kpis: {
        revenue: Math.round(totals?.revenue ?? 0),
        orders: totals?.order_count ?? 0,
        openOrders: totals?.open_orders ?? 0,
        avgOrder: Math.round(totals?.avg_order ?? 0),
        customers: customers?.c ?? 0,
        products: products?.c ?? 0,
        lowStock: lowStock?.c ?? 0,
      },
      revenueSeries: buckets,
      ordersByStatus: orderStatuses,
      topProducts,
      recentOrders,
      recentActivity,
      projects,
      lowStockItems,
    });
  }),
);

export default router;
